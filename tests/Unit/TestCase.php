<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey;
use josworld\services\yootheme\FieldResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for the isolated unit tier.
 *
 * Wires Brain Monkey around every test so WordPress functions can be
 * stubbed/expected without loading WordPress. MockeryPHPUnitIntegration
 * converts Mockery expectations (Functions\expect) into PHPUnit
 * assertions and closes Mockery per test.
 */
abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        // FieldResolver (T05) memoizes cached post meta per-post for the
        // life of a request (FR-8/SC-2 — see its class docblock). With no
        // PHPUnit process isolation on this tier, that static memo would
        // otherwise persist across test methods/classes in the same run —
        // documented as a known, real design decision by FieldResolverTest's
        // class docblock (design note (c)), which authorises exactly this
        // invalidation point rather than editing the contract test itself.
        // Guarded by class_exists so this base class carries no hard
        // dependency on the theme for every OTHER unit test.
        if (class_exists(FieldResolver::class, false)) {
            FieldResolver::flush();
        }

        Monkey\tearDown();
        parent::tearDown();
    }
}
