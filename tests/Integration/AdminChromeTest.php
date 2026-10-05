<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminAppService;
use josworld\services\admin\AdminChromeService;
use WP_UnitTestCase;

/**
 * The chrome flag, and the no-second-palette rule it exists to keep.
 *
 * SC-6 is the load-bearing one: josworld must not carry its own copy of
 * ntdst-baseline's colours. The body class makes baseline's tokens cascade,
 * so a hex literal in this project's admin stylesheet means the copy crept
 * back in — and nothing else would catch that.
 */
final class AdminChromeTest extends WP_UnitTestCase
{
    private const STYLESHEET = '/../../app/content/mu-plugins/josworld-core/assets/css/admin-chrome.css';

    protected function setUp(): void
    {
        parent::setUp();

        $admin = __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/admin/';
        require_once $admin . 'AdminAppService.php';
        require_once $admin . 'AdminChromeService.php';

        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        foreach (array_keys(AdminAppService::POST_TYPES) as $postType) {
            if (! post_type_exists($postType)) {
                register_post_type($postType, ['public' => true, 'show_ui' => true]);
            }
        }

        new AdminChromeService();
    }

    protected function tearDown(): void
    {
        global $current_screen;
        $current_screen = null;

        parent::tearDown();
    }

    private function bodyClassOn(string $screen): string
    {
        set_current_screen($screen);

        return (string) apply_filters('admin_body_class', '');
    }

    /**
     * Ruled 2026-09-14: the CPT list and edit screens stay WordPress'. A
     * skinned list table felt neither like WordPress nor like the app, so the
     * chrome is scoped to the app's own pages and these screens must come
     * back untouched.
     */
    public function test_the_cpt_screens_are_left_to_wordpress(): void
    {
        foreach (array_keys(AdminAppService::POST_TYPES) as $postType) {
            $classes = $this->bodyClassOn($postType);

            $this->assertStringNotContainsString(
                'jw-admin-chrome',
                $classes,
                "the `{$postType}` list screen must not be restyled",
            );
            $this->assertStringNotContainsString('ntdst-admin', $classes);
        }
    }

    public function test_the_post_edit_screen_is_left_alone_too(): void
    {
        $this->assertStringNotContainsString('jw-admin-chrome', $this->bodyClassOn('edit-case'));
    }

    public function test_the_apps_own_pages_carry_the_chrome(): void
    {
        set_current_screen('toplevel_page_josworld');

        $this->assertStringContainsString('jw-admin-chrome', (string) apply_filters('admin_body_class', ''));
    }

    /**
     * The menu-open fix is a correctness bug, not a skin, so it still runs on
     * the CPT screens the chrome no longer touches.
     *
     * It writes index 4 of the `$menu` row — the class string
     * `_wp_menu_output()` appends verbatim — because the supported
     * `parent_file` route does not stick on `edit.php` here.
     */
    public function test_the_menu_is_held_open_on_a_cpt_screen(): void
    {
        $_GET['post_type'] = 'case';
        $GLOBALS['menu'] = [[
            'Josworld', 'edit_posts', AdminAppService::MENU_SLUG, '',
            'menu-top', 'toplevel_page_josworld', 'dashicons-screenoptions',
        ]];

        (new AdminChromeService())->holdTheMenuOpen();

        $this->assertStringContainsString('wp-menu-open', (string) $GLOBALS['menu'][0][4]);
        $this->assertStringContainsString('wp-has-current-submenu', (string) $GLOBALS['menu'][0][4]);

        unset($_GET['post_type'], $GLOBALS['menu']);
    }

    public function test_an_unrelated_screen_does_not_hold_the_menu_open(): void
    {
        $_GET['post_type'] = 'page';
        $GLOBALS['menu'] = [[
            'Josworld', 'edit_posts', AdminAppService::MENU_SLUG, '',
            'menu-top', 'toplevel_page_josworld', 'dashicons-screenoptions',
        ]];

        (new AdminChromeService())->holdTheMenuOpen();

        $this->assertSame('menu-top', (string) $GLOBALS['menu'][0][4]);

        unset($_GET['post_type'], $GLOBALS['menu']);
    }

    public function test_an_unrelated_screen_is_left_alone(): void
    {
        $classes = $this->bodyClassOn('dashboard');

        $this->assertStringNotContainsString(
            'ntdst-admin',
            $classes,
            'WordPress own screens and other plugins admin pages must not be restyled',
        );
    }

    /**
     * josworld owns a palette now (ruled 2026-09-14: "use daans
     * color/look&feel"), so a colour literal is no longer banned — a
     * SCATTERED one is. Every colour must be declared once as a custom
     * property; a hex inside a normal rule means the palette is leaking out
     * of the token block and will drift.
     */
    public function test_every_colour_lives_in_the_token_block(): void
    {
        $strays = [];

        foreach (explode("\n", (string) file_get_contents(__DIR__ . self::STYLESHEET)) as $line) {
            $line = trim($line);

            // A custom-property declaration is where colour belongs.
            if (str_starts_with($line, '--')) {
                continue;
            }

            if (preg_match('/#[0-9a-fA-F]{3,8}\b/', $line, $m)) {
                $strays[] = trim($line);
            }
        }

        $this->assertSame(
            [],
            $strays,
            'a colour outside the token block will drift from the rest of the palette — '
            . 'declare it as a --ntdst-admin-* or --jw-* custom property and reference it',
        );
    }

    /**
     * The skin works by re-declaring ntdst-baseline's OWN tokens, which is
     * what lets it re-style every component baseline ships without copying a
     * single one of its rules. Lose that and this file becomes a second
     * stylesheet chasing an upstream one.
     */
    public function test_the_skin_redeclares_baseline_tokens_rather_than_restyling_components(): void
    {
        $css = (string) file_get_contents(__DIR__ . self::STYLESHEET);

        foreach (['--ntdst-admin-ground', '--ntdst-admin-border', '--ntdst-admin-primary'] as $token) {
            $this->assertStringContainsString(
                $token . ':',
                $css,
                "{$token} must be re-declared here — that is how the skin reaches baseline's components",
            );
        }
    }
}
