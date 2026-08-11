<?php

/**
 * Admin UI Service — enqueues the metabox field JS (relation autocomplete,
 * gallery picker, repeater sort, conditional fields) on josworld's own CPT
 * edit screens.
 *
 * Exists because ntdst-core's enqueue_metabox_scripts() only enqueues the
 * ACTIVE THEME's `assets/dist/theme-services.js` — josworld's theme ships
 * no such build, so relation/repeater/gallery fields would render as inert
 * markup. Owning the enqueue in the plugin keeps the content model
 * independent of the theme's build tooling.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\admin;

defined('ABSPATH') || exit;

final class AdminUIService
{
    /**
     * The post-type slugs the metabox field JS is scoped to. PUBLIC so
     * PostTypeRegistryConsistencyTest can assert every slug against the
     * real registry — nothing else ties this list to the registered CPTs,
     * and a mismatched slug silently stops the enqueue on that screen with
     * no test failure anywhere.
     *
     * @var list<string>
     */
    public const POST_TYPES = ['case', 'team', 'vacature', 'tool'];

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueMetaboxFieldScripts']);
    }

    /**
     * Scoped to post.php/post-new.php AND to POST_TYPES, so unrelated admin
     * screens (dashboard, a native `post` edit screen) never receive the JS.
     */
    public function enqueueMetaboxFieldScripts(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (! $screen || ! in_array($screen->post_type, self::POST_TYPES, true)) {
            return;
        }

        wp_enqueue_script(
            'josworld-metabox-fields',
            plugins_url('assets/js/metabox-fields.js', dirname(__DIR__, 2) . '/josworld-core.php'),
            ['jquery', 'jquery-ui-sortable'],
            null,
            true,
        );

        $this->enqueueConditionalFieldScript($screen->post_type);
    }

    /**
     * The condition map is read off the REGISTERED model's schema, never
     * duplicated here — a future field gaining a `condition` option needs
     * no change on this side.
     *
     * DEGRADE-TO-VISIBLE: the PHP renderer knows nothing of `condition` and
     * renders every field visible; this script only ADDS `display:none` at
     * runtime. A CSS-first hide-by-default would make conditional fields
     * unreachable without JS — deliberately not done. Localizes an empty
     * map when there are no conditional fields; the JS no-ops on it.
     */
    private function enqueueConditionalFieldScript(string $postType): void
    {
        wp_enqueue_script(
            'josworld-metabox-conditional',
            plugins_url('assets/js/metabox-conditional.js', dirname(__DIR__, 2) . '/josworld-core.php'),
            ['jquery', 'josworld-metabox-fields'],
            null,
            true,
        );

        wp_localize_script('josworld-metabox-conditional', 'josworldConditionalFields', [
            'conditions' => $this->getConditionMap($postType),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getConditionMap(string $postType): array
    {
        if (! function_exists('ntdst_data')) {
            return [];
        }

        $schema = ntdst_data()->get($postType)->getSchema();

        $conditions = [];
        foreach ($schema as $fieldName => $fieldConfig) {
            if (is_array($fieldConfig) && isset($fieldConfig['condition']) && is_array($fieldConfig['condition'])) {
                $conditions[$fieldName] = $fieldConfig['condition'];
            }
        }

        return $conditions;
    }
}
