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
 * Unit tier: the CLOSING CHECKS for Cluster B2 review findings C-1
 * (security), C-2/I-1/I-2 (correctness) and I-4 (the blindness that hid
 * them). They share one root cause and one fix, so they close together.
 *
 * ── THE SECURITY DEFECT (C-1) ──────────────────────────────────────────
 *
 * The white-page rule forces the resolver to be a PUBLIC, literally-nameable
 * standalone function — YOOtheme serialises `func` as a string, so nothing
 * else can work. That function then took `field`, `schema` AND `prefix`
 * verbatim from its caller and handed them to `FieldResolver`, whose
 * allow-list IS that caller-supplied `$schema`.
 *
 * An allow-list the caller supplies is not an allow-list. Reproduced by
 * execution before the fix:
 *
 *     A1 unprefixed plugin secret  -> 'sk-live-XXXX'
 *     A2 WP internal _edit_lock    -> '1735689600:1'
 *     A3 undeclared _jw_ meta      -> 'never-registered'
 *
 * Both boundaries — threat 4's `_jw_` gate and threat 2's
 * `ntdst/yootheme/exclude_fields` filter — live in `collect()` and were
 * entirely bypassed on this path. Reachable two ways: any code in the
 * process, and (worse) a SAVED BUILDER LAYOUT, because
 * `SourceQuery::queryField()` copies arbitrary directives with arbitrary
 * arguments out of the layout, gated only by `edit_post`. That is an
 * Editor-reachable arbitrary-post-meta read.
 *
 * THE FIX THIS PINS: the resolver takes NO schema and NO prefix from its
 * caller. It receives only a TYPE NAME and a FIELD NAME, and looks the
 * vetted schema/prefix up in the service's own registry — the same registry
 * `collect()` populates after applying both boundaries. Caller-supplied
 * schema/prefix become unrepresentable rather than merely discouraged.
 *
 * ── THE CORRECTNESS DEFECT (C-2 / I-1 / I-2) ───────────────────────────
 *
 * A repeater ROW is a plain array — `['figure' => '+7 mio']` — with no `ID`.
 * The resolver derived `$postId` from `$root->ID` and fell back to `0`, so
 * every grid cell read `_jw_figure` on post 0 and returned ''. Impact,
 * Take-aways and Interview would all render blank.
 *
 * THAT IS THE EXACT FAILURE THIS PROJECT EXISTS TO PREVENT — "fields
 * visible, values empty" — recurring one level down from the parent-field
 * case T05's contract was written to make unshippable.
 *
 * Worse, `$root` was TRUSTED for the post id: a row carrying `ID => 999`
 * redirected the read to post 999 (`SECRET-FROM-999`), with no post_status
 * and no capability check anywhere in the bridge.
 *
 * ── WHY 19 GREEN TESTS MISSED ALL OF IT (I-4) ──────────────────────────
 *
 * SourcesServiceTest asserts, rigorously, the SHAPE of every emitted
 * resolver reference: that it is a literal string, that the named function
 * exists, that it is not a Closure or `[Class, 'method']`. It never once
 * CALLS one. Shape was proven exhaustively; behaviour never was.
 *
 * So the rule for this file is: INVOKE THE EMITTED RESOLVER, with the
 * EMITTED args, against a realistic root, and assert the VALUE. Nothing
 * here reads the service's source or trusts a docblock.
 *
 * A test file by nature declares a symbol and executes side effects.
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
     * The headline denial. A caller cannot widen what is readable by
     * supplying its own schema — because the resolver accepts no schema at
     * all. Each payload here is one of the three reads reproduced against
     * the pre-fix code.
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

    /**
     * The positive control for the above: with HONEST args — the ones the
     * service actually emits — a declared field still resolves. Without
     * this, "return '' always" would satisfy every denial test.
     */
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

    /**
     * The emitted args must not CARRY the schema or the prefix at all —
     * if they are absent from the wire, they cannot be tampered with, and
     * the payload YOOtheme JSON-serialises stops embedding the whole schema
     * in every single field.
     */
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

    /**
     * THE CRITICAL CORRECTNESS BUG. A repeater row must resolve its
     * sub-field FROM THE ROW, never from a post-id lookup.
     */
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

    /**
     * Proves the fix reads the REQUESTED key rather than, say, always
     * returning the row's first value.
     */
    public function test_a_repeater_row_resolves_each_sub_field_independently(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $types = $service->buildTypes();

        $call = $types['CaseImpactItem']['fields']['description']['extensions']['call'];
        $row = ['figure' => '+7 mio', 'description' => 'extra omzet'];

        $this->assertSame('extra omzet', ($call['func'])($row, $call['args'] ?? []));
    }

    /**
     * I-1 — the row must NEVER be trusted for a post id. Pre-fix, a row
     * carrying `ID => 999` redirected the read to post 999 and returned
     * that post's meta, with no post_status and no capability check.
     */
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

    /**
     * Row edge: a missing key resolves empty, not null and not a notice.
     */
    public function test_a_repeater_row_missing_the_field_resolves_empty(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $types = $service->buildTypes();

        $call = $types['CaseImpactItem']['fields']['figure']['extensions']['call'];

        $this->assertSame('', ($call['func'])(['description' => 'only this'], $call['args'] ?? []));
    }

    /**
     * C-2's parent-side twin: a root with no derivable post id must DENY
     * rather than silently resolving post 0 — and must not spend a meta
     * read doing it.
     */
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
