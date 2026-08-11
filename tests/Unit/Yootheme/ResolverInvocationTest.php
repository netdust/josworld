<?php

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use Brain\Monkey\Functions;
use josworld\services\yootheme\YOOthemeSourcesService;
use NtdstTests\Unit\TestCase;

use function josworld\services\yootheme\resolve_jw_field;

require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';
require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/FieldResolver.php';
require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/YOOthemeSourcesService.php';

/**
 * Invokes the EMITTED resolver with the EMITTED args and asserts the
 * VALUE — SourcesServiceTest proves resolver shape exhaustively but never
 * calls one, which is how these defects stayed green.
 *
 * Security contract: a caller-supplied schema/prefix is not an allow-list
 * (a saved builder layout can forge resolver args, gated only by
 * edit_post), so the resolver accepts only a type + field name and
 * consults the service's vetted registry, populated after the _jw_
 * boundary and the exclude_fields filter. Correctness contract: a
 * repeater row is a plain array with no ID — it resolves from the row, is
 * never trusted for a post id, and a root with no post id denies instead
 * of reading post 0.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */
final class ResolverInvocationTest extends TestCase
{
    private const PREFIX = '_jw_';

    protected function setUp(): void
    {
        parent::setUp();

        YOOthemeSourcesService::flushRegistry();

        Functions\when('apply_filters')->alias(
            static fn(string $hook, mixed $value, mixed ...$args): mixed => $value,
        );
    }

    protected function tearDown(): void
    {
        YOOthemeSourcesService::flushRegistry();

        parent::tearDown();
    }

    // ── C-1: the security contract ─────────────────────────────────────

    /**
     * The headline denial: a caller cannot widen what is readable by
     * supplying its own schema — the resolver accepts none.
     *
     * @dataProvider forgedArgsProvider
     *
     * @param array<string, mixed> $forgedArgs
     */
    public function test_a_caller_cannot_read_arbitrary_meta_by_forging_resolver_args(
        array $forgedArgs,
        string $why,
    ): void {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        Functions\when('ntdst_get_post_meta_from_cache')->justReturn([
            '_edit_lock' => '1735689600:1',
            'secret_api_key' => 'sk-live-XXXX',
            '_jw_internal_scratch' => 'never-registered',
            '_jw_client' => 'ACME',
        ]);

        $this->assertSame(
            '',
            resolve_jw_field((object) ['ID' => 42], $forgedArgs),
            "forged resolver args must resolve empty ({$why}). A caller-supplied allow-list is not "
            . 'an allow-list — the schema and prefix must come from the service registry, which is '
            . 'populated only after the _jw_ boundary and the exclude_fields filter have run.',
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public function forgedArgsProvider(): array
    {
        return [
            'A1 unprefixed plugin secret' => [
                [
                    'type' => 'Case',
                    'field' => 'secret_api_key',
                    'schema' => ['secret_api_key' => ['type' => 'text']],
                    'prefix' => '',
                ],
                'an unprefixed key belonging to another plugin',
            ],
            'A2 WordPress internal' => [
                [
                    'type' => 'Case',
                    'field' => '_edit_lock',
                    'schema' => ['_edit_lock' => ['type' => 'text']],
                    'prefix' => '',
                ],
                'a WP internal key — the exact harm threat 3 names',
            ],
            'A3 undeclared _jw_ meta' => [
                [
                    'type' => 'Case',
                    'field' => 'internal_scratch',
                    'schema' => ['internal_scratch' => ['type' => 'text']],
                    'prefix' => self::PREFIX,
                ],
                'a _jw_ key the model never declared',
            ],
            'A4 unknown type name' => [
                ['type' => 'NotARegisteredType', 'field' => 'client'],
                'a type that was never collected has no registry entry to consult',
            ],
        ];
    }

    // Positive control: with honest args a declared field still resolves —
    // otherwise "return '' always" would satisfy every denial test.
    public function test_a_declared_field_still_resolves_through_the_registry(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $types = $service->buildTypes();

        Functions\when('ntdst_get_post_meta_from_cache')->justReturn(['_jw_client' => 'ACME']);

        $call = $types['Case']['fields']['client']['extensions']['call'];

        $this->assertSame(
            'ACME',
            ($call['func'])((object) ['ID' => 42], $call['args'] ?? []),
            'a parent field must still resolve from post meta via the composed _jw_ key',
        );
    }

    // Schema/prefix absent from the wire cannot be tampered with, and the
    // serialised payload stops embedding the whole schema per field.
    public function test_the_emitted_args_carry_no_schema_and_no_prefix(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $checked = 0;

        foreach ($service->buildTypes() as $typeName => $config) {
            foreach ($config['fields'] ?? [] as $fieldName => $definition) {
                $args = $definition['extensions']['call']['args'] ?? [];

                $this->assertArrayNotHasKey(
                    'schema',
                    $args,
                    "{$typeName}.{$fieldName} must not ship its schema on the wire",
                );
                $this->assertArrayNotHasKey(
                    'prefix',
                    $args,
                    "{$typeName}.{$fieldName} must not ship its meta prefix on the wire",
                );
                ++$checked;
            }
        }

        $this->assertGreaterThan(0, $checked, 'vacuity guard: some fields must have been checked');
    }

    // ── C-2 / I-1 / I-2: the repeater row ──────────────────────────────

    // A repeater row must resolve its sub-field FROM THE ROW, never from
    // a post-id lookup.
    public function test_a_repeater_row_resolves_its_sub_field_from_the_row(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $types = $service->buildTypes();

        $call = $types['CaseImpactItem']['fields']['figure']['extensions']['call'] ?? null;

        $this->assertIsArray($call, 'precondition: the sub-type field must carry a resolver');

        // No meta read may happen for a row — the value is already in hand.
        Functions\expect('ntdst_get_post_meta_from_cache')->never();

        $row = ['figure' => '+7 mio', 'description' => 'extra omzet'];

        $this->assertSame(
            '+7 mio',
            ($call['func'])($row, $call['args'] ?? []),
            'a repeater row must resolve its own sub-field value. Resolving empty here is the silent '
            . 'half-failure this project exists to prevent: the builder lists the field, the grid '
            . 'renders blank, and nothing errors.',
        );
    }

    // Reads the REQUESTED key, not simply the row's first value.
    public function test_a_repeater_row_resolves_each_sub_field_independently(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $types = $service->buildTypes();

        $call = $types['CaseImpactItem']['fields']['description']['extensions']['call'];
        $row = ['figure' => '+7 mio', 'description' => 'extra omzet'];

        $this->assertSame('extra omzet', ($call['func'])($row, $call['args'] ?? []));
    }

    // The row must NEVER be trusted for a post id: `ID => 999` in
    // editor-authored row content once redirected the read to post 999,
    // with no status or capability check.
    public function test_a_repeater_row_carrying_an_id_cannot_redirect_the_read(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $types = $service->buildTypes();

        $call = $types['CaseImpactItem']['fields']['figure']['extensions']['call'];

        Functions\expect('ntdst_get_post_meta_from_cache')->never();

        $this->assertSame(
            '40%',
            ($call['func'])(['ID' => 999, 'figure' => '40%'], $call['args'] ?? []),
            'a row must resolve from its own data; an ID key in editor-authored row content must '
            . 'never redirect the read to another post',
        );
    }

    // Row edge: a missing key resolves empty, not null and not a notice.
    public function test_a_repeater_row_missing_the_field_resolves_empty(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $types = $service->buildTypes();

        $call = $types['CaseImpactItem']['fields']['figure']['extensions']['call'];

        $this->assertSame('', ($call['func'])(['description' => 'only this'], $call['args'] ?? []));
    }

    // A root with no derivable post id must DENY rather than silently
    // resolve post 0 — without spending a meta read doing it.
    public function test_a_parent_field_denies_when_the_root_has_no_post_id(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $types = $service->buildTypes();

        $call = $types['Case']['fields']['client']['extensions']['call'];

        Functions\expect('ntdst_get_post_meta_from_cache')->never();

        $this->assertSame(
            '',
            ($call['func'])(null, $call['args'] ?? []),
            'a null root (an archive with no current item, or the builder previewing with nothing '
            . 'selected) must resolve empty without reading meta for post 0',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function caseConfig(): array
    {
        return [
            'meta_prefix' => self::PREFIX,
            'fields' => [
                'client' => ['type' => 'text', 'label' => 'Klant'],
                'impact' => [
                    'type' => 'repeater',
                    'label' => 'Impact',
                    'sub_fields' => [
                        'figure' => ['type' => 'text', 'label' => 'Cijfer'],
                        'description' => ['type' => 'text', 'label' => 'Omschrijving'],
                    ],
                ],
            ],
        ];
    }
}
