<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

/**
 * Pins ntdst-baseline's hook-namespace convention: every hook the plugin
 * owns lives under `ntdst/baseline/`. No linter or runtime test sees hook
 * names, so without this a divergent namespace returns unnoticed.
 *
 * Deliberately a SOURCE SCAN, not a behavioural test: the property covers
 * the whole tree, and a runtime assertion would miss any filter on a
 * branch no test drives.
 *
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
        'ntdst/model/registered' => 'ntdst-core framework hook, not baseline-owned',
        'ntdst/model/registering' => 'ntdst-core framework hook, not baseline-owned',
        'ntdst/model/updating' => 'ntdst-core data-layer hook baseline subscribes to, not baseline-owned',
        'ntdst/model/updated' => 'ntdst-core data-layer hook baseline subscribes to, not baseline-owned',
        'ntdst/model/meta_updated' => 'ntdst-core data-layer hook baseline subscribes to, not baseline-owned',
        'ntdst/pages/languages' => 'ntdst-core page-route extension point baseline answers, not baseline-owned',
        'ntdst/pages/current_language' => 'ntdst-core page-route extension point baseline answers, not baseline-owned',
        'ntdst/api_data/' => 'ntdst-core API router vocabulary, not baseline-owned',
        'ntdst/api/' => 'ntdst-core API router vocabulary, not baseline-owned',
        'ntdst/yootheme/exclude_fields' => 'the yootheme module (2.3.0) kept the name the per-project bridges consumed',
    ];

    /**
     * Hook literals allowed only as written: the scanner reads up to the closing quote, so a name
     * built by concatenation shows as its first piece, and a prefix match on that would allow any hook.
     *
     * @var array<string, string>
     */
    private const ALLOWED_EXACT_LITERALS = [
        'ntdst/' => "GrantModel's `'ntdst/' . POST_TYPE . '/fields'`: ntdst-core's per-model fields filter",
    ];

    public function test_every_ntdst_hook_in_the_plugin_uses_the_baseline_namespace(): void
    {
        $offenders = [];

        foreach ($this->pluginPhpFiles() as $file) {
            $source = (string) file_get_contents($file);

            // Match the hook-name literal in any hook call referencing
            // an ntdst/ hook — either quote style, interpolated or not.
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

    // Guard: without this, a broken regex or wrong directory would make
    // the convention assertion vacuously green.
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
        if (isset(self::ALLOWED_EXACT_LITERALS[$hook])) {
            return true;
        }

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
