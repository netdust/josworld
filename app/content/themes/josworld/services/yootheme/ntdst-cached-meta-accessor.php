<?php

/**
 * T05's global-namespace cached-meta seam — split into its OWN file,
 * deliberately, and `require_once`d LAZILY (from inside
 * `FieldResolver::resolve()`, on first use) rather than eagerly at
 * `FieldResolver.php`'s own load time.
 *
 * WHY A SEPARATE, LAZILY-LOADED FILE — verified empirically against this
 * project's test suite, not assumed:
 *
 * Brain Monkey's function interception (via Patchwork) can only redefine a
 * function whose DEFINING FILE was included through Patchwork's own stream
 * wrapper — which Patchwork installs when it bootstraps, which happens
 * inside `Brain\Monkey\setUp()` (`patchwork-loader.php`), which runs inside
 * `TestCase::setUp()`, i.e. PER TEST, AFTER PHPUnit has already parsed and
 * loaded every test file's top-level code (including `FieldResolverTest`'s
 * own `require_once` of `FieldResolver.php`, evaluated once at test-suite
 * discovery — before any test's `setUp()` runs).
 *
 * A function declared directly in `FieldResolver.php` is therefore always
 * loaded BEFORE Patchwork bootstraps, and any attempt to stub it throws
 * `Patchwork\Exceptions\DefinedTooEarly` ("The file that defines
 * ntdst_get_post_meta_from_cache() was included earlier than Patchwork") —
 * a `function_exists()` guard does not change this; the guard only prevents
 * a double-declaration fatal, and this function's DEFINING file has already
 * been fixed at the moment `FieldResolver.php` is required, regardless of
 * the guard.
 *
 * Splitting the declaration into this file and requiring it from inside
 * `resolve()` (called only once a test is RUNNING, i.e. after `setUp()`
 * has bootstrapped Patchwork) means the FIRST time this file is included,
 * Patchwork's wrapper is already active and preprocesses it — making the
 * function interceptable exactly like a genuinely-undefined function such
 * as `esc_html` is in BrainMonkeyFunctionsTest. Verified: with this split,
 * all 16 of FieldResolverTest's tests pass, including the two SC-2
 * read-count tests that stub/expect this exact function.
 *
 * The body is WordPress calls only — calling them directly from
 * `FieldResolver.php` would drag WordPress and `$wpdb` into the unit tier.
 * This forwarding function keeps that coupling in the one place a resolver
 * ever touches it. Integration cover for the REAL body:
 * tests/Integration/CachedMetaAccessorTest.php — the pre-5.0 body forwarded
 * to a core method that never existed in any version, and only the unit
 * tier's stub of this global kept that green.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

if (! function_exists('ntdst_get_post_meta_from_cache')) {
    /**
     * The post's full meta map: RAW PREFIXED keys (`_jw_client`), scalar
     * values as stored, array values (repeaters) unserialized.
     *
     * WordPress's own cached read, on purpose. ntdst-core 5.0's core-trim
     * removed the bulk raw-meta read (static getPostMeta(), FR-4) and the
     * model's getMeta() returns FORMATTED, UNPREFIXED fields — the wrong
     * shape for FieldResolver, which composes `$prefix . $field` keys
     * itself as its injection guard. get_post_meta($id) with no key rides
     * the meta cache (one query per post, memoized further by
     * FieldResolver::$metaMemo) but skips unserialization in that form,
     * hence the maybe_unserialize() per value.
     *
     * @return array<string, mixed>
     */
    function ntdst_get_post_meta_from_cache(int $postId): array
    {
        $meta = [];

        foreach (get_post_meta($postId) as $key => $values) {
            $meta[$key] = maybe_unserialize($values[0] ?? '');
        }

        return $meta;
    }
}
