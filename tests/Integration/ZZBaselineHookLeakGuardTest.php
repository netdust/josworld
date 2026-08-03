<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use ntdst\baseline\services\SchemaService;
use ntdst\baseline\services\SeoService;
use WP_UnitTestCase;

/**
 * Integration tier: the CROSS-FILE STATE GUARD for BaselineBootTest.
 *
 * WHY THIS FILE EXISTS (Cluster A review, finding I-1 — a real defect this
 * test reproduced): BaselineBootTest must evict two container singletons
 * (SeoService, SchemaService) via `forget()` to make its denial-path
 * assertion discriminating. How it CLEANS UP afterwards is the trap, and it
 * is invisible from inside that file — a green BaselineBootTest run says
 * nothing about the state it leaves behind.
 *
 * The original cleanup re-warmed both services with `ntdst_get()`. That is
 * wrong because of ORDERING: a child `tearDown()` body runs BEFORE
 * `parent::tearDown()`, and the parent's `_restore_hooks()`
 * (wp-phpunit/includes/abstract-testcase.php:202) wholesale restores
 * `$wp_filter` from a pre-test snapshot. So the re-warm constructed a fresh
 * instance and registered its listeners, and then `_restore_hooks()`
 * deleted exactly those listeners — leaving the CONTAINER holding a
 * SeoService/SchemaService that is wired to NOTHING, permanently, for every
 * later test file in the process.
 *
 * That failure mode is silent: the suite stayed green only because no other
 * test happened to resolve these services from the container and assert on
 * their hooks. The first test that did would fail mysteriously, and ONLY
 * when run after BaselineBootTest — the hardest bug class to diagnose.
 *
 * The fix is for BaselineBootTest's tearDown to `forget()` rather than
 * re-warm, leaving the slot EMPTY so the next resolution constructs fresh
 * against a live hook table. This file is the mechanical proof that the fix
 * holds: it resolves each service from the container the same way real code
 * would, and asserts the resolved instance's listener is actually present
 * on `wp_head`.
 *
 * NAMING: the `ZZ` prefix is load-bearing, not decoration. PHPUnit walks
 * the suite directory alphabetically, so this file must sort AFTER
 * `BaselineBootTest.php` to observe the state that file leaves. Renaming it
 * to sort earlier silently turns it into a tautology — it would pass
 * against the pristine boot state and prove nothing.
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
