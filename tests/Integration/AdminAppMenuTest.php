<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminAppService;
use ntdst\baseline\services\admin\AdminUIService as BaselineAdminUI;
use WP_UnitTestCase;

/**
 * The cluster RED for specs/josworld-admin-app.
 *
 * Drives the REAL ntdst-baseline admin_ui module. The whole point of the
 * feature is that josworld supplies a config array and the MODULE does the
 * moving, so a test asserting against josworld's own array would prove
 * nothing — it would pass just as happily if the filter were never read.
 *
 * The four top-level CPT entries are seeded the way `wp-admin/menu.php`
 * builds them at request time (that file never runs in the test suite), so
 * `remove_menu_page()` has something real to remove. Without the seed the
 * "no longer top-level" assertion would pass against an empty menu.
 */
final class AdminAppMenuTest extends WP_UnitTestCase
{
    private const POST_TYPES = ['case' => 'Cases', 'team' => 'Teamleden', 'vacature' => 'Vacatures', 'tool' => 'Tools'];

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/admin/AdminAppService.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        foreach (array_keys(self::POST_TYPES) as $postType) {
            if (! post_type_exists($postType)) {
                register_post_type($postType, ['public' => true, 'show_ui' => true]);
            }
        }
        if (! taxonomy_exists('dienst')) {
            register_taxonomy('dienst', 'case', ['public' => true, 'show_ui' => true]);
        }

        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        set_current_screen('dashboard'); // is_admin() answers true from here

        $GLOBALS['menu'] = [];
        $GLOBALS['submenu'] = [];
        foreach (self::POST_TYPES as $postType => $label) {
            $GLOBALS['menu'][] = [
                $label, 'edit_posts', 'edit.php?post_type=' . $postType, '',
                'menu-top', 'menu-posts-' . $postType, 'dashicons-admin-post',
            ];
        }
    }

    protected function tearDown(): void
    {
        global $current_screen;
        $current_screen = null;
        unset($GLOBALS['menu'], $GLOBALS['submenu']);

        parent::tearDown();
    }

    public function test_four_cpts_move_under_the_josworld_menu(): void
    {
        new AdminAppService();
        new BaselineAdminUI();

        do_action('admin_menu');

        $under = array_column($GLOBALS['submenu']['josworld'] ?? [], 2);
        $top = array_column($GLOBALS['menu'], 2);

        $this->assertContains('josworld', $top, 'a single top-level Josworld menu must exist');

        foreach (array_keys(self::POST_TYPES) as $postType) {
            $this->assertContains(
                'edit.php?post_type=' . $postType,
                $under,
                "`{$postType}` must appear as a submenu entry under Josworld",
            );
            $this->assertNotContains(
                'edit.php?post_type=' . $postType,
                $top,
                "`{$postType}` must no longer hold a top-level menu entry",
            );
        }

        foreach (['dienst', 'domain', 'doelgroep'] as $taxonomy) {
            $this->assertContains(
                "edit-tags.php?taxonomy={$taxonomy}&post_type=case",
                $under,
                "the `{$taxonomy}` taxonomy must follow `case` under Josworld",
            );
        }
    }
}
