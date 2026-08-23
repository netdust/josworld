<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use ntdst\baseline\support\CacheHeaderPolicy;
use ntdst\baseline\support\ClientIp;

// ntdst-baseline is loaded by WP at boot, not Composer PSR-4, so this
// no-WordPress tier requires the pure support classes directly.
//
// ntdst-baseline v2.0.0 dropped its own support/Cidr.php: ClientIp is now a
// thin adapter that delegates to ntdst-core's canonical NTDST_ClientIp,
// which in turn needs core's NTDST_Cidr for its CIDR trust matching. Both
// must load from ntdst-core before baseline's ClientIp.php, or the
// class_exists('NTDST_ClientIp') check inside it fails closed and the
// magic-quoted-XFF assertion below resolves to REMOTE_ADDR instead of the
// forwarded address.
require_once dirname(__DIR__, 2)
    . '/app/content/mu-plugins/ntdst-core/support/Cidr.php';
require_once dirname(__DIR__, 2)
    . '/app/content/mu-plugins/ntdst-core/support/ClientIp.php';
require_once dirname(__DIR__, 2)
    . '/app/content/mu-plugins/ntdst-baseline/support/ClientIp.php';
require_once dirname(__DIR__, 2)
    . '/app/content/mu-plugins/ntdst-baseline/support/CacheHeaderPolicy.php';

/**
 * Pins two `$_SERVER` input-handling properties that hold only by accident
 * in the current code: a magic-quoted $_SERVER must still resolve the
 * forwarded IP, and a percent-encoded ETag must survive the input
 * pipeline. Nothing else fails when the accident stops being true.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */
final class BaselineInputHandlingTest extends TestCase
{
    // IPv6 is the case that bites: addslashes() leaves an IPv4 dotted
    // quad untouched, so an IPv4-only test would pass vacuously.
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

    // Denial path: the magic-quotes handling must not weaken the
    // trusted-proxy gate — an untrusted remote's X-Forwarded-For is ignored.
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

    // sanitize_text_field() strips %xx sequences; this pins the RFC 9110
    // entity-tag filter that replaced it.
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

    // Guard: a filter that passes everything through would satisfy the
    // test above while removing all protection — illegal bytes must drop.
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
