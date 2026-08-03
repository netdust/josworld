<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use NtdstTests\Support\DetectsTimeoutBinary;
use WP_UnitTestCase;

/**
 * Integration tier: proves the T02 CONTRACT — ntdst-baseline actually BOOTS
 * inside real WordPress (T01 only proved the tree parses), and that its
 * per-module toggle (`ntdst/baseline/modules`) genuinely gates which
 * services construct.
 *
 * THREE CLAUSES, THREE PROOF TECHNIQUES (mirrors JosworldCoreBootTest.php's
 * own three-clause structure — same load-order trap, same subprocess
 * technique for the framework-absent case):
 *
 * 1) With defaults, all 6 modules instantiate — observed via the
 *    `ntdst/baseline/booted` marker filter every service's constructor
 *    registers (`apply_filters('ntdst/baseline/booted', [])`).
 * 2) DENIAL PATH: evicting a module from the container then disabling it
 *    via `ntdst/baseline/modules` means it is NOT reconstructed on
 *    re-invocation (see the count-delta technique below — simple presence
 *    in the booted list is not a valid observable for this clause).
 * 3) Framework-absent path does not fatal (FR-10/AC-9): `ntdst_get` IS
 *    defined in this integration environment (ntdst-core is a real loaded
 *    mu-plugin), so it cannot be honestly undefined in-process. A real,
 *    separate `php` subprocess requires the REAL, unmodified baseline
 *    loader + entrypoint with a minimal hook shim and NO ntdst-core at all,
 *    mirroring this project's own established pattern
 *    (tests/Unit/IntegrationBootstrapGuardTest.php,
 *    JosworldCoreBootTest.php's clause 3).
 *
 * CLAUSES 1+2 — re-invoke the REAL, named `boot_modules()` function, don't
 * reimplement it. `ntdst\baseline\boot_modules()` is deliberately extracted
 * to a named, independently-callable function (see its own docblock)
 * precisely so a test can re-invoke it deterministically AFTER registering
 * its own `ntdst/baseline/modules` filter — the real boot already ran once
 * at genuine WP load with defaults, before this test's filter could attach.
 * Re-invoking it is calling the actual code under test a second time, not a
 * simulation of it.
 *
 * SHARED-HOOK-QUEUE HAZARD (see CacheHeadersService.php's class docblock
 * for the canonical statement of this hazard) — and why clause 2 cannot use
 * simple PRESENCE in `apply_filters('ntdst/baseline/booted', [])` as its
 * re-boot observable: the REAL, container-managed service instances are
 * constructed ONCE at genuine WP boot — with all 6 modules enabled by
 * default — before ANY test method runs. Each constructor's
 * `add_filter('ntdst/baseline/booted', …)` call is a PERMANENT hook
 * registration on that real instance; `NTDST_Container::forget($id)` (the
 * container's own documented eviction seam — "If a test needs fresh
 * resolution after rebinding, call flush()."; forget() is flush()'s
 * single-service equivalent, see Container.php's class docblock) clears the
 * container's cache so a later `ntdst_get()` call would construct fresh,
 * but it cannot and does not un-register that original instance's already-
 * attached `booted` filter closure — WordPress's hook table is a separate
 * concern from the container. So even a module that is correctly NEVER
 * reconstructed after being forgotten and disabled still shows up (once) in
 * `apply_filters('ntdst/baseline/booted', [])`, because the stale marker
 * from the first real boot never goes away. Simple presence is therefore
 * only a valid observable for "was this module EVER constructed since real
 * WP boot" (clause 1's question) — it cannot answer "was this module
 * constructed AGAIN just now" (clause 2's question).
 *
 * Clause 2 instead uses a COUNT DELTA: `array_count_values()` of the
 * `booted` list taken once BEFORE the forget()+disable+re-invoke sequence,
 * once AFTER. A constructor call registers exactly one MORE closure on the
 * hook, so a genuinely-reconstructed module's count for that key increases
 * by exactly one between the two reads; a module correctly skipped keeps
 * the SAME count (the stale marker from real boot, no more). A POSITIVE
 * CONTROL (a second module — schema — also forgotten but left ENABLED)
 * proves this delta technique is genuinely discriminating: it MUST show a
 * +1, or the technique itself (or `boot_modules()`) is not doing what this
 * test assumes. `forget()` is applied only to these two modules under
 * test, not all 6 — several OTHER constructors wire heavier real side
 * effects (cron scheduling, login-attempt tracking, admin-bar nodes) that
 * would widen this test's blast radius on the shared hook queue for no
 * added proof value. Both forgotten services are re-warmed in tearDown (a
 * bare `ntdst_get()` call each, with no test filter left attached) so no
 * OTHER test file's shared hook queue (`template_redirect`, `save_post`,
 * etc.) is left without that service's real, once-only registered
 * listeners.
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
     * @var array<int, callable>
     */
    private array $addedModuleFilters = [];

    /**
     * Any container service IDs this test evicted via forget() and must
     * re-warm in tearDown so other test files' shared hook queues
     * (template_redirect, save_post, etc.) still see that service's real,
     * once-only registered listeners.
     *
     * @var array<int, class-string>
     */
    private array $forgottenServiceIds = [];

    protected function tearDown(): void
    {
        foreach ($this->addedModuleFilters as $callback) {
            remove_filter('ntdst/baseline/modules', $callback);
        }
        $this->addedModuleFilters = [];

        foreach ($this->forgottenServiceIds as $id) {
            // Re-warm with the default (all-enabled) filter set — no
            // test-added ntdst/baseline/modules filter remains attached at
            // this point, so this reconstructs the service exactly as the
            // real boot would.
            \ntdst_get($id);
        }
        $this->forgottenServiceIds = [];

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
        // The real WP boot already constructed every module once (all 6
        // default to enabled) before this test method ran, and ntdst_get()
        // caches singletons — so disabling 'seo' and re-invoking
        // boot_modules() proves nothing unless (a) the SeoService singleton
        // is first evicted from the container (forget() — see class
        // docblock) AND (b) the observable used counts constructions from
        // THIS point forward rather than reading the permanently-polluted
        // `booted` marker list (see class docblock for why `booted` cannot
        // answer this question on its own).
        //
        // Two modules are forgotten: 'seo' (stays disabled — this is the
        // clause under test) and 'schema' as a POSITIVE CONTROL (stays
        // enabled) — proving the count-delta technique itself genuinely
        // detects a module that DOES get reconstructed is what makes the
        // seo assertion below discriminating rather than vacuously true
        // (e.g. if boot_modules() silently never reconstructed anything at
        // all, both assertions would need to agree; only the disabled one
        // may show a zero delta).
        $seoClass = \ntdst\baseline\services\SeoService::class;
        $schemaClass = \ntdst\baseline\services\SchemaService::class;
        \ntdst_container()->forget($seoClass);
        \ntdst_container()->forget($schemaClass);
        $this->forgottenServiceIds[] = $seoClass;
        $this->forgottenServiceIds[] = $schemaClass;

        // A CONSTRUCTOR REGISTERS its `booted` marker closure — it does not
        // FIRE the hook itself (nothing calls apply_filters('ntdst/baseline/
        // booted', …) during boot_modules(); that only happens when
        // something, e.g. this test, explicitly reads the marker list
        // afterwards). So the discriminating observable is the marker
        // COUNT for each key, taken once before this re-invocation and once
        // after: a genuinely-reconstructed module gains exactly one more
        // registered closure (one more occurrence when the hook is read);
        // a module that was correctly skipped gains none.
        $countBefore = $this->booted_marker_counts();

        // DENIAL PATH (Tier-A obligation): disable exactly the forgotten
        // 'seo' module; leave the other forgotten module ('schema') and
        // every other key at their default (true).
        $callback = static function (array $modules): array {
            $modules['seo'] = false;

            return $modules;
        };
        add_filter('ntdst/baseline/modules', $callback);
        $this->addedModuleFilters[] = $callback;

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
     * Typed wrapper around `array_count_values(apply_filters('ntdst/baseline/booted', []))`
     * — PHPStan cannot narrow the untyped `apply_filters()` return well
     * enough on its own for level 6 to accept indexed access below.
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
        // ntdst_get() IS defined in this integration environment (ntdst-core
        // is loaded as a real mu-plugin) — it cannot be honestly undefined
        // in-process. A real, separate php subprocess that requires the
        // ACTUAL baseline loader + entrypoint with a minimal WP-hook shim
        // and NO ntdst-core at all is the only way to make ntdst_get()
        // genuinely absent while still exercising the real guarded code
        // path end-to-end (same technique as
        // tests/Unit/IntegrationBootstrapGuardTest.php and
        // JosworldCoreBootTest.php's clause 3).
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
     * Minimal, real-hook-faithful shim: NOT a copy of the boot block (that
     * would prove nothing about the real file) — just enough add_action/
     * do_action surface for the REAL, unmodified loader + entrypoint to run
     * its actual deferred closures end-to-end, with ntdst_get() genuinely
     * absent from the symbol table.
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
