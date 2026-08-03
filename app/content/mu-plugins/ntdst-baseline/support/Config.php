<?php

declare(strict_types=1);

namespace ntdst\baseline\support;

defined('ABSPATH') || exit;

/**
 * Pure, WP-free config merge for baseline module config arrays.
 *
 * Every module builds its config as:
 *   Config::merge(self::DEFAULTS, (array) apply_filters('ntdst/baseline/<module>/config', []));
 *
 * Merge semantics (FR-2):
 * - Scalar overrides win.
 * - Nested associative arrays merge recursively (key-by-key).
 * - List-valued overrides (sequential 0-indexed arrays) REPLACE the default
 *   list wholesale — never appended — so a site can shrink a list default
 *   (e.g. `preconnect_domains`, `trusted_proxies`) instead of only growing it.
 * - Override keys absent from the defaults are KEPT (forward-compat): a site
 *   may pass a key the current defaults don't declare yet, and it survives
 *   the merge unchanged.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class Config
{
    /**
     * @param array<array-key, mixed> $defaults
     * @param array<array-key, mixed> $overrides
     * @return array<array-key, mixed>
     */
    public static function merge(array $defaults, array $overrides): array
    {
        $result = $defaults;

        foreach ($overrides as $key => $value) {
            if (
                array_key_exists($key, $result)
                && is_array($result[$key])
                && is_array($value)
                && self::isAssoc($result[$key])
                && self::isAssoc($value)
            ) {
                // Both sides are associative arrays at this key: merge
                // recursively rather than replacing the whole branch.
                $result[$key] = self::merge($result[$key], $value);

                continue;
            }

            // Scalar override, list-valued override (replaces wholesale),
            // or a key the defaults don't declare (kept as-is): all three
            // cases are a direct assignment.
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * True for associative arrays (including the empty array, which is
     * treated as an empty assoc/object shape — e.g. `csp => []` merging
     * with an override list would not reach here since one side must be
     * non-empty-and-associative to trigger the recursive branch; an empty
     * array on either side falls through to direct assignment, matching
     * "list override replaces" for the common `[] -> [...]` case).
     *
     * False for lists: sequential, zero-indexed arrays.
     *
     * @param array<mixed> $array
     */
    private static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
