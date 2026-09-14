<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminAppService;
use ntdst\baseline\services\admin\AdminUIService as BaselineAdminUI;
use WP_UnitTestCase;

/**
 * The dashboard as ntdst-baseline actually renders it.
 *
 * Fires the page hook `add_menu_page()` registered, so what runs is the
 * MODULE's own wrapper closure — `Shell::open()` → josworld's render →
 * `Shell::close()`. Calling renderDashboard() directly would assert the
 * body while proving nothing about the chrome, and the chrome is the half
 * this feature outsources.
 */
final class AdminAppDashboardRenderTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/admin/AdminAppService.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        foreach (array_keys(AdminAppService::POST_TYPES) as $postType) {
            if (! post_type_exists($postType)) {
                register_post_type($postType, ['public' => true, 'show_ui' => true]);
            }
        }

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        set_current_screen('dashboard');

        $GLOBALS['menu'] = [];
        $GLOBALS['submenu'] = [];
    }

    protected function tearDown(): void
    {
        global $current_screen;
        $current_screen = null;
        unset($GLOBALS['menu'], $GLOBALS['submenu']);

        parent::tearDown();
    }

    private function renderThroughTheModule(): string
    {
        new AdminAppService();
        new BaselineAdminUI();

        do_action('admin_menu');

        ob_start();
        do_action('toplevel_page_josworld');

        return (string) ob_get_clean();
    }

    public function test_the_dashboard_renders_four_tiles_inside_the_baseline_shell(): void
    {
        $html = $this->renderThroughTheModule();

        $this->assertStringContainsString('class="wrap ntdst-admin"', $html, 'the shell must wrap the body');
        $this->assertStringContainsString('ntdst-admin__title', $html, 'the shell header must render');
        $this->assertStringContainsString('ntdst-admin__tiles', $html);
        $this->assertSame(
            4,
            substr_count($html, 'ntdst-admin__tile-value'),
            'exactly one tile per content type',
        );

        foreach (AdminAppService::POST_TYPES as $label) {
            $this->assertStringContainsString($label, $html, "the `{$label}` tile must be labelled");
        }
    }

    public function test_a_tile_reports_the_real_published_count_and_flags_drafts(): void
    {
        self::factory()->post->create_many(2, ['post_type' => 'case', 'post_status' => 'publish']);
        self::factory()->post->create(['post_type' => 'case', 'post_status' => 'draft']);

        $html = $this->renderThroughTheModule();

        $this->assertStringContainsString('<div class="ntdst-admin__tile-value">2</div>', $html);
        $this->assertStringContainsString('1 concept', $html, 'a draft must be flagged on the tile');
    }

    public function test_a_type_with_no_posts_shows_zero_and_no_draft_line(): void
    {
        $html = $this->renderThroughTheModule();

        $this->assertStringContainsString('<div class="ntdst-admin__tile-value">0</div>', $html);
        $this->assertStringNotContainsString('0 concepten', $html, 'an empty type must not print a draft line');
    }

    public function test_a_user_who_cannot_create_sees_no_new_link(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));

        $html = $this->renderThroughTheModule();

        $this->assertStringNotContainsString('ntdst-admin__tile-link', $html);
        $this->assertStringNotContainsString('post-new.php', $html);
    }
}
