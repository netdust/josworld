<?php

declare(strict_types=1);

namespace ntdst\baseline\support;

defined('ABSPATH') || exit;

/**
 * Security response header assembly (threat-model M9/M10, plan.md
 * lines 200-206; interface spec lines 508-510).
 *
 * Pure static builder: header-name => value map derived ONLY from typed
 * config and the isSsl flag — no request data, no hardcoded hosts.
 *
 * Deliberately does NOT emit the deprecated XSS-filter response header
 * (ineffective in modern browsers, and could itself introduce an XSS
 * vector in older IE — plan.md lines 202-203). Do not add that header
 * back, including in comments; a source-level regression test greps the
 * whole plugin tree for its literal name.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class SecurityHeaderPolicy
{
    /**
     * @param array<string, mixed> $config
     * @return array<string, string>
     */
    public static function build(array $config, bool $isSsl): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        $csp = (array) ($config['csp'] ?? []);
        if ($csp !== []) {
            $headers['Content-Security-Policy'] = self::assembleCsp($csp);
        }

        $hsts = (array) ($config['hsts'] ?? []);
        if (($hsts['enabled'] ?? false) === true && $isSsl) {
            $headers['Strict-Transport-Security'] = self::assembleHsts($hsts);
        }

        return $headers;
    }

    /**
     * @param array<string, array<int, string>> $csp
     */
    private static function assembleCsp(array $csp): string
    {
        $directives = [];
        foreach ($csp as $directive => $sources) {
            $directive = self::stripHeaderControlChars((string) $directive);
            $sources = array_map(
                static fn($source): string => self::stripHeaderControlChars((string) $source),
                (array) $sources,
            );

            $directives[] = $directive . ' ' . implode(' ', $sources);
        }

        return implode('; ', $directives);
    }

    /**
     * @param array<string, mixed> $hsts
     */
    private static function assembleHsts(array $hsts): string
    {
        $maxAge = (int) ($hsts['max_age'] ?? 31536000);
        $value = 'max-age=' . $maxAge;

        if (($hsts['include_subdomains'] ?? false) === true) {
            $value .= '; includeSubDomains';
        }

        // `preload` is deliberately never emitted — no config knob maps to
        // it (threat-model M10: preload requires a manual submission to
        // the HSTS preload list, never an automatic config flag).
        return $value;
    }

    /**
     * Strips CR, LF and NUL from a header directive name or source value
     * before it is concatenated into an assembled header string.
     *
     * Defense-in-depth (this method's inputs are typed config, not raw
     * request data): PHP's own `header()` already rejects embedded CR/LF
     * in the string it is given, so this is inert against today's actual
     * call sites — but assembly happens here, one layer away from where
     * `header()` is finally called (`SecurityService::sendSecurityHeaders()`),
     * so a config value or a future call site that doesn't go through
     * `header()` directly (logging, header preview in wp-admin, a CDN
     * API call) would not get PHP's own protection. Stripping at assembly
     * time keeps the guarantee local to this policy regardless of how the
     * caller eventually emits the string.
     */
    private static function stripHeaderControlChars(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }
}
