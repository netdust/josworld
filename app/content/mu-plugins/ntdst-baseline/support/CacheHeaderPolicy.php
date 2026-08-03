<?php

declare(strict_types=1);

namespace ntdst\baseline\support;

defined('ABSPATH') || exit;

/**
 * Cache response header assembly (threat-model M5, plan.md lines 176-182;
 * §CacheHeadersService, plan.md lines 698-716).
 *
 * Pure static computation: header-name => value map derived ONLY from the
 * request-context booleans/timestamp and typed config — no direct WP calls,
 * no I/O.
 *
 * Precedence order (a request-context state machine — evaluated in this
 * order, first match wins, never combined):
 *   1. `logged_in === true` => no-cache ALWAYS (M5, THE denial path). This
 *      check MUST run first and unconditionally short-circuit — no later
 *      branch may ever see a logged-in ctx, regardless of any other ctx
 *      flag or config permutation.
 *   2. `is_404` / `is_search` / `is_feed` / `is_robots` / `is_sitemap` =>
 *      no-cache class headers, no ETag.
 *
 *      `is_robots` / `is_sitemap` (FIX ROUND, FINDING I-1, Important):
 *      widened here rather than left to fall through to step 3 because
 *      `/robots.txt` and `/wp-sitemap.xml` both reflect MUTABLE site state
 *      (`blog_public`, published-content set) that a stale edge-cached copy
 *      actively MISREPRESENTS, not merely goes stale on. Concretely: this
 *      cache layer sits in front of `SeoService`'s `blog_public` guard
 *      (SeoService.php:232), which correctly stops emitting permissive
 *      robots rules the instant a site flips `blog_public` 1 -> 0 (the
 *      staging-protection mitigation, M7) — but WITHOUT this branch, an
 *      edge/reverse-proxy cache honoring our own `public, max-age=...`
 *      would keep serving the PRE-FLIP permissive robots.txt body for up to
 *      an hour (or the sitemap for up to six) past the flip. C4 (SeoService)
 *      and C5 (this cache layer) were each individually correct; the gap
 *      only existed at their boundary, where C5 cached C4's output as if it
 *      were immutable. DO NOT "optimize" these back into the public branch
 *      — that silently reopens this gap. `is_sitemap` covers both the
 *      index AND every sub-type/stylesheet URL (both set one of the
 *      `sitemap` / `sitemap-stylesheet` query vars — see
 *      CacheHeadersService::computeContext()).
 *   3. Otherwise public, with a max-age scaled by content type.
 *   4. ETag emitted only on the public path, when singular + config allows
 *      it + a modified timestamp exists.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class CacheHeaderPolicy
{
    /**
     * @param array<string, mixed> $ctx
     * @param array<string, mixed> $config
     * @return array<string, string>
     */
    public static function compute(array $ctx, array $config): array
    {
        // 1. M5 denial: logged-in ALWAYS wins, unconditionally, before any
        // other ctx/config value is even inspected.
        if (($ctx['logged_in'] ?? false) === true) {
            return [
                'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
            ];
        }

        // 2. Dynamic/error-class responses: never cached, never ETagged.
        // is_robots/is_sitemap (FINDING I-1): these reflect mutable site
        // state (blog_public, published content) a stale edge copy would
        // actively misrepresent — see class docblock for the M7 interaction.
        if (
            ($ctx['is_404'] ?? false) === true
            || ($ctx['is_search'] ?? false) === true
            || ($ctx['is_feed'] ?? false) === true
            || ($ctx['is_robots'] ?? false) === true
            || ($ctx['is_sitemap'] ?? false) === true
        ) {
            return [
                'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
            ];
        }

        // 3. Public, with a max-age scaled per content type.
        $baseMaxAge = (int) ($config['max_age'] ?? 3600);
        $isSingular = ($ctx['is_singular'] ?? false) === true;
        $isArchiveOrFront = ($ctx['is_archive'] ?? false) === true || ($ctx['is_front'] ?? false) === true;

        if ($isSingular) {
            $maxAge = $baseMaxAge * (int) ($config['singular_factor'] ?? 24);
        } elseif ($isArchiveOrFront) {
            $maxAge = $baseMaxAge * (int) ($config['archive_factor'] ?? 6);
        } else {
            $maxAge = $baseMaxAge;
        }

        $headers = [
            'Cache-Control' => 'public, max-age=' . $maxAge,
            'Expires' => gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT',
        ];

        // 4. ETag: only on the public path, only for a singular resource,
        // only when config allows it, and only derived from the modified
        // timestamp — never from user data or session state.
        //
        // Emitted WEAK (bug-fix round, DEFECT 2 of
        // .superpowers/sdd/tasks/etag-diagnosis.md): this value is derived
        // from post_modified_time, a property of the SEMANTIC content, not
        // the exact byte stream, so it was only ever weak-equivalent in
        // truth (RFC 9110 §8.8.1) — a strong tag made a false byte-identity
        // promise. Emitting weak ourselves also means our tag and the
        // gzip-weakened tag most reverse proxies produce become the SAME
        // string in transit (nginx only adds W/ if not already present),
        // removing one source of in-transit divergence.
        $modified = $ctx['modified'] ?? null;
        if ($isSingular && ($config['etag'] ?? false) === true && $modified !== null) {
            $headers['ETag'] = 'W/"' . md5((string) $modified) . '"';
        }

        return $headers;
    }

    /**
     * RFC 9110 §8.8.3.2 weak comparison for If-None-Match (bug-fix round,
     * DEFECT 2 of .superpowers/sdd/tasks/etag-diagnosis.md).
     *
     * `$etag` is our own served validator, e.g. `W/"abc123"` or `"abc123"`.
     * `$ifNoneMatch` is the raw (caller-must-already-have-wp_unslash()'d)
     * header value, which may be a comma-separated list of entity-tags, or
     * the literal `*` (matches unconditionally, RFC 9110 §13.1.2).
     *
     * Weak comparison: two validators match if their OPAQUE TAG (the string
     * inside the quotes, stripping any leading `W/`) is identical — the
     * `W/` prefix itself is never part of the comparison, regardless of
     * which side (or both, or neither) carries it.
     */
    public static function matches(string $etag, string $ifNoneMatch): bool
    {
        $ifNoneMatch = trim($ifNoneMatch);

        if ($ifNoneMatch === '') {
            return false;
        }

        if ($ifNoneMatch === '*') {
            return true;
        }

        $ourTag = self::opaqueTag($etag);

        foreach (self::splitList($ifNoneMatch) as $candidate) {
            if (hash_equals($ourTag, self::opaqueTag($candidate))) {
                return true;
            }
        }

        return false;
    }

    /** Strips an optional leading `W/` and the surrounding quotes. */
    private static function opaqueTag(string $tag): string
    {
        $tag = trim($tag);

        if (str_starts_with($tag, 'W/')) {
            $tag = substr($tag, 2);
        }

        return trim($tag, '"');
    }

    /**
     * Splits a comma-separated entity-tag list, respecting that commas
     * never appear inside a legal quoted opaque-tag (RFC 9110 defines
     * etagc as excluding both '"' and control chars), so a naive comma
     * split is spec-safe here.
     *
     * @return array<int, string>
     */
    private static function splitList(string $header): array
    {
        return array_map('trim', explode(',', $header));
    }
}
