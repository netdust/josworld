<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

/**
 * Unit tier: pins ntdst-baseline's hook-namespace convention.
 *
 * WHY THIS EXISTS (Cluster A drift review): `SchemaService` fired 7 filters
 * under `ntdst/schema/*` while every other extension point in the plugin —
 * including `SchemaService`'s OWN config filter — used `ntdst/baseline/*`.
 * The inconsistency was inside a single file, and nothing could see it: the
 * linters have no opinion on hook names, and no test asserted the
 * convention, so the only thing standing between the plugin and a second
 * divergent namespace was whoever happened to read the file next.
 *
 * The rename was safe to do (2026-08-03, human ruling: daan has nothing
 * built on those filters yet). This test is what stops the drift coming
 * back — a new filter in a new module has to either follow the convention
 * or consciously edit this test, which is a visible act in a diff rather
 * than an oversight.
 *
 * Deliberately a SOURCE SCAN, not a behavioural test. The property is
 * "every hook name in this plugin starts with ntdst/baseline/", which is a
 * statement about the whole tree; asserting it per-filter at runtime would
 * need every code path driven, and would silently miss any filter whose
 * branch no test happens to reach. Reading the source is the honest
 * observable for a whole-tree naming rule.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class BaselineFilterNamespaceTest extends TestCase
{
    private const BASELINE_DIR = '/app/content/mu-plugins/ntdst-baseline';

    /**
     * Hook names this plugin is allowed to reference without the
     * `ntdst/baseline/` prefix, each with the reason it is exempt.
     *
     * @var array<string, string>
     */
    private const ALLOWED_FOREIGN_HOOKS = [
        // ntdst-core's own model-registration signal — owned by the
        // framework, not by baseline; baseline may only listen to it.
        'ntdst/model/registered' => 'ntdst-core framework hook, not baseline-owned',
        'ntdst/model/registering' => 'ntdst-core framework hook, not baseline-owned',
        // The Data/API layer's action vocabulary, same reasoning.
        'ntdst/api_data/' => 'ntdst-core API router vocabulary, not baseline-owned',
        'ntdst/api/' => 'ntdst-core API router vocabulary, not baseline-owned',
    ];

    public function test_every_ntdst_hook_in_the_plugin_uses_the_baseline_namespace(): void
    {
        $offenders = [];

        foreach ($this->pluginPhpFiles() as $file) {
            $source = (string) file_get_contents($file);

            // Match the hook-name string literal in any apply_filters/
            // do_action/add_filter/add_action call that references an
            // ntdst/ hook — single or double quoted, interpolated or not.
            preg_match_all(
                '/(?:apply_filters|do_action|add_filter|add_action)\(\s*["\'](ntdst\/[^"\']*)/',
                $source,
                $matches,
            );

            foreach ($matches[1] as $hook) {
                if (str_starts_with($hook, 'ntdst/baseline/')) {
                    continue;
                }
                if ($this->isAllowedForeignHook($hook)) {
                    continue;
                }
                $offenders[] = basename($file) . ' -> ' . $hook;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "every ntdst hook owned by ntdst-baseline must live under `ntdst/baseline/`, so the plugin has "
            . "ONE extension namespace rather than two. Offenders:\n  " . implode("\n  ", $offenders),
        );
    }

    /**
     * Guard: the scanner must actually FIND hooks. Without this, a broken
     * regex (or a wrong directory) would make the test above vacuously
     * green while asserting nothing at all.
     */
    public function test_the_scanner_finds_the_plugins_real_hooks(): void
    {
        $found = 0;

        foreach ($this->pluginPhpFiles() as $file) {
            $found += preg_match_all(
                '/(?:apply_filters|do_action|add_filter|add_action)\(\s*["\']ntdst\//',
                (string) file_get_contents($file),
            );
        }

        $this->assertGreaterThan(
            10,
            $found,
            'the namespace scanner found suspiciously few ntdst hooks — the regex or the path is wrong, '
            . 'which would make the convention assertion vacuous',
        );
    }

    private function isAllowedForeignHook(string $hook): bool
    {
        foreach (array_keys(self::ALLOWED_FOREIGN_HOOKS) as $allowed) {
            if ($hook === $allowed || str_starts_with($hook, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function pluginPhpFiles(): array
    {
        $dir = dirname(__DIR__, 2) . self::BASELINE_DIR;
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $entry) {
            if ($entry instanceof \SplFileInfo && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
