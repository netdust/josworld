<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use NtdstTests\Support\DetectsTimeoutBinary;
use WP_UnitTestCase;

/**
 * ntdst-baseline boots inside real WordPress, and the per-module
 * `ntdst/baseline/modules` filter genuinely gates which services construct.
 * The framework-absent clause runs in a subprocess with a hook shim because
 * ntdst_get() cannot be honestly undefined in-process.
 *
 * Shared-hook-queue hazard: the real boot already constructed every module
 * once, and those instances' `booted` marker registrations survive forget()
 * — so presence in the marker list cannot answer "constructed again just
 * now". The disable test uses forget() + a marker COUNT DELTA instead, with
 * an evicted-but-enabled sibling as positive control, and tearDown restores
 * the ORIGINAL evicted instances (re-warming or leaving the slot empty both
 * strand listeners — see tearDown).
 */
final class BaselineBootTest extends WP_UnitTestCase
{
    use DetectsTimeoutBinary;

    private const ALL_MODULE_KEYS = [
        'security',
        'head_cleanup',
        'seo',
        'schema',
        'maintenance',
        'cache_headers',
    ];

    /**
     * Original instances evicted via forget(), restored verbatim in
     * tearDown — see there for why any other cleanup corrupts state.
     *
     * @var array<class-string, object>
     */
    private array $originalServiceInstances = [];

    protected function tearDown(): void
    {
        // parent::tearDown()'s _restore_hooks() already removes any filter
        // this file added. Only the ORIGINAL instance stays wired after that
        // restore: re-warming here constructs a fresh instance whose
        // listeners the restore then wipes, and forgetting defers the same
        // broken end state to the next resolution — so put the exact
        // original references back.
        foreach ($this->originalServiceInstances as $id => $instance) {
            \ntdst_container()->set($id, $instance);
        }
        $this->originalServiceInstances = [];

        parent::tearDown();
    }

    public function test_enabled_modules_instantiate_with_default_filter_values(): void
    {
        \ntdst\baseline\boot_modules();

        $booted = apply_filters('ntdst/baseline/booted', []);

        foreach (self::ALL_MODULE_KEYS as $key) {
            $this->assertContains(
                $key,
                $booted,
                "module '{$key}' must be present in the booted marker list when all modules default to enabled",
            );
        }
    }

    public function test_a_module_disabled_via_the_modules_filter_is_not_instantiated(): void
    {
        // Re-invoking boot_modules() proves nothing unless the singleton is
        // first evicted AND the observable counts constructions from this
        // point forward (see class docblock). 'schema' is forgotten too but
        // left enabled — the positive control.
        $seoClass = \ntdst\baseline\services\SeoService::class;
        $schemaClass = \ntdst\baseline\services\SchemaService::class;

        // tearDown restores these exact references.
        $this->originalServiceInstances[$seoClass] = \ntdst_get($seoClass);
        $this->originalServiceInstances[$schemaClass] = \ntdst_get($schemaClass);

        \ntdst_container()->forget($seoClass);
        \ntdst_container()->forget($schemaClass);

        // A constructor REGISTERS one more marker closure; the count delta
        // per key is therefore constructions since this read.
        $countBefore = $this->booted_marker_counts();

        // Disable only the forgotten 'seo'; everything else stays default.
        $callback = static function (array $modules): array {
            $modules['seo'] = false;

            return $modules;
        };
        add_filter('ntdst/baseline/modules', $callback);

        \ntdst\baseline\boot_modules();

        $countAfter = $this->booted_marker_counts();

        $this->assertSame(
            $countBefore['seo'] ?? 0,
            $countAfter['seo'] ?? 0,
            'a module disabled via the ntdst/baseline/modules filter must NOT be reconstructed '
            . '(its booted marker count must not increase after evicting it from the container and disabling it)',
        );
        $this->assertSame(
            ($countBefore['schema'] ?? 0) + 1,
            $countAfter['schema'] ?? 0,
            'POSITIVE CONTROL: an evicted-but-still-enabled sibling module (schema) MUST be reconstructed '
            . '(its booted marker count must increase by exactly one) by this same boot_modules() call — '
            . 'proves the observable is genuinely discriminating, not vacuous',
        );
    }

    /**
     * Typed wrapper — PHPStan cannot narrow apply_filters()'s return enough
     * for indexed access.
     *
     * @return array<string, int>
     */
    private function booted_marker_counts(): array
    {
        /** @var array<int, string> $booted */
        $booted = apply_filters('ntdst/baseline/booted', []);

        return array_count_values($booted);
    }

    public function test_boot_does_not_fatal_when_the_framework_is_absent(): void
    {
        // ntdst_get() is defined in-process here; a subprocess with no
        // ntdst-core is the only honest absence.
        $result = $this->boot_the_real_baseline_in_a_subprocess_without_ntdst_core();

        $this->assertSame(
            0,
            $result['exit'],
            "subprocess must exit cleanly when ntdst_get is undefined; stderr:\n{$result['stderr']}",
        );
        $this->assertStringNotContainsString(
            'Fatal error',
            $result['stderr'],
            'the baseline plugin must not fatal when the framework is absent',
        );
        $this->assertStringContainsString('NO_FATAL_REACHED', $result['stdout']);
    }

    /**
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function boot_the_real_baseline_in_a_subprocess_without_ntdst_core(): array
    {
        $harness = tempnam(sys_get_temp_dir(), 'jw-baseline-degrade-');
        $stdoutFile = tempnam(sys_get_temp_dir(), 'jw-baseline-degrade-out-');
        $stderrFile = tempnam(sys_get_temp_dir(), 'jw-baseline-degrade-err-');
        $this->assertNotFalse($harness);
        $this->assertNotFalse($stdoutFile);
        $this->assertNotFalse($stderrFile);

        $loader = dirname(__DIR__, 2) . '/app/content/mu-plugins/ntdst-baselineloader.php';
        $this->assertFileExists($loader, 'the real loader must exist for this subprocess to require it unmodified');

        file_put_contents($harness, $this->degraded_boot_harness_source($loader));

        $command = sprintf(
            '%sphp %s > %s 2> %s',
            $this->timeout_prefix(),
            escapeshellarg($harness),
            escapeshellarg($stdoutFile),
            escapeshellarg($stderrFile),
        );

        exec($command, $unusedOutput, $exit);

        $stdout = (string) file_get_contents($stdoutFile);
        $stderr = (string) file_get_contents($stderrFile);
        unlink($harness);
        unlink($stdoutFile);
        unlink($stderrFile);

        return [
            'exit' => $exit,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    /**
     * Minimal hook shim — the REAL loader + entrypoint run unmodified with
     * ntdst_get() genuinely absent from the symbol table.
     */
    private function degraded_boot_harness_source(string $loader): string
    {
        $loaderExport = var_export($loader, true);

        return <<<PHP
            <?php
            define('ABSPATH', '/tmp/jw-baseline-degrade-fake/');

            \$GLOBALS['__hooks'] = [];
            function add_action(string \$hook, callable \$cb, int \$priority = 10): void {
                \$GLOBALS['__hooks'][\$hook][\$priority][] = \$cb;
            }
            function do_action(string \$hook): void {
                if (!isset(\$GLOBALS['__hooks'][\$hook])) {
                    return;
                }
                ksort(\$GLOBALS['__hooks'][\$hook]);
                foreach (\$GLOBALS['__hooks'][\$hook] as \$callbacks) {
                    foreach (\$callbacks as \$callback) {
                        \$callback();
                    }
                }
            }

            require {$loaderExport};

            do_action('plugins_loaded');
            do_action('after_setup_theme');

            fwrite(STDOUT, "NO_FATAL_REACHED\\n");
            PHP;
    }
}
