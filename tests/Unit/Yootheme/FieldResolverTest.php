<?php

/**
 * T05 CONTRACT TEST — authored by the independent test-author BEFORE any
 * implementation exists, and IMMUTABLE to the implementer: green it without
 * weakening it. A dispute is an escalation, not an edit.
 *
 * WHAT THIS PINS (from tasks.md T05's acceptance criteria, FR-3/FR-8, SC-2,
 * threat 2 and threat 3, and acceptance flows 1 and 2 — NOT from any
 * implementation, which did not exist when this was written):
 *   1. PREFIX COMPOSITION. Field `client` reads meta key `_jw_client` and NOT
 *      `client` (FR-3, threat 3).
 *   2. NO CALLER-SUPPLIED RAW META KEY. A caller passing `_jw_client`, or any
 *      other literal meta key, cannot have it used verbatim as a lookup
 *      (threat 3).
 *   3. MISSING VALUE -> EMPTY STRING, never null, never a notice (flow 1's
 *      "empty" edge).
 *   4. CACHED READS. Resolution routes through ntdst-core's cached accessor,
 *      at most twice per post for a fully-populated case, and NEVER through
 *      per-field `get_post_meta()` (FR-8, SC-2).
 *   5. REPEATER VALUES. Zero rows -> empty list, never null; a stored value
 *      that is not an array -> empty list, no fatal (flow 2's "empty" and
 *      "mid-flow failure" edges, explicitly deferred to T05 by T06's author).
 *   6. SCHEMA IS THE ALLOW-LIST. A field the model's schema does not declare
 *      resolves empty and reads no meta key for it (threat 2).
 *
 * ─────────────────────────────────────────────────────────────────────────
 * THE TRAP THIS FILE EXISTS TO MAKE UNSHIPPABLE.
 *
 * The working reference (ludoluykx `YOOthemeDynamicContentService.php`) reads
 * BARE field names — `get_post_meta($item->ID, $field, true)` at its :507 —
 * and its `attach_post_meta()` hydrator SKIPS every key beginning with `_`
 * (`if (strpos($key, '_') === 0) continue;` at its :436, independently
 * confirmed in the framework's own golden path
 * `ntdst-patterns/golden-paths/yootheme-integration.md`, which documents that
 * skip as intended behaviour: "drops `_`-prefixed internals").
 *
 * josworld prefixes EVERY declared field's meta key with `_jw_`
 * (`JOSWORLD_CORE_META_PREFIX`, josworld-core.php:32; all four content
 * services pass it as `meta_prefix`). A verbatim port therefore fails in the
 * worst available way: every field name registers correctly and shows up in
 * the builder's picker, and every VALUE resolves empty — because the lookup
 * asks for `client` while the data lives at `_jw_client`, and the `_`-skip
 * would drop it even if it did not. The builder looks healthy; the page
 * renders blank. That is a silent half-failure with no error anywhere, and
 * assertion 1 below is what makes it impossible to ship.
 *
 * GROUND TRUTH FOR THE PREFIXED READ IS THE FRAMEWORK ITSELF, not this test's
 * invention: `NTDST_Data_Model::formatFields()` composes
 * `$metaKey = $this->meta_prefix . $field;` (ntdst-core/api/Data.php:1481)
 * before looking `$meta[$metaKey]` up. The resolver must agree with the layer
 * that WROTE the data.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * DESIGN DECISIONS THIS TEST PINS, AND WHY — recorded here because the task
 * brief explicitly delegated the shape to the test-author.
 *
 * (a) THE SIGNATURE IS THE THREAT-3 MITIGATION. Threat 3 says resolution
 *     "always composes `getMetaPrefix() . $field` and never accepts a
 *     caller-supplied key". A signature of `resolve($post, $metaKey)` could
 *     only honour that by convention; every caller would be free to break it,
 *     and the test could only assert that today's caller happens not to. So
 *     the contract is:
 *
 *         FieldResolver::resolve(int $postId, string $field,
 *                                array $schema, string $prefix): mixed
 *
 *     `$schema` and `$prefix` come from the REGISTRATION side — T07 already
 *     holds the model and calls `getSchema()` / `getMetaPrefix()` on it — and
 *     the only caller-controlled string, `$field`, is checked against
 *     `$schema` BEFORE any key is composed. There is no parameter through
 *     which a raw meta key can enter. `test_a_caller_supplied_raw_meta_key_
 *     is_refused` proves that structurally: `_jw_client` is not a schema key,
 *     so it is refused, and neither `_jw_client` nor `_jw__jw_client` is ever
 *     looked up. This is the difference between a documented rule and an
 *     unrepresentable state.
 *
 * (b) STATIC, LIKE `SchemaMapper`, BUT FOR A DIFFERENT REASON. SchemaMapper is
 *     static because it is pure. This class is NOT pure — it reads WordPress
 *     meta, which is exactly why it is a separate class from the mapper. It is
 *     static because of T07's hard constraint: YOOtheme serialises resolver
 *     references into the builder's JSON config, so every resolver must be a
 *     standalone namespace-prefixed FUNCTION referenced by literal
 *     fully-qualified string — never a closure, never `[$this, 'method']`
 *     (golden path: anything non-serialisable = white page in the Customizer).
 *     A static method is callable from inside such a function with no
 *     instance to construct, no container lookup, and no bootstrap ordering
 *     to get wrong, and it keeps `new` out of the per-field hot path of every
 *     grid item. T07 is NOT implemented here; the point is that this shape
 *     does not block it.
 *
 * (c) HOW ≤2 READS PER POST IS ASSERTED HONESTLY AT THE UNIT TIER (SC-2). The
 *     naive implementation is one uncached `get_post_meta()` per field, which
 *     for a `case` is ~20 reads per post and, in a grid, ~20 x M. Counting
 *     "queries" is not available without a database, so the contract is
 *     asserted at the seam the unit actually controls: Brain Monkey
 *     `Functions\expect()` on ntdst-core's cached accessor, bounded with
 *     `atMost()->twice()`, PLUS `Functions\expect('get_post_meta')->never()`.
 *     The second half is what gives the first its teeth — without it an
 *     implementation could satisfy the bound by simply not using the cached
 *     accessor at all. Together they pin FR-8's actual words ("cached
 *     accessors, not per-field uncached `get_post_meta()`") rather than a
 *     proxy for them. This is a bounded-interaction assertion, used
 *     deliberately where the contract IS about interaction cost; every other
 *     test in this file asserts returned state.
 *
 *     WHAT THIS BOUND FORCES, stated plainly so it is not a surprise: a
 *     resolver that calls the cached accessor once PER `resolve()` CALL does
 *     NOT satisfy it — YOOtheme calls the resolver once per bound field per
 *     item, so ten fields is ten calls. The implementer needs a per-post
 *     memo keyed by `$postId`. Verified: a sketch without the memo fails
 *     these two tests with "at most 2 times but called 10 times", which is
 *     exactly the reference implementation's N-reads-per-item bug that FR-8
 *     exists to prevent.
 *
 *     ONE WARNING, FOUND THE HARD WAY BY THE TEST-AUTHOR while confirming
 *     these tests are satisfiable: a bare `static array $memo` inside the
 *     method PERSISTS ACROSS TESTS in the same PHP process. Every test here
 *     resolves post 42, so a naive memo serves test #2 the meta map stubbed
 *     for test #1 — the tests then pass INDIVIDUALLY and fail as a suite.
 *     That is not a flaw in this contract; it is a real cache-lifetime bug
 *     the contract surfaces, and it would behave the same way in production
 *     across two requests in a long-lived process or between two posts that
 *     reuse an ID after an import. The memo must be invalidatable — the
 *     simplest honest options are a per-request static the test can reset via
 *     a public `flush()` (add it if you want; ADDING to this contract is
 *     allowed, weakening it is not), or leaning on `wp_cache_*`, which
 *     ntdst-core's accessors already do. This is called out so it reads as a
 *     known design decision rather than a surprise at green-time.
 *
 * (d) WHICH CACHED ACCESSOR. `getPostMetaFromCache()` (Data.php:1593) is the
 *     primed-cache variant and itself falls back to `getCachedPostMeta()`
 *     (:1694), so the tests accept EITHER as the routed accessor rather than
 *     pinning one — the plan names both (plan.md:72) and the choice between
 *     them is an implementation call, not a contract. What IS contract: one of
 *     them is used, at most twice, and `get_post_meta()` is not.
 *
 * (e) WHY THE ACCESSOR SEAM IS A *FUNCTION*, AND WHAT THE IMPLEMENTER MUST
 *     BUILD. GROUND-TRUTHED: ntdst-core exposes the cached meta accessors ONLY
 *     as STATIC METHODS on `NTDST_Data_Manager` (`getPostMetaFromCache()`
 *     Data.php:1593, `getCachedPostMeta()` :1694). There is no procedural
 *     `ntdst_get_*_post_meta()` helper — the only procedural helpers in that
 *     file are `ntdst_data()`, `ntdst_get_posts_fast()`,
 *     `ntdst_clear_posts_cache()`, `ntdst_invalidate_post_type()` and
 *     `ntdst_query_cache()` (:1982-2028, checked).
 *
 *     That matters because Brain Monkey intercepts FUNCTIONS, not static
 *     methods, and `NTDST_Data_Manager` is a mu-plugin class with no
 *     autoloader — requiring it at the unit tier would drag in WordPress and
 *     `$wpdb`. A resolver that calls `NTDST_Data_Manager::getPostMetaFromCache()`
 *     directly is therefore NOT unit-testable at all, and FR-8/SC-2 would have
 *     no per-task proof.
 *
 *     SO THE CONTRACT INCLUDES A ONE-LINE SEAM the implementer authors: a
 *     GLOBAL-namespace forwarding function, called from the resolver with an
 *     EXPLICIT LEADING BACKSLASH.
 *
 *         // in the theme, global namespace (its own tiny file, or a
 *         // `namespace { }` block — NOT inside josworld\services\yootheme):
 *         function ntdst_get_post_meta_from_cache(int $postId): array {
 *             return \NTDST_Data_Manager::getPostMetaFromCache($postId);
 *         }
 *
 *         // in FieldResolver::resolve():
 *         $meta = \ntdst_get_post_meta_from_cache($postId);
 *
 *     THE GLOBAL NAMESPACE AND THE LEADING `\` ARE BOTH LOAD-BEARING, and
 *     this was VERIFIED EMPIRICALLY by the test-author, not assumed. Two
 *     alternatives were tried against these tests and both FAILED:
 *       - a function declared inside `namespace josworld\services\yootheme`
 *         is already defined by the time Brain Monkey's per-test `setUp()`
 *         runs (the production file is `require_once`d at test-FILE load,
 *         once per process), so Monkey cannot redefine it and the stub is
 *         never applied — every value assertion returns the real function's
 *         result;
 *       - wrapping that declaration in `function_exists()` does not help,
 *         for the same ordering reason.
 *     A global-namespace function called as `\ntdst_...()` is interceptable
 *     by Brain Monkey exactly like `esc_html` in BrainMonkeyFunctionsTest,
 *     and with that shape 14 of these 16 tests pass against a sketch
 *     implementation — so this contract is demonstrably SATISFIABLE, not
 *     merely strict. (The other 2 are the SC-2 read-count tests, which
 *     correctly reject a resolver that re-reads meta on every call; see (c).)
 *
 *     Either name in `CACHED_ACCESSORS` is accepted — the implementer defines
 *     ONE. The assertions set an `atMost()` expectation on both, which
 *     tolerates zero calls to the unused one.
 *
 *     This is a seam, not indirection for its own sake: it is the single
 *     place the bridge touches ntdst-core's cache, and it is what makes
 *     FR-8/SC-2 provable at the unit tier at all.
 *
 * (f) SCHEMA AS ALLOW-LIST (threat 2). The golden path's reference hydrator
 *     filters meta to schema fields precisely so the bridge cannot "expose
 *     arbitrary meta fields" (its own comment). Passing `$schema` in gives
 *     this resolver the same property per-field, and it is the same array T07
 *     already has in hand for `SchemaMapper::toObjectType()` — no extra
 *     lookup, no second source of truth about what a model declares.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use Brain\Monkey\Functions;
use NtdstTests\Unit\TestCase;
use josworld\services\yootheme\FieldResolver;

// The theme has no PSR-4 autoloader — require the unit under test directly,
// the same pattern SchemaMapperTest and BaselineInputHandlingTest use.
// ABSPATH is already defined by tests/bootstrap-unit.php, so the file's
// `defined('ABSPATH') || exit;` guard is a no-op here.
require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/FieldResolver.php';

final class FieldResolverTest extends TestCase
{
    /**
     * The meta prefix every josworld model uses (JOSWORLD_CORE_META_PREFIX,
     * josworld-core.php:32). Supplied to the resolver the way T07 will supply
     * it: from the model's own `getMetaPrefix()`.
     */
    private const PREFIX = '_jw_';

    /**
     * The cached-accessor seam, as ntdst-core exposes it. The implementer
     * routes reads through ONE of these; the helpers below accept either.
     *
     * `getPostMetaFromCache` (Data.php:1593) is the primed-cache variant and
     * falls back internally to `getCachedPostMeta` (:1694) — see design note
     * (d). Both return the post's FULL meta map in ONE query, keyed by meta
     * key, `maybe_unserialize`d.
     *
     * @var list<string>
     */
    private const CACHED_ACCESSORS = [
        'ntdst_get_post_meta_from_cache',
        'ntdst_get_cached_post_meta',
    ];

    /**
     * CaseService's real field list (getFields(), CaseService.php:125+),
     * trimmed to the shape the resolver cares about. Copied from the source,
     * not invented — a `case` is the fully-populated model SC-2 names.
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
     * Stub every accepted cached accessor to return `$meta` (the post's full
     * meta map, PREFIXED keys, exactly as ntdst-core's accessors return it).
     *
     * `Functions\when()` — an unbounded stub — is used for the tests that
     * assert VALUE contracts. The read-count contract has its own helper
     * below, because bounding a call is a different assertion from stubbing
     * one.
     *
     * @param array<string, mixed> $meta
     */
    private function stubCachedMeta(array $meta): void
    {
        foreach (self::CACHED_ACCESSORS as $accessor) {
            Functions\when($accessor)->justReturn($meta);
        }

        // The per-field uncached read is the reference implementation's bug
        // (its :507). It is never the right call here — if the resolver
        // reaches for it, that is a contract violation, not a fallback. Left
        // UNSTUBBED on purpose in the value tests so a call surfaces as a
        // Brain Monkey "undefined function" failure rather than silently
        // returning null and passing.
    }

    /**
     * Read the resolver's own meta-key lookups out of the stub.
     *
     * Rather than assert on WHICH key was passed to a mock (the resolver
     * receives the whole meta map in one call and indexes it itself), the
     * stub returns a map containing ONLY the keys named here. A resolver that
     * composes the wrong key gets a miss and returns empty — so the assertion
     * on the returned VALUE is what proves the composed key, with no
     * interaction mocking. This is the state-over-interactions form of
     * assertion 1.
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

    // ─────────────────────────────────────────────────────────────────────
    // 1. PREFIX COMPOSITION — FR-3, threat 3, plan.md:64 "the #1 trap"
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Field `client` resolves from meta key `_jw_client`.
     *
     * State-over-interactions: the stubbed meta map contains ONLY the
     * prefixed key, so the only way to return this value is to have composed
     * `_jw_` . `client`.
     */
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

    /**
     * ...and it does NOT read the BARE key. THE TRAP, asserted directly.
     *
     * Here the meta map holds BOTH keys with DIFFERENT values. A resolver that
     * ports the reference verbatim (bare `$field`) returns the decoy and
     * fails loudly right here — which is the entire point of splitting this
     * task: the failure mode is otherwise silent, because in production only
     * the `_jw_` key exists and the bare read merely returns empty while the
     * builder still lists the field.
     */
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

    /**
     * The prefix is applied to EVERY declared field, not just the first — a
     * resolver that special-cases one field, or that prefixes only when the
     * bare key is missing, fails here.
     */
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

    // ─────────────────────────────────────────────────────────────────────
    // 2. DENIAL PATH — no caller-supplied raw meta key (threat 3)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * THE DENIAL PATH FOR THREAT 3. A caller that passes an ALREADY-PREFIXED
     * meta key must not have it honoured.
     *
     * `_jw_client` is a meta key, not a declared field name — `getSchema()`
     * keys are bare (`client`). So it is refused, and neither the verbatim
     * key nor the double-prefixed `_jw__jw_client` resolves to anything. The
     * meta map below deliberately contains BOTH so that either mistake
     * returns a distinctive wrong value rather than an ambiguous empty.
     *
     * This is what makes the mitigation structural: with `$schema` as the
     * allow-list, "never accepts a caller-supplied raw meta key" is not a
     * rule the caller has to remember — it is unrepresentable.
     */
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

    /**
     * The same denial for a WordPress-internal key, which is the concrete harm
     * threat 3 names: "a prefix bug could read or render an unrelated
     * `_`-prefixed internal key".
     *
     * `_edit_lock` and `_wp_page_template` are real WP internals and are
     * present in the meta map the cached accessor returns (it selects ALL
     * meta for the post — Data.php:1706 `SELECT meta_key, meta_value ... WHERE
     * post_id = %d`, with no key filter). So the resolver genuinely has them
     * in hand and the allow-list is the only thing standing between them and
     * the builder.
     */
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

    /**
     * THE ALLOW-LIST, threat 2. A field the schema does not declare resolves
     * empty even when a matching PREFIXED key exists in storage.
     *
     * This is the per-field form of the filtering the golden path's reference
     * hydrator does for exactly this reason ("prevents exposing arbitrary meta
     * fields"). Without it, the bridge would resolve any `_jw_`-prefixed key
     * a caller can name — including one deliberately never registered as a
     * builder field.
     */
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

    // ─────────────────────────────────────────────────────────────────────
    // 3. MISSING VALUE -> EMPTY STRING (flow 1, "empty" edge)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A declared field with no stored value resolves to the EMPTY STRING —
     * never null, never a notice.
     *
     * `assertSame('')` rather than `assertEmpty()`: null, false and 0 are all
     * "empty" and all wrong. A null reaching YOOtheme is the same class of
     * problem SchemaMapper's omit-don't-default rule avoids on the type side —
     * it surfaces in the builder as a bound field that renders nothing and
     * gives the editor no signal.
     */
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

    /**
     * A post with NO josworld meta at all — a case saved before the fields
     * existed, or one the editor never filled in — resolves every declared
     * field to empty, with no notice and no fatal.
     *
     * The empty-map case is separate from the missing-key case because a
     * resolver that guards with `isset($meta[$key])` passes both, while one
     * that guards with a truthiness check on the whole map may not.
     */
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

    /**
     * A stored EMPTY STRING is indistinguishable from absent, and both are the
     * empty string. Pinned so an implementation cannot "helpfully" convert a
     * deliberately-cleared field into null or into a default.
     */
    public function test_a_stored_empty_string_stays_an_empty_string(): void
    {
        $this->assertSame(
            '',
            $this->resolveWith(['_jw_tagline' => ''], 'tagline'),
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. CACHED READS — FR-8, SC-2
    // ─────────────────────────────────────────────────────────────────────

    /**
     * SC-2, ASSERTED AS A BOUND. Resolving EVERY field of a fully-populated
     * `case` issues at most TWO calls into the cached accessor, and ZERO
     * uncached `get_post_meta()` calls.
     *
     * Ten declared fields are resolved in this test. The reference
     * implementation's shape (`get_post_meta($item->ID, $field, true)` per
     * field, its :507) would be ten uncached calls here and ~20 on the real
     * schema — times M grid items. `->never()` on `get_post_meta` is the half
     * of this assertion with teeth: without it, an implementation could
     * satisfy the ≤2 bound by not using the cached accessor at all.
     *
     * `atMost()->twice()` rather than `once()`: the bound the plan and SC-2
     * state is "≤ 2 meta queries per post", and the primed-cache accessor
     * legitimately falls back to the raw-SQL one internally (Data.php:1593 ->
     * :1694). Pinning `once()` would be pinning an implementation detail
     * tighter than the contract; pinning `twice()` exactly would forbid the
     * better one-read implementation. `atMost` is the contract's own words.
     *
     * This is a deliberate bounded-INTERACTION assertion — the only one in
     * this file — used because SC-2's contract IS about interaction cost and
     * cannot be observed in returned state.
     */
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

        // FR-8's actual words: "not per-field uncached get_post_meta() calls".
        Functions\expect('get_post_meta')->never();

        $schema = $this->caseSchema();
        $resolved = [];

        foreach (array_keys($schema) as $field) {
            $resolved[$field] = FieldResolver::resolve(42, (string) $field, $schema, self::PREFIX);
        }

        // The read-count bound is enforced by the Mockery expectations above
        // (MockeryPHPUnitIntegration converts them into PHPUnit assertions).
        // These assertions cover the OTHER half, and are the reason this test
        // is not merely an interaction check: staying under the bound must not
        // be achieved by resolving nothing. A memo that caches too
        // aggressively — one entry for the whole POST rather than per FIELD —
        // would satisfy `atMost(2)` while returning `client`'s value for every
        // field, and only this assertion catches it.
        $this->assertSame('Colruyt Group', $resolved['client']);
        $this->assertSame('Van data naar daad', $resolved['tagline']);
        $this->assertSame('Eén verhaal', $resolved['result_body']);
        $this->assertSame(
            [['figure' => '+7 mio', 'description' => 'extra omzet']],
            $resolved['impact'],
            'the repeater resolves within the same bound as the scalars',
        );
    }

    /**
     * The bound holds for the SAME post across REPEATED resolution — the grid
     * case. YOOtheme calls a resolver once per bound field per item, so a
     * resolver that reads fresh meta on every call multiplies by N fields
     * even if each individual call looks cheap.
     *
     * Same field, resolved five times, still ≤2 accessor calls.
     */
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

    // ─────────────────────────────────────────────────────────────────────
    // 5. REPEATER VALUES — flow 2's two edges, deferred to T05 by T06
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A populated repeater resolves to its LIST OF ROWS, each row an
     * associative array of its sub-fields.
     *
     * Shape ground-truthed against ntdst-core's own repeater handling:
     * `sanitizeRepeater()` (Data.php:226) builds `$sanitized_rows[] =
     * $sanitized_row` — a LIST of associative rows — and `formatRepeaterField()`
     * (:259) returns the same shape on load. The cached accessor
     * `maybe_unserialize`s, so the resolver receives that array directly under
     * the single `_jw_impact` key.
     */
    public function test_a_populated_repeater_resolves_to_its_list_of_rows(): void
    {
        $rows = [
            ['figure' => '+7 mio', 'description' => 'extra omzet'],
            ['figure' => '3x', 'description' => 'sneller ter plaatse'],
        ];

        $value = $this->resolveWith(['_jw_impact' => $rows], 'impact');

        $this->assertSame($rows, $value, 'a repeater must resolve to its rows, in order');
    }

    /**
     * FLOW 2's "empty" EDGE, explicitly handed to T05 by T06's author: a
     * repeater with ZERO ROWS resolves to an EMPTY LIST, never null.
     *
     * `assertSame([])` rather than `assertEmpty()`: null and `''` are also
     * empty and are both wrong. YOOtheme iterates a `listOf` — null there is
     * a foreach over null, which in PHP 8 is a warning per grid render, and
     * SC-3 requires zero notices.
     *
     * Both storage shapes of "no rows" are covered: the key absent entirely
     * (the editor never added a row — CaseService's docblock documents
     * takeaways as "absent from storage entirely when the editor adds none"),
     * and the key present holding an empty array.
     */
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

    /**
     * FLOW 2's "mid-flow failure" EDGE, also handed to T05: a stored repeater
     * value that is NOT AN ARRAY resolves to an empty list, with NO FATAL.
     *
     * This is the untrusted-shape denial path on the value side, and it is not
     * hypothetical: `_jw_impact` is a plain meta row that any plugin, import,
     * WP-CLI call, or hand-edit can overwrite with a scalar. The resolver
     * hands its return straight to YOOtheme's `listOf` machinery, so a string
     * there is a foreach over a string — a fatal in the builder, i.e. a white
     * page.
     *
     * Five realistic corrupt shapes, including the serialized-string case
     * (an unserialize that returned the raw string) and the nested-scalar case
     * (an array whose ROWS are not arrays — which passes a naive
     * `is_array($value)` guard and only fails one level down, in YOOtheme).
     */
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

    /**
     * ...and the same denial ONE LEVEL DOWN: an array whose rows are not
     * arrays. `is_array($value)` alone is satisfied here, so this is the
     * shape a plausible-looking guard misses; it is also exactly what
     * ntdst-core's own `formatRepeaterField()` guards against (`foreach
     * ($value as $row) { if (is_array($row)) ... }`, Data.php:283-287) — the
     * resolver must agree with the layer that formats the same data.
     */
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

    /**
     * A malformed repeater must not take the REST of the model down with it —
     * FR-6's "one bad field must not poison the model", asserted on the value
     * side. The scalar sibling of a corrupt repeater still resolves.
     */
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
