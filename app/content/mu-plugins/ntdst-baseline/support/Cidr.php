<?php

declare(strict_types=1);

namespace ntdst\baseline\support;

defined('ABSPATH') || exit;

/**
 * IPv4/IPv6-safe CIDR membership check (threat-model M2, plan.md
 * lines 157-162).
 *
 * Binary comparison via inet_pton() — never string/regex matching on the
 * textual IP form. Fail-closed: any malformed $ip or $range, any
 * cross-family comparison, or any out-of-range mask width returns false.
 * $range accepts either a bare IP (exact match) or CIDR notation.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class Cidr
{
    /**
     * Fail-closed by contract: any malformed $ip or $range returns false and
     * this method never throws. (Expressed in prose rather than a
     * `@throws never` tag — PHPStan reads that tag as a declared-but-unthrown
     * exception type and flags it as `throws.unusedType`.)
     */
    public static function contains(string $ip, string $range): bool
    {
        if ($ip === '' || $range === '') {
            return false;
        }

        $ipBinary = inet_pton($ip);

        if ($ipBinary === false) {
            return false;
        }

        if (!str_contains($range, '/')) {
            // Bare IP: exact match only, still via binary comparison so
            // e.g. leading-zero or alternate textual forms don't fool it.
            $rangeBinary = inet_pton($range);

            return $rangeBinary !== false && $rangeBinary === $ipBinary;
        }

        [$rangeIp, $prefixLength] = explode('/', $range, 2);

        if (!ctype_digit($prefixLength)) {
            return false;
        }

        $prefixLength = (int) $prefixLength;
        $rangeBinary = inet_pton($rangeIp);

        if ($rangeBinary === false) {
            return false;
        }

        // Cross-family guard: inet_pton() yields 4 bytes for IPv4, 16 for
        // IPv6. Reject any comparison between different address families
        // (e.g. an IPv4-mapped IPv6 address against a plain IPv4 range) —
        // no implicit family coercion, ever.
        if (strlen($ipBinary) !== strlen($rangeBinary)) {
            return false;
        }

        $maxPrefixLength = strlen($ipBinary) * 8;

        if ($prefixLength < 0 || $prefixLength > $maxPrefixLength) {
            return false;
        }

        return self::binaryPrefixMatches($ipBinary, $rangeBinary, $prefixLength);
    }

    private static function binaryPrefixMatches(string $ipBinary, string $rangeBinary, int $prefixLength): bool
    {
        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($rangeBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits) & 0xFF;
        $ipByte = ord($ipBinary[$fullBytes]);
        $rangeByte = ord($rangeBinary[$fullBytes]);

        return ($ipByte & $mask) === ($rangeByte & $mask);
    }
}
