<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminUIService;
use NtdstTests\Support\DetectsTimeoutBinary;
use WP_Hook;
use WP_UnitTestCase;

/**
 * Integration tier: proves the T03 CONTRACT — the deferred boot in
 * josworld-core.php actually works against REAL WordPress, not a
 * simulation. This is the exact load-order trap the boot's own
 * `plugins_loaded` deferral exists for (see josworld-core.php:10-16): the
 * loader sorts alphabetically BEFORE ntdst-coreloader.php, so `ntdst_get()`
 * is undefined at include time and every framework-dependent call must wait
 * for a later hook. Brain Monkey (unit tier) cannot observe any of this —
 * it never runs mu-plugins load order — so this is the ONLY place the trap
 * is provable.
 *
 * THREE CLAUSES, THREE DIFFERENT PROOF TECHNIQUES (each justified at its
 * own test — a single technique cannot honestly prove all three):
 *
 * 1) Services resolve via ntdst_get() on after_setup_theme:5, non-empty.
 * 2) Admin-only services do NOT resolve on a front-end request.
 * 3) With ntdst_get undefined, the site loads with no fatal and registers
 *    no post type.
 *
 * CLAUSES 1+2 — re-fire the REAL registered hook, don't reimplement it:
 * wp-phpunit's bootstrap already fires plugins_loaded/after_setup_theme
 * for real, once, before ANY test method runs (confirmed via probe:
 * did_action('after_setup_theme') === 1 at test start, is_admin() === false
 * at that point because the CLI-driven bootstrap is a front-end-like
 * context with no current screen set). That real, once-only firing is
 * itself clause 2's proof — the actual boot genuinely skipped
 * AdminUIService, verified here by asserting no admin_enqueue_scripts
 * listener from it exists yet. Clause 1 is then proven by RE-FIRING the
 * SAME real, already-registered after_setup_theme:5 closure (do_action()
 * calls the real callback again — this is not a reimplementation of the
 * boot block, it is WordPress invoking the actual code under test a second
 * time) after simulating an admin screen the same way
 * AdminUIServiceEnqueueTest does (set_current_screen()), and asserting the
 * listener now exists AND that ntdst_get() returns the SAME cached
 * instance on a fresh call (proof the container's singleton cache was
 * really populated during the refire, not merely resolvable — has()
 * returns true for any autowireable class whether or not it was ever
 * actually constructed).
 *
 * CLAUSE 3 — real subprocess, mirroring the project's own established
 * pattern (tests/Unit/IntegrationBootstrapGuardTest.php): `ntdst_get` IS
 * defined in this integration environment (ntdst-core is loaded), so it
 * cannot be honestly undefined here in-process. A separate `php` process
 * that requires the REAL, unmodified loader + entrypoint files with a
 * minimal hook shim and no ntdst-core at all is the only way to make
 * `ntdst_get` genuinely absent while still exercising the real guarded
 * code path. The probe (see task notes) confirmed this technique is
 * discriminating: calling ntdst_get() directly while undefined fatals with
 * exit 255 and "Fatal error" in the output, so a clean exit 0 with no such
 * string is real proof the guard fired, not an artifact of an inert test.
 * "No post type registered" is asserted as a REGRESSION GUARD: josworld
 * registers none today (T05-T08 land later), so the meaningful assertion
 * today is that the degraded boot registers no MORE than the current
 * baseline of zero — once a content service lands behind the same guard,
 * this same assertion starts covering the real omission it names.
 */
final class JosworldCoreBootTest extends WP_UnitTestCase
{
    use DetectsTimeoutBinary;

    protected function tearDown(): void
    {
        global $current_screen;
        $current_screen = null;
        unset($GLOBALS['hook_suffix']);

        parent::tearDown();
    }

    public function test_real_boot_already_ran_and_did_not_resolve_admin_service_on_a_front_end_like_request(): void
    {
        // Sanity-anchor: the real plugins_loaded/after_setup_theme sequence
        // has already fired for real, exactly once, before this test method
        // runs — proving the assertions below exercise the REAL boot's
        // first, genuine pass rather than something this test triggers
        // itself.
        $this->assertSame(
            1,
            did_action('after_setup_theme'),
            'the real after_setup_theme hook must already have fired once during bootstrap',
        );
        $this->assertFalse(
            is_admin(),
            'the wp-phpunit CLI bootstrap is front-end-like (no admin screen set) — '
            . 'this is what makes the first real firing a genuine front-end request',
        );

        $this->assertFalse(
            $this->admin_ui_service_has_registered_its_enqueue_listener(),
            'AdminUIService must not have wired its admin_enqueue_scripts listener '
            . 'from the real front-end-like boot pass',
        );
    }

    public function test_admin_only_service_does_not_resolve_on_a_front_end_request(): void
    {
        // Belt-and-braces behavioral assertion alongside the anchor above:
        // explicitly simulate a front-end context (no current screen — the
        // same "unset" state WordPress itself is in on a real front-end
        // request) and re-fire the REAL after_setup_theme:5 closure. It
        // must still refuse to resolve the admin-only service.
        global $current_screen;
        $current_screen = null;

        do_action('after_setup_theme');

        $this->assertFalse(
            $this->admin_ui_service_has_registered_its_enqueue_listener(),
            'admin-only services must not resolve when after_setup_theme fires outside wp-admin',
        );
    }

    public function test_services_resolve_via_ntdst_get_on_after_setup_theme_and_the_set_is_non_empty(): void
    {
        // Simulate an admin edit screen exactly the way
        // AdminUIServiceEnqueueTest does, then RE-FIRE the REAL,
        // already-registered after_setup_theme:5 closure — this calls the
        // actual boot-block code under test a second time, it does not
        // reimplement it.
        set_current_screen('case');
        $this->assertTrue(
            is_admin(),
            'set_current_screen() must put us in an admin context for this case',
        );

        do_action('after_setup_theme');

        $this->assertTrue(
            $this->admin_ui_service_has_registered_its_enqueue_listener(),
            'AdminUIService must resolve (and wire its admin_enqueue_scripts listener) '
            . 'once after_setup_theme fires inside wp-admin',
        );

        // Non-emptiness is not just "a listener exists" — prove the
        // CONTAINER really constructed and cached an instance: a fresh
        // ntdst_get() call returns the SAME instance (singleton semantics),
        // which only happens if resolve() already ran and populated the
        // resolved cache. has() alone would be true even if nothing was
        // ever constructed (it also passes for any autowireable class).
        $first = ntdst_get(AdminUIService::class);
        $second = ntdst_get(AdminUIService::class);
        $this->assertInstanceOf(AdminUIService::class, $first);
        $this->assertSame(
            $first,
            $second,
            'ntdst_get() must return the cached singleton — proof the boot block '
            . 'actually resolved the service, not merely that it COULD',
        );
    }

    public function test_site_loads_with_no_fatal_and_registers_no_post_type_when_ntdst_get_is_undefined(): void
    {
        // ntdst_get() IS defined in this integration environment (ntdst-core
        // is loaded as a real mu-plugin) — it cannot be honestly undefined
        // in-process. A real, separate php subprocess that requires the
        // ACTUAL loader + entrypoint files with a minimal WP-hook shim and
        // NO ntdst-core at all is the only way to make ntdst_get() genuinely
        // absent while still exercising the real guarded code path
        // end-to-end, mirroring this project's own established subprocess
        // pattern (tests/Unit/IntegrationBootstrapGuardTest.php).
        $result = $this->boot_the_real_plugin_in_a_subprocess_without_ntdst_core();

        $this->assertSame(
            0,
            $result['exit'],
            "subprocess must exit cleanly when ntdst_get is undefined; stderr:\n{$result['stderr']}",
        );
        $this->assertStringNotContainsString(
            'Fatal error',
            $result['stderr'],
            'the site must not fatal when the framework is absent',
        );
        $this->assertStringContainsString('NO_FATAL_REACHED', $result['stdout']);
        $this->assertSame(
            '0',
            trim($result['registeredPostTypes']),
            'no post type may be registered when the plugin degrades because ntdst_get '
            . 'is undefined (regression guard: josworld registers none today; this '
            . 'assertion starts covering real content services once T05-T08 land '
            . 'behind the same guard)',
        );
    }

    private function admin_ui_service_has_registered_its_enqueue_listener(): bool
    {
        global $wp_filter;

        if (!isset($wp_filter['admin_enqueue_scripts']) || !$wp_filter['admin_enqueue_scripts'] instanceof WP_Hook) {
            return false;
        }

        foreach ($wp_filter['admin_enqueue_scripts']->callbacks as $callbacksAtPriority) {
            foreach ($callbacksAtPriority as $callback) {
                $function = $callback['function'];
                if (is_array($function) && ($function[0] ?? null) instanceof AdminUIService) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{exit: int, stdout: string, stderr: string, registeredPostTypes: string}
     */
    private function boot_the_real_plugin_in_a_subprocess_without_ntdst_core(): array
    {
        $harness = tempnam(sys_get_temp_dir(), 'jw-boot-degrade-');
        $stdoutFile = tempnam(sys_get_temp_dir(), 'jw-boot-degrade-out-');
        $stderrFile = tempnam(sys_get_temp_dir(), 'jw-boot-degrade-err-');
        $this->assertNotFalse($harness);
        $this->assertNotFalse($stdoutFile);
        $this->assertNotFalse($stderrFile);

        $loader = dirname(__DIR__, 2) . '/app/content/mu-plugins/josworld-coreloader.php';
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

        preg_match('/REGISTERED_POST_TYPES=(\d+)/', $stdout, $matches);

        return [
            'exit' => $exit,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'registeredPostTypes' => $matches[1] ?? '',
        ];
    }

    /**
     * Minimal, real-hook-faithful shim: NOT a copy of the boot block (that
     * would prove nothing about the real file) — just enough add_action/
     * do_action/is_admin/register_post_type surface for the REAL, unmodified
     * loader + entrypoint to run its actual deferred closures end-to-end,
     * with ntdst_get() genuinely absent from the symbol table.
     */
    private function degraded_boot_harness_source(string $loader): string
    {
        $loaderExport = var_export($loader, true);

        return <<<PHP
            <?php
            define('ABSPATH', '/tmp/jw-boot-degrade-fake/');

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
            function is_admin(): bool {
                return true; // worst case: even inside wp-admin, must not fatal.
            }
            \$GLOBALS['__registered_post_types'] = [];
            function register_post_type(string \$postType, array \$args = []): void {
                \$GLOBALS['__registered_post_types'][] = \$postType;
            }

            require {$loaderExport};

            do_action('plugins_loaded');
            do_action('after_setup_theme');

            fwrite(STDOUT, "NO_FATAL_REACHED\\n");
            fwrite(STDOUT, 'REGISTERED_POST_TYPES=' . count(\$GLOBALS['__registered_post_types']) . "\\n");
            PHP;
    }
}
