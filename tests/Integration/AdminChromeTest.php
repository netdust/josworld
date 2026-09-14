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

    public function test_the_cpt_screens_carry_the_admin_chrome(): void
    {
        foreach (array_keys(AdminAppService::POST_TYPES) as $postType) {
            $classes = $this->bodyClassOn($postType);

            $this->assertStringContainsString(
                'ntdst-admin',
                $classes,
                "the `{$postType}` screens must inherit baseline's tokens via the body class",
            );
        }
    }

    public function test_the_post_edit_screen_carries_it_too(): void
    {
        $this->assertStringContainsString('ntdst-admin', $this->bodyClassOn('edit-case'));
    }

    public function test_the_apps_own_pages_carry_it_too(): void
    {
        // The dashboard needs the same sheet: the tile links and the equal
        // split live there, not on the CPT screens.
        set_current_screen('toplevel_page_josworld');

        $this->assertStringContainsString('jw-admin-chrome', (string) apply_filters('admin_body_class', ''));
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
