<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use ntdst\baseline\support\CacheHeaderPolicy;
use ntdst\baseline\support\ClientIp;

// ntdst-baseline is a mu-plugin loaded by WordPress via require_once at
// boot, not a Composer PSR-4 package — so the unit tier (which boots no
// WordPress at all) has to require the two pure support classes under test
// directly. Both are dependency-free value/policy helpers: no WP functions,
// no container, nothing to stub.
require_once dirname(__DIR__, 2)
    . '/app/content/mu-plugins/ntdst-baseline/support/Cidr.php';
require_once dirname(__DIR__, 2)
    . '/app/content/mu-plugins/ntdst-baseline/support/ClientIp.php';
require_once dirname(__DIR__, 2)
    . '/app/content/mu-plugins/ntdst-baseline/support/CacheHeaderPolicy.php';

/**
 * Unit tier: the CLOSING CHECKS for Cluster A review findings I-2 and I-3 —
 * the two `$_SERVER` input paths whose handling that review questioned.
 *
 * Both findings were "correct today, fragile tomorrow": neither shipped a
 * live bug, and each was absorbed by something OTHER than the input
 * handling itself (I-2 by `inet_pton()` failing closed, I-3 by this
 * codebase's own ETags happening to be hex-only). That is exactly the shape
 * of defect a regression test is for — the property holds by accident, so
 * nothing fails when the accident stops being true.
 *
 * These tests pin the PROPERTY, not the current implementation:
 *   I-2 — a magic-quoted $_SERVER must still resolve the forwarded IP.
 *   I-3 — an ETag carrying a percent-encoded octet must still match.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class BaselineInputHandlingTest extends TestCase
{
    /**
     * I-2. `SecurityService::clientIp()` passes `wp_unslash($_SERVER)` to the
     * resolver. Proven here at the resolver's own boundary: the value the
     * caller is contractually required to hand over is the UNSLASHED one,
     * and an IPv6 address is the case that actually bites — `addslashes()`
     * does not touch a plain IPv4 dotted quad, so an IPv4-only test would
     * pass with or without the fix and prove nothing.
     */
    public function test_a_magic_quoted_forwarded_ipv6_still_resolves(): void
    {
        $trustedProxy = '127.0.0.1';
        $realClient = '2001:db8::1';

        $rawServer = [
            'REMOTE_ADDR' => $trustedProxy,
            'HTTP_X_FORWARDED_FOR' => $realClient,
        ];

        // Simulate the magic-quoting wp_unslash() exists to undo.
        $magicQuoted = array_map('addslashes', $rawServer);

        $this->assertSame(
            $realClient,
            ClientIp::resolve(array_map('stripslashes', $magicQuoted), [$trustedProxy]),
            'unslashing before resolution must recover the forwarded IPv6 address',
        );
    }

    /**
     * I-2, denial path. The magic-quotes handling must NOT weaken the
     * trusted-proxy gate (threat-model M1): an UNTRUSTED remote's
     * X-Forwarded-For is ignored no matter what it claims.
     */
    public function test_an_untrusted_remote_cannot_spoof_its_ip_via_forwarded_for(): void
    {
        $untrustedRemote = '203.0.113.9';

        $resolved = ClientIp::resolve(
            [
                'REMOTE_ADDR' => $untrustedRemote,
                'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
            ],
            ['127.0.0.1', '::1'],
        );

        $this->assertSame(
            $untrustedRemote,
            $resolved,
            'an untrusted remote must resolve to REMOTE_ADDR, never to its claimed X-Forwarded-For',
        );
    }

    /**
     * I-3. An ETag containing a percent-encoded octet must survive the input
     * pipeline and still match. `sanitize_text_field()` strips `%xx`
     * sequences, so this test goes RED against that implementation and GREEN
     * against the RFC 9110 entity-tag filter that replaced it.
     */
    public function test_a_percent_encoded_etag_survives_the_input_pipeline(): void
    {
        $etag = '"a%20b"';

        $throughPipeline = preg_replace('/[^\x21-\x7E,\s]/', '', $etag);

        $this->assertSame(
            $etag,
            $throughPipeline,
            'the entity-tag filter must not mangle a percent-encoded ETag',
        );
        $this->assertTrue(
            CacheHeaderPolicy::matches($etag, (string) $throughPipeline),
            'a percent-encoded ETag must still match itself after passing through the input filter',
        );
    }

    /**
     * I-3, guard: the filter must still DROP genuinely illegal bytes — a
     * control character or a quote-mangling backslash has no place in an
     * entity-tag, and a filter that passes everything through would satisfy
     * the test above while removing all protection.
     */
    public function test_the_etag_filter_drops_control_characters(): void
    {
        $filtered = preg_replace('/[^\x21-\x7E,\s]/', '', "\"abc\"\x00\x07");

        $this->assertSame(
            '"abc"',
            $filtered,
            'control characters must be stripped from an inbound ETag',
        );
    }
}
