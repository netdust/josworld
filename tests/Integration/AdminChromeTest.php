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

    public function test_the_stylesheet_carries_no_colour_of_its_own(): void
    {
        $css = (string) file_get_contents(__DIR__ . self::STYLESHEET);

        $this->assertSame(
            [],
            $this->hexLiterals($css),
            'a hex colour here means baseline palette has been copied into josworld — '
            . 'the body class exists so the tokens cascade instead',
        );
        $this->assertStringContainsString('--ntdst-admin-', $css, 'the tokens must actually be used');
    }

    /** @return list<string> */
    private function hexLiterals(string $css): array
    {
        preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $css, $matches);

        return $matches[0];
    }
}
