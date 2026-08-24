<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use ntdst\baseline\services\SchemaService;
use ntdst\baseline\services\SeoService;
use WP_UnitTestCase;

/**
 * Cross-file state guard for BaselineBootTest: its tearDown must evict (rebind)
 * the evicted singletons, never re-warm them — a re-warmed instance's
 * listeners are wiped by parent::tearDown()'s _restore_hooks(), leaving
 * the container holding services wired to nothing for every later file.
 * This file resolves each service as real code would and asserts its
 * wp_head listener is present.
 *
 * The `ZZ` prefix is load-bearing: PHPUnit walks the directory
 * alphabetically, and sorting before BaselineBootTest turns this into a
 * tautology against pristine boot state.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class ZZBaselineHookLeakGuardTest extends WP_UnitTestCase
{
    /**
     * @return array<string, array{class-string, string}>
     */
    public function baselineHookedServiceProvider(): array
    {
        return [
            'SeoService on wp_head' => [SeoService::class, 'wp_head'],
            'SchemaService on wp_head' => [SchemaService::class, 'wp_head'],
        ];
    }

    /**
     * @dataProvider baselineHookedServiceProvider
     *
     * @param class-string $serviceId
     */
    public function test_a_container_held_baseline_service_stays_wired(
        string $serviceId,
        string $hook,
    ): void {
        $instance = \ntdst_get($serviceId);

        $this->assertTrue(
            $this->instanceIsHookedOn($instance, $hook),
            sprintf(
                'the container-held %s instance must still be registered on %s. A failure here means a '
                . 'PRECEDING test file (BaselineBootTest) left the container holding an instance whose '
                . 'listeners were wiped by _restore_hooks() — see this class docblock, finding I-1.',
                $serviceId,
                $hook,
            ),
        );
    }

    private function instanceIsHookedOn(object $instance, string $hook): bool
    {
        global $wp_filter;

        if (!isset($wp_filter[$hook])) {
            return false;
        }

        foreach ($wp_filter[$hook]->callbacks as $callbacks) {
            foreach ($callbacks as $registered) {
                $callback = $registered['function'];
                if (is_array($callback) && ($callback[0] ?? null) === $instance) {
                    return true;
                }
            }
        }

        return false;
    }
}
