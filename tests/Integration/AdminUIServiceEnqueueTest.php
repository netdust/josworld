<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminUIService;
use WP_UnitTestCase;

/**
 * Proves the metabox field JS delivery after its promotion into ntdst-core:
 * the framework's MetaboxGenerator enqueues `ntdst-metabox-fields` (with the
 * wp-api-fetch transport the relation picker needs) ONLY on registered-model
 * edit screens, and josworld's AdminUIService layers only its
 * conditional-field script on that handle. The negative cases are the
 * substance: an everywhere-enqueue passes a positive-only assertion.
 */
final class AdminUIServiceEnqueueTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/admin/AdminUIService.php';

        // The real content services registered the four models (and their
        // metaboxes) at boot; register only the bare post types the screen
        // machinery needs when another test unregistered them.
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
        unset($GLOBALS['hook_suffix'], $GLOBALS['post_type']);

        parent::tearDown();
    }

    /**
     * Fire the CORE's enqueue (MetaboxGenerator gates on its own registered
     * models via the global $post_type the admin screen sets).
     *
     * @return array{0: bool, 1: array<int, string>, 2: string} [is enqueued, deps, src]
     */
    private function fireCoreEnqueueOn(string $hook, ?string $postType): array
    {
        if ($postType !== null) {
            set_current_screen($postType);
            $GLOBALS['post_type'] = $postType;
        } else {
            set_current_screen($hook);
            unset($GLOBALS['post_type']);
        }

        ntdst_metabox()->enqueue_metabox_scripts($hook);

        $isEnqueued = wp_script_is('ntdst-metabox-fields', 'enqueued');

        global $wp_scripts;
        $deps = $isEnqueued ? $wp_scripts->registered['ntdst-metabox-fields']->deps : [];
        $src = $isEnqueued ? (string) $wp_scripts->registered['ntdst-metabox-fields']->src : '';

        wp_dequeue_script('ntdst-metabox-fields');
        wp_deregister_script('ntdst-metabox-fields');

        return [$isEnqueued, $deps, $src];
    }

    public function test_the_core_enqueues_the_client_on_a_case_edit_screen_with_its_deps(): void
    {
        [$isEnqueued, $deps] = $this->fireCoreEnqueueOn('post.php', 'case');

        $this->assertTrue($isEnqueued, 'expected ntdst-metabox-fields on a case edit screen');
        $this->assertContains('jquery', $deps);
        $this->assertContains('jquery-ui-sortable', $deps);
        $this->assertContains(
            'wp-api-fetch',
            $deps,
            'the relation autocomplete transports through wp.apiFetch since 5.0 '
            . '(the ntdst-api handle died with the command dispatcher) — without '
            . 'the wp-api-fetch dependency the picker silently disables itself',
        );
    }

    /**
     * Registration is not resolution: a wrong URL base 404s the src while
     * wp_script_is() stays true.
     */
    public function test_enqueued_script_src_resolves_inside_ntdst_core(): void
    {
        [$isEnqueued, , $src] = $this->fireCoreEnqueueOn('post.php', 'case');

        $this->assertTrue($isEnqueued, 'precondition: the script must be enqueued to have a src');
        $this->assertStringEndsWith(
            '/mu-plugins/ntdst-core/assets/js/metabox-fields.js',
            $src,
            'the client ships with the framework now — the src must resolve inside ntdst-core',
        );
        $this->assertStringStartsWith('http', $src, 'the src must be an absolute URL');
    }

    public function test_the_core_enqueues_on_each_josworld_post_type_new_screen(): void
    {
        foreach (['team', 'vacature', 'tool'] as $postType) {
            [$isEnqueued] = $this->fireCoreEnqueueOn('post-new.php', $postType);
            $this->assertTrue($isEnqueued, "expected enqueue on a {$postType} new-post screen");
        }
    }

    public function test_the_core_does_not_enqueue_on_the_dashboard(): void
    {
        [$isEnqueued] = $this->fireCoreEnqueueOn('index.php', null);

        $this->assertFalse($isEnqueued, 'the dashboard must never receive the metabox field JS');
    }

    public function test_the_core_does_not_enqueue_on_a_native_post_edit_screen(): void
    {
        [$isEnqueued] = $this->fireCoreEnqueueOn('post.php', 'post');

        $this->assertFalse(
            $isEnqueued,
            'a native `post` edit screen has no registered model and must not receive the JS',
        );
    }

    /**
     * josworld's remaining admin layer: the conditional-field script rides
     * on the CORE's handle — a renamed/withdrawn ntdst-metabox-fields would
     * leave this dependency dangling and the script unprinted.
     */
    public function test_the_conditional_layer_depends_on_the_core_handle(): void
    {
        set_current_screen('case');

        (new AdminUIService())->enqueueConditionalFieldScripts('post.php');

        $this->assertTrue(
            wp_script_is('josworld-metabox-conditional', 'registered'),
            'the conditional-field script must enqueue on a josworld edit screen',
        );

        global $wp_scripts;
        $this->assertContains(
            'ntdst-metabox-fields',
            $wp_scripts->registered['josworld-metabox-conditional']->deps,
            'the conditional layer must depend on the framework handle, not a project one',
        );

        wp_dequeue_script('josworld-metabox-conditional');
        wp_deregister_script('josworld-metabox-conditional');
    }

    public function test_the_case_screen_gets_the_term_limits_script_and_other_screens_do_not(): void
    {
        set_current_screen('case');
        (new AdminUIService())->enqueueConditionalFieldScripts('post.php');

        $this->assertTrue(wp_script_is('josworld-case-term-limits', 'enqueued'));
        global $wp_scripts;
        $limits = (string) $wp_scripts->get_data('josworld-case-term-limits', 'data');
        $this->assertStringContainsString('"domain":"1"', $limits);
        $this->assertStringContainsString('"dienst":"3"', $limits);
        wp_dequeue_script('josworld-case-term-limits');
        wp_deregister_script('josworld-case-term-limits');

        set_current_screen('team');
        (new AdminUIService())->enqueueConditionalFieldScripts('post.php');

        $this->assertFalse(wp_script_is('josworld-case-term-limits', 'enqueued'));
    }
}
