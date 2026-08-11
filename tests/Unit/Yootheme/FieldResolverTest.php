<?php

/**
 * Contract for FieldResolver::resolve(): field `client` reads `_jw_client`,
 * never the bare key — the reference implementation reads bare field names
 * and skips `_`-prefixed keys, so a verbatim port lists every field in the
 * builder and renders every value empty, silently. The `$schema` argument
 * is the allow-list: there is no parameter through which a caller-supplied
 * raw meta key can enter. Missing scalars resolve '', repeaters resolve
 * lists ([] for zero rows or corrupt shapes — never null, never a fatal),
 * and reads route through ntdst-core's cached accessor: at most two calls
 * per post, `get_post_meta()` never — which forces a per-post memo.
 *
 * Proof-technique notes (both found the hard way):
 * - Brain Monkey intercepts FUNCTIONS only, so the resolver must reach the
 *   cached accessor through a GLOBAL-namespace forwarding function called
 *   with a leading `\` — a function declared in the theme's own namespace
 *   is defined at test-file load and Monkey cannot re-stub it.
 * - The per-post memo persists across tests in one PHP process (every test
 *   resolves post 42), so it must be flushable — TestCase::tearDown()
 *   calls FieldResolver::flush().
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use Brain\Monkey\Functions;
use NtdstTests\Unit\TestCase;
use josworld\services\yootheme\FieldResolver;

// The theme has no PSR-4 autoloader — require the unit under test
// directly. ABSPATH is defined by tests/bootstrap-unit.php.
require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/FieldResolver.php';

final class FieldResolverTest extends TestCase
{
    /** The meta prefix every josworld model uses. */
    private const PREFIX = '_jw_';

    /**
     * The cached-accessor seam. The implementer routes reads through ONE of
     * these; the helpers accept either. Both return the post's full meta
     * map in one query, keyed by meta key, maybe_unserialize'd.
     *
     * @var list<string>
     */
    private const CACHED_ACCESSORS = [
        'ntdst_get_post_meta_from_cache',
        'ntdst_get_cached_post_meta',
    ];

    /**
     * CaseService's real field list, trimmed to the shape the resolver
     * cares about.
     *
     * @return array<string, mixed>
     */
    private function caseSchema(): array
    {
        return [
            'client' => ['type' => 'text', 'label' => 'Klant', 'required' => true],
            'tagline' => ['type' => 'text', 'label' => 'Tagline'],
            'what_we_did' => ['type' => 'textarea', 'label' => 'Wat we hebben gedaan'],
            'target_audience' => ['type' => 'textarea', 'label' => 'Doelgroep'],
            'impact' => [
                'type' => 'repeater',
                'label' => 'Impact',
                'sub_fields' => [
                    'figure' => ['type' => 'text', 'label' => 'Cijfer'],
                    'description' => ['type' => 'text', 'label' => 'Omschrijving'],
                ],
            ],
            'challenge_title' => ['type' => 'text', 'label' => 'Uitdaging — titel'],
            'challenge_body' => ['type' => 'textarea', 'label' => 'Uitdaging — tekst'],
            'result_title' => ['type' => 'text', 'label' => 'Resultaat — titel'],
            'result_body' => ['type' => 'textarea', 'label' => 'Resultaat — tekst'],
            'has_takeaways' => ['type' => 'boolean', 'label' => 'Heeft take-aways?'],
        ];
    }

    /**
     * Stub every accepted cached accessor to return `$meta` (the post's
     * full meta map, PREFIXED keys, as ntdst-core's accessors return it).
     *
     * @param array<string, mixed> $meta
     */
    private function stubCachedMeta(array $meta): void
    {
        foreach (self::CACHED_ACCESSORS as $accessor) {
            Functions\when($accessor)->justReturn($meta);
        }

        // get_post_meta is left UNSTUBBED on purpose: a call surfaces as a
        // Brain Monkey "undefined function" failure instead of silently
        // returning null and passing.
    }

    /**
     * The stub returns ONLY the keys named in `$meta`, so a resolver that
     * composes the wrong key gets a miss and returns empty — the VALUE
     * assertion is what proves the composed key, no interaction mocking.
     *
     * @param array<string, mixed>      $meta
     * @param array<string, mixed>|null $schema
     */
    private function resolveWith(array $meta, string $field, ?array $schema = null): mixed
    {
        $this->stubCachedMeta($meta);

        return FieldResolver::resolve(
            42,
            $field,
            $schema ?? $this->caseSchema(),
            self::PREFIX,
        );
    }

    // ── Prefix composition ──

    // The stubbed map contains ONLY the prefixed key, so returning the
    // value proves `_jw_` . `client` was composed.
    public function test_a_declared_field_resolves_from_the_prefixed_meta_key(): void
    {
        $value = $this->resolveWith(
            ['_jw_client' => 'Colruyt Group'],
            'client',
        );

        $this->assertSame(
            'Colruyt Group',
            $value,
            'field `client` must read meta key `_jw_client` (getMetaPrefix() . $field)',
        );
    }

    // The map holds BOTH keys with different values: a bare read returns
    // the decoy and fails loudly here, where in production it would just
    // render every field silently empty.
    public function test_the_bare_unprefixed_meta_key_is_never_read(): void
    {
        $value = $this->resolveWith(
            [
                'client' => 'WRONG — this is the unprefixed decoy the reference impl would read',
                '_jw_client' => 'Colruyt Group',
            ],
            'client',
        );

        $this->assertSame(
            'Colruyt Group',
            $value,
            'resolution must prefer the PREFIXED key; reading the bare `client` is the reference '
                . "implementation's bug (ludoluykx :507) that renders every josworld field empty",
        );
    }

    // Every declared field composes its own key — prefixing only when the
    // bare key is missing fails here.
    public function test_every_declared_scalar_field_composes_its_own_prefixed_key(): void
    {
        $meta = [
            '_jw_client' => 'Colruyt Group',
            '_jw_tagline' => 'Van data naar daad',
            '_jw_challenge_title' => 'De uitdaging',
            '_jw_result_body' => 'Het resultaat',
            // Bare decoys for all four — none may be read.
            'client' => 'WRONG',
            'tagline' => 'WRONG',
            'challenge_title' => 'WRONG',
            'result_body' => 'WRONG',
        ];

        $expected = [
            'client' => 'Colruyt Group',
            'tagline' => 'Van data naar daad',
            'challenge_title' => 'De uitdaging',
            'result_body' => 'Het resultaat',
        ];

        foreach ($expected as $field => $want) {
            $this->assertSame(
                $want,
                $this->resolveWith($meta, $field),
                "`{$field}` must resolve from `_jw_{$field}`, never from the bare key",
            );
        }
    }

    // ── Denial path: no caller-supplied raw meta key ──

    // `_jw_client` is a meta key, not a schema field, so it is refused —
    // the map contains both the verbatim and double-prefixed keys so
    // either mistake returns a distinctive value, not an ambiguous empty.
    public function test_a_caller_supplied_raw_meta_key_is_refused(): void
    {
        $value = $this->resolveWith(
            [
                '_jw_client' => 'LEAKED — a raw meta key was used verbatim as a lookup',
                '_jw__jw_client' => 'LEAKED — a raw meta key was blindly re-prefixed',
            ],
            '_jw_client',
        );

        $this->assertSame(
            '',
            $value,
            '`_jw_client` is a meta key, not a declared field — it is not in getSchema() and must '
                . 'be refused, neither used verbatim nor re-prefixed (threat 3)',
        );
    }

    // The cached accessor returns ALL meta for the post, WP internals
    // included — the allow-list is the only thing between them and the
    // builder.
    public function test_a_wordpress_internal_meta_key_is_never_exposed(): void
    {
        $meta = [
            '_edit_lock' => '1735689600:1',
            '_wp_page_template' => 'default',
            '_jw_client' => 'Colruyt Group',
        ];

        foreach (['_edit_lock', '_wp_page_template'] as $internal) {
            $this->assertSame(
                '',
                $this->resolveWith($meta, $internal),
                "`{$internal}` is not a declared field and must never resolve to its stored value",
            );
        }
    }

    // An undeclared field resolves empty even when a matching PREFIXED key
    // exists — otherwise the bridge resolves any _jw_ key a caller names.
    public function test_a_field_absent_from_the_schema_resolves_empty(): void
    {
        $value = $this->resolveWith(
            ['_jw_internal_scratch' => 'LEAKED — arbitrary meta reached the builder'],
            'internal_scratch',
        );

        $this->assertSame(
            '',
            $value,
            'the schema is the allow-list: an undeclared field must resolve empty even when a '
                . 'prefixed value exists in storage (threat 2)',
        );
    }

    // ── Missing value -> empty string ──

    // assertSame('') rather than assertEmpty(): null, false and 0 are all
    // "empty" and all wrong for YOOtheme.
    public function test_a_declared_field_with_no_stored_value_resolves_to_the_empty_string(): void
    {
        $value = $this->resolveWith(
            ['_jw_client' => 'Colruyt Group'],
            'tagline',
        );

        $this->assertSame(
            '',
            $value,
            'a missing scalar must resolve to the empty string, never null',
        );
    }

    // Separate from the missing-key case: an isset() guard passes both,
    // a truthiness check on the whole map may not.
    public function test_a_post_with_no_meta_at_all_resolves_every_field_to_empty(): void
    {
        foreach (['client', 'tagline', 'result_body'] as $field) {
            $this->assertSame(
                '',
                $this->resolveWith([], $field),
                "`{$field}` on a post with no meta must resolve to the empty string",
            );
        }
    }

    // A deliberately-cleared field must not be "helpfully" converted to
    // null or a default.
    public function test_a_stored_empty_string_stays_an_empty_string(): void
    {
        $this->assertSame(
            '',
            $this->resolveWith(['_jw_tagline' => ''], 'tagline'),
        );
    }

    // ── Cached reads ──

    // Bounded-interaction assertion: at most two cached-accessor calls,
    // and ->never() on get_post_meta — without the second half the bound
    // could be satisfied by not using the cached accessor at all.
    public function test_resolving_every_field_of_a_case_stays_within_two_cached_reads(): void
    {
        $meta = [
            '_jw_client' => 'Colruyt Group',
            '_jw_tagline' => 'Van data naar daad',
            '_jw_what_we_did' => 'Strategie en uitvoering',
            '_jw_target_audience' => 'Retailmedewerkers',
            '_jw_impact' => [
                ['figure' => '+7 mio', 'description' => 'extra omzet'],
            ],
            '_jw_challenge_title' => 'De uitdaging',
            '_jw_challenge_body' => 'Veel silo’s',
            '_jw_result_title' => 'Het resultaat',
            '_jw_result_body' => 'Eén verhaal',
            '_jw_has_takeaways' => '1',
        ];

        foreach (self::CACHED_ACCESSORS as $accessor) {
            Functions\expect($accessor)
                ->atMost()
                ->twice()
                ->andReturn($meta);
        }

        Functions\expect('get_post_meta')->never();

        $schema = $this->caseSchema();
        $resolved = [];

        foreach (array_keys($schema) as $field) {
            $resolved[$field] = FieldResolver::resolve(42, (string) $field, $schema, self::PREFIX);
        }

        // Staying under the bound must not be achieved by resolving
        // nothing — a memo caching one entry per POST instead of per FIELD
        // would satisfy atMost(2) and only these assertions catch it.
        $this->assertSame('Colruyt Group', $resolved['client']);
        $this->assertSame('Van data naar daad', $resolved['tagline']);
        $this->assertSame('Eén verhaal', $resolved['result_body']);
        $this->assertSame(
            [['figure' => '+7 mio', 'description' => 'extra omzet']],
            $resolved['impact'],
            'the repeater resolves within the same bound as the scalars',
        );
    }

    // The grid case: YOOtheme calls the resolver once per bound field per
    // item, so fresh meta on every call multiplies by N fields.
    public function test_repeated_resolution_of_the_same_post_does_not_multiply_reads(): void
    {
        foreach (self::CACHED_ACCESSORS as $accessor) {
            Functions\expect($accessor)
                ->atMost()
                ->twice()
                ->andReturn(['_jw_client' => 'Colruyt Group']);
        }

        Functions\expect('get_post_meta')->never();

        $schema = $this->caseSchema();

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(
                'Colruyt Group',
                FieldResolver::resolve(42, 'client', $schema, self::PREFIX),
                'repeated resolution must keep returning the value, not just stay cheap',
            );
        }
    }

    // ── Repeater values ──

    // The stored shape is a LIST of associative rows under the single
    // prefixed key — ntdst-core's own repeater format.
    public function test_a_populated_repeater_resolves_to_its_list_of_rows(): void
    {
        $rows = [
            ['figure' => '+7 mio', 'description' => 'extra omzet'],
            ['figure' => '3x', 'description' => 'sneller ter plaatse'],
        ];

        $value = $this->resolveWith(['_jw_impact' => $rows], 'impact');

        $this->assertSame($rows, $value, 'a repeater must resolve to its rows, in order');
    }

    // Zero rows resolve [] never null (YOOtheme foreaches a listOf), in
    // both storage shapes: key absent entirely, and key holding [].
    public function test_a_repeater_with_zero_rows_resolves_to_an_empty_list(): void
    {
        $this->assertSame(
            [],
            $this->resolveWith([], 'impact'),
            'an ABSENT repeater must resolve to an empty list, never null',
        );

        $this->assertSame(
            [],
            $this->resolveWith(['_jw_impact' => []], 'impact'),
            'a repeater STORED as an empty array must resolve to an empty list, never null',
        );
    }

    // Any plugin/import/hand-edit can overwrite the meta row with a
    // scalar, and the return feeds YOOtheme's listOf machinery — a string
    // there is a foreach over a string, a white page in the builder.
    public function test_a_malformed_repeater_value_resolves_to_an_empty_list_without_fatal(): void
    {
        $corrupt = [
            'a plain string where rows were expected',
            '',
            0,
            false,
            'a:1:{i:0;s:5:"oops";}',
        ];

        foreach ($corrupt as $index => $stored) {
            $this->assertSame(
                [],
                $this->resolveWith(['_jw_impact' => $stored], 'impact'),
                "corrupt repeater shape #{$index} must resolve to an empty list, never fatal",
            );
        }
    }

    // One level down: an array whose ROWS are not arrays satisfies a naive
    // is_array($value) guard and only fails inside YOOtheme.
    public function test_repeater_rows_that_are_not_arrays_are_dropped_not_returned(): void
    {
        $value = $this->resolveWith(
            [
                '_jw_impact' => [
                    ['figure' => '+7 mio', 'description' => 'extra omzet'],
                    'not-a-row',
                    42,
                    null,
                ],
            ],
            'impact',
        );

        $this->assertSame(
            [['figure' => '+7 mio', 'description' => 'extra omzet']],
            $value,
            'non-array rows must be dropped; the valid row still resolves (one bad row must not '
                . 'poison the repeater, mirroring FR-6 on the value side)',
        );
    }

    // A corrupt repeater must not poison sibling fields.
    public function test_a_corrupt_repeater_does_not_break_sibling_scalar_fields(): void
    {
        $meta = [
            '_jw_impact' => 'corrupt',
            '_jw_client' => 'Colruyt Group',
        ];

        $this->assertSame([], $this->resolveWith($meta, 'impact'));
        $this->assertSame('Colruyt Group', $this->resolveWith($meta, 'client'));
    }
}
