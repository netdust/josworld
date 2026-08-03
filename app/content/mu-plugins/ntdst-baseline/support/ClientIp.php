<?php

declare(strict_types=1);

namespace ntdst\baseline\support;

defined('ABSPATH') || exit;

/**
 * Trusted-proxy-gated client IP resolution (threat-model M1, plan.md
 * lines 150-156).
 *
 * X-Forwarded-For is consulted ONLY when REMOTE_ADDR matches a
 * trustedProxies entry (exact IP or CIDR, via Cidr::contains()). This is
 * the fail-closed denial path: an untrusted REMOTE_ADDR with a spoofed
 * XFF header must never leak the spoofed value into the resolved
 * identity — the caller always gets REMOTE_ADDR instead.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class ClientIp
{
    /**
     * @param array<string, mixed> $server
     * @param array<int, string> $trustedProxies
     */
    public static function resolve(array $server, array $trustedProxies): string
    {
        $remoteAddr = $server['REMOTE_ADDR'] ?? null;

        if (!is_string($remoteAddr) || $remoteAddr === '' || inet_pton($remoteAddr) === false) {
            return '';
        }

        if (!self::isTrustedProxy($remoteAddr, $trustedProxies)) {
            // Denial path (threat-model M1): untrusted remote, XFF fully
            // ignored regardless of what it claims.
            return $remoteAddr;
        }

        $forwardedFor = $server['HTTP_X_FORWARDED_FOR'] ?? null;

        if (!is_string($forwardedFor) || $forwardedFor === '') {
            return $remoteAddr;
        }

        foreach (explode(',', $forwardedFor) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate !== '' && inet_pton($candidate) !== false) {
                return $candidate;
            }
        }

        return $remoteAddr;
    }

    /**
     * @param array<int, string> $trustedProxies
     */
    private static function isTrustedProxy(string $remoteAddr, array $trustedProxies): bool
    {
        foreach ($trustedProxies as $proxy) {
            if (Cidr::contains($remoteAddr, $proxy)) {
                return true;
            }
        }

        return false;
    }
}
