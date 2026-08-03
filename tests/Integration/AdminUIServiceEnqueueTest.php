<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminUIService;
use WP_UnitTestCase;

/**
 * Integration tier: proves the T04 enqueue-scoping CONTRACT against real
 * WordPress hook/screen/script-queue machinery — not a mock.
 *
 * THE CONTRACT (T04 acceptance criteria): the metabox field JS
 * (relation autocomplete, gallery picker, repeater add/remove/drag-sort) is
 * enqueued ONLY on a josworld CPT edit screen (`case`/`team`/`vacature`/
 * `tool`), with `jquery` + `jquery-ui-sortable` as declared dependencies —
 * and is NOT enqueued on unrelated admin screens (dashboard, plugins list,
 * a native `post` edit screen). The negative case is the substance of this
 * test: an enqueue that fires everywhere would "pass" a naive positive-only
 * assertion while still shipping the exact bug this task exists to avoid
 * (ntdst-core's own enqueue firing project-wide off an active-theme path
 * that doesn't exist).
 *
 * WHY integration, not unit: the scoping decision depends on real
 * `get_current_screen()`/`set_current_screen()` state and the real
 * `wp_enqueue_script()` dependency queue — Brain Monkey stubs would let a
 * test assert against whatever the test itself hard-codes, never proving
 * the service reads a REAL screen object the way wp-admin actually
 * populates one on `post.php`/`post-new.php`.
 */
final class AdminUIServiceEnqueueTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/admin/AdminUIService.php';

        // Screens josworld's content services (T05-T08) will register.
        // Registered directly here (not via those not-yet-existing
        // services) so this test exercises real WP_Screen resolution
        // without depending on tasks that haven't landed.
        foreach (['case', 'team', 'vacature', 'tool'] as $postType) {
            if (! post_type_exists($postType)) {
                register_post_type($postType, ['public' => true, 'show_ui' => true]);
            }
        }
    }

    protected function tearDown(): void
    {
        global $current_screen;
        $current_screen = null;
        unset($GLOBALS['hook_suffix']);

        parent::tearDown();
    }

    private function newService(): AdminUIService
    {
        return new AdminUIService();
    }

    /**
     * @return array{0: bool, 1: array<int, string>} [is enqueued, deps]
     */
    private function fireEnqueueOn(string $hook, ?string $postType): array
    {
        if ($postType !== null) {
            set_current_screen($postType);
        } else {
            set_current_screen($hook);
        }

        $service = $this->newService();
        $service->enqueueMetaboxFieldScripts($hook);

        $isEnqueued = wp_script_is('josworld-metabox-fields', 'enqueued');

        global $wp_scripts;
        $deps = $isEnqueued ? $wp_scripts->registered['josworld-metabox-fields']->deps : [];

        wp_dequeue_script('josworld-metabox-fields');
        wp_deregister_script('josworld-metabox-fields');

        return [$isEnqueued, $deps];
    }

    public function test_enqueues_on_a_case_edit_screen_with_jquery_and_sortable_deps(): void
    {
        [$isEnqueued, $deps] = $this->fireEnqueueOn('post.php', 'case');

        $this->assertTrue($isEnqueued, 'expected the metabox field JS to be enqueued on a case edit screen');
        $this->assertContains('jquery', $deps);
        $this->assertContains('jquery-ui-sortable', $deps);
    }

    public function test_enqueues_on_each_josworld_post_type_new_screen(): void
    {
        foreach (['team', 'vacature', 'tool'] as $postType) {
            [$isEnqueued] = $this->fireEnqueueOn('post-new.php', $postType);
            $this->assertTrue($isEnqueued, "expected enqueue on a {$postType} new-post screen");
        }
    }

    public function test_does_not_enqueue_on_the_dashboard(): void
    {
        [$isEnqueued] = $this->fireEnqueueOn('index.php', null);

        $this->assertFalse($isEnqueued, 'the dashboard must never receive the metabox field JS');
    }

    public function test_does_not_enqueue_on_the_plugins_list_screen(): void
    {
        [$isEnqueued] = $this->fireEnqueueOn('plugins.php', null);

        $this->assertFalse($isEnqueued, 'the plugins list must never receive the metabox field JS');
    }

    public function test_does_not_enqueue_on_a_native_post_edit_screen(): void
    {
        [$isEnqueued] = $this->fireEnqueueOn('post.php', 'post');

        $this->assertFalse(
            $isEnqueued,
            'a native `post` edit screen is not a josworld CPT and must not receive the metabox field JS',
        );
    }
}
