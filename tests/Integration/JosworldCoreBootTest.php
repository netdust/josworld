<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminUIService;
use NtdstTests\Support\DetectsTimeoutBinary;
use WP_Hook;
use WP_UnitTestCase;

/**
 * The deferred boot in josworld-core.php against real WordPress: the
 * mu-plugin loader sorts josworld-core BEFORE ntdst-coreloader, so
 * ntdst_get() is undefined at include time and every framework call must
 * wait for a later hook — only provable here, never in Brain Monkey.
 * The resolve/skip clauses re-fire the REAL registered after_setup_theme:5
 * closure (wp-phpunit's bootstrap already fired it once, front-end-like);
 * the framework-absent clause runs a subprocess with a hook shim and no
 * ntdst-core, because ntdst_get cannot be honestly undefined in-process.
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
        // Anchor: the real boot fired exactly once, front-end-like, before
        // any test method ran.
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
        // Simulate a front-end context (no current screen) and re-fire the
        // REAL after_setup_theme:5 closure.
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
        // Simulate an admin screen, then re-fire the REAL registered closure
        // — the actual boot-block code, not a reimplementation.
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

        // Prove the container really constructed and cached an instance:
        // a fresh ntdst_get() returns the SAME object — has() would be true
        // for any autowireable class, constructed or not.
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
        // ntdst_get() is defined in-process here; a subprocess with no
        // ntdst-core is the only honest absence.
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
     * Minimal hook shim — the REAL loader + entrypoint run unmodified with
     * ntdst_get() genuinely absent from the symbol table.
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
