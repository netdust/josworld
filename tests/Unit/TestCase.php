<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey;
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
        Monkey\tearDown();
        parent::tearDown();
    }
}
