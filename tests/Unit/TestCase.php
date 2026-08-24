<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey;
use josworld\services\yootheme\FieldResolver;
use josworld\services\yootheme\YOOthemeSourcesService;
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
        // FieldResolver memoizes post meta statically; with no process
        // isolation the memo would leak across tests. class_exists keeps
        // this base free of a hard theme dependency.
        if (class_exists(FieldResolver::class, false)) {
            FieldResolver::flush();
        }

        // YOOthemeSourcesService holds its vetted registry statically
        // (resolve_jw_field() is standalone, no $this). Without this flush
        // the registry leaks across tests — boundary tests passed alone
        // and failed in-suite until it was wired.
        if (class_exists(YOOthemeSourcesService::class, false)) {
            YOOthemeSourcesService::flushRegistry();
        }

        Monkey\tearDown();
        parent::tearDown();
    }
}
