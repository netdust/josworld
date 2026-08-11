<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminUIService;
use WP_UnitTestCase;

/**
 * Proves the metabox field JS enqueues ONLY on josworld CPT edit screens,
 * with jquery + jquery-ui-sortable deps, against real WP screen/queue
 * machinery. The negative cases are the substance: an everywhere-enqueue
 * passes a positive-only assertion.
 */
final class AdminUIServiceEnqueueTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/admin/AdminUIService.php';

        // Registered directly (not via the content services) so real
        // WP_Screen resolution is exercised without those dependencies.
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
     * @return array{0: bool, 1: array<int, string>, 2: string} [is enqueued, deps, src]
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
        $src = $isEnqueued ? (string) $wp_scripts->registered['josworld-metabox-fields']->src : '';

        wp_dequeue_script('josworld-metabox-fields');
        wp_deregister_script('josworld-metabox-fields');

        // josworld-metabox-conditional depends on -fields, and the
        // 'enqueued' query recurses the queue's dep tree — leaving the
        // dependent enqueued makes the next check report true again.
        wp_dequeue_script('josworld-metabox-conditional');
        wp_deregister_script('josworld-metabox-conditional');

        return [$isEnqueued, $deps, $src];
    }

    public function test_enqueues_on_a_case_edit_screen_with_jquery_and_sortable_deps(): void
    {
        [$isEnqueued, $deps] = $this->fireEnqueueOn('post.php', 'case');

        $this->assertTrue($isEnqueued, 'expected the metabox field JS to be enqueued on a case edit screen');
        $this->assertContains('jquery', $deps);
        $this->assertContains('jquery-ui-sortable', $deps);
    }

    /**
     * Registration is not resolution: a wrong dirname() hop in the
     * plugins_url() base 404s the src while wp_script_is() stays true.
     */
    public function test_enqueued_script_src_resolves_inside_the_plugin_directory(): void
    {
        [$isEnqueued, , $src] = $this->fireEnqueueOn('post.php', 'case');

        $this->assertTrue($isEnqueued, 'precondition: the script must be enqueued to have a src');
        $this->assertStringEndsWith(
            '/mu-plugins/josworld-core/assets/js/metabox-fields.js',
            $src,
            'the enqueued src must resolve to the file inside josworld-core, not to a '
            . 'sibling/parent directory — a wrong dirname() hop 404s silently',
        );
        $this->assertStringStartsWith('http', $src, 'plugins_url() must yield an absolute URL');
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
