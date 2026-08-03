<?php

declare(strict_types=1);

/**
 * Relation Field Service
 * Provides API endpoints for post searching and reverse relationship metaboxes
 *
 * @package NTDST Core
 * @version 1.0.0
 */

defined('ABSPATH') || exit;

final class NTDST_RelationField implements NTDST_Service_Meta
{
    public static function metadata(): array
    {
        return [
            'name' => 'Relation Fields',
            'description' => 'API endpoints for relation field autocomplete and reverse relationships',
            'admin_only' => true,
            'enabled' => true,
            'priority' => 5,
        ];
    }

    public function __construct(private readonly NTDST_Theme $theme)
    {
        $this->init();
    }

    private function init(): void
    {
        // Note: Using existing search_posts endpoint from Endpoints.php
        // No need to register duplicate API endpoint

        // Register reverse relationship metaboxes
        add_action('add_meta_boxes', [$this, 'registerReverseRelationshipMetaboxes'], 20);
    }

    /**
     * Register reverse relationship metaboxes
     * Shows "Featured in Exhibitions" on Artist/Artwork pages, etc.
     */
    public function registerReverseRelationshipMetaboxes(): void
    {
        // Get all registered models with relation fields
        $models_with_relations = $this->getModelsWithRelations();

        foreach ($models_with_relations as $source_post_type => $relation_fields) {
            foreach ($relation_fields as $field_name => $field_config) {
                $target_post_type = $field_config['post_type'] ?? null;

                if (!$target_post_type || !post_type_exists($target_post_type)) {
                    continue;
                }

                // Add metabox to target post type showing where it's referenced
                $metabox_title = $field_config['reverse_label'] ?? sprintf(
                    'Featured in %s',
                    ucwords(str_replace('_', ' ', $source_post_type)) . 's',
                );

                add_meta_box(
                    "ntdst_reverse_{$source_post_type}_{$field_name}",
                    $metabox_title,
                    [$this, 'renderReverseRelationshipMetabox'],
                    $target_post_type,
                    'side',
                    'default',
                    [
                        'source_post_type' => $source_post_type,
                        'field_name' => $field_name,
                        'target_post_type' => $target_post_type,
                    ],
                );
            }
        }
    }

    /**
     * Get all registered models with relation fields.
     *
     * Uses NTDST_Data_Manager::isRegistered() so iterating over public
     * post types doesn't auto-create phantom model entries for built-in
     * types (post, page, sfwd-courses, etc.) that have no NTDST schema.
     */
    private function getModelsWithRelations(): array
    {
        $models = [];
        $data_manager = ntdst_data();

        // Get all registered post types
        $post_types = get_post_types(['public' => true], 'names');

        foreach ($post_types as $post_type) {
            if (!$data_manager->isRegistered($post_type)) {
                continue;
            }

            try {
                $model = $data_manager->get($post_type);
                $schema = $model->getSchema();
                if (empty($schema)) {
                    continue;
                }

                foreach ($schema as $field_name => $field_config) {
                    if (is_array($field_config) && ($field_config['type'] ?? '') === 'relation') {
                        $models[$post_type][$field_name] = $field_config;
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $models;
    }

    /**
     * Render reverse relationship metabox
     */
    public function renderReverseRelationshipMetabox(\WP_Post $post, array $metabox): void
    {
        $source_post_type = $metabox['args']['source_post_type'];
        $field_name = $metabox['args']['field_name'];

        // Find all posts of source_post_type that reference this post
        $referring_posts = $this->findReferringPosts($post->ID, $source_post_type, $field_name);

        if (empty($referring_posts)) {
            echo '<p class="ntdst-reverse-relations-empty" style="padding: 12px; color: #646970; font-style: italic; margin: 0;">Not featured in any ' . esc_html($source_post_type) . 's yet.</p>';
            return;
        }

        echo '<ul class="ntdst-reverse-relations-list" style="margin: 0; padding: 0;">';
        foreach ($referring_posts as $referring_post) {
            $edit_url = get_edit_post_link($referring_post->ID);
            echo '<li style="margin: 0; padding: 8px 12px; border-bottom: 1px solid #f0f0f1;">';
            echo '<a href="' . esc_url($edit_url) . '">' . esc_html($referring_post->post_title) . '</a>';
            echo '</li>';
        }
        echo '</ul>';

        echo '<style>
            .ntdst-reverse-relations-list li:last-child { border-bottom: none; }
        </style>';
    }

    /**
     * Find posts that reference the given post ID in a relation field.
     *
     * TWO DEFECTS FIXED HERE (josworld, 2026-08-03) — both made this method
     * return an empty set, which renders as an empty reverse metabox and is
     * indistinguishable from "this item genuinely has no referrers".
     *
     * 1. META PREFIX (affected every ntdst-core copy). The lookup queried
     *    `pm.meta_key = $field_name` with the RAW field name. A model
     *    registered with a `meta_prefix` (a first-class register() arg —
     *    Data.php:52,71) stores that field as `<prefix><field>`, so the
     *    query matched nothing. Verified live: a `case` model with
     *    meta_prefix `_jw_` and a `team` relation stores
     *    `_jw_team => a:2:{i:0;i:11;i:1;i:22;}`, while this method looked
     *    for meta_key `team`. Both spellings are now tried — the same
     *    tolerance Data.php's own read path already has
     *    (`$meta[$metaKey] ?? $meta[$field]`, Data.php:1477).
     *
     * 2. STORAGE FORMAT (a regression in the newer copies). The previous
     *    revision of this docblock asserted "Data.php stores relation
     *    values as JSON arrays ([6, 7]), not PHP serialized" and replaced a
     *    working serialized-pattern LIKE with JSON-shaped ones. That
     *    premise is false: relation values go through update_post_meta() on
     *    a PHP array, so WordPress serializes them via maybe_serialize().
     *    Data.php never json_encode()s a relation value — grep confirms it.
     *    The older ntdst-core revision (still deployed on acerta,
     *    ludoluykx, netdust, rossi) had this right with `%i:<id>;%`.
     *
     * Both formats are now accepted: serialized (what WordPress actually
     * writes) AND JSON (in case any caller ever hand-writes that shape).
     * The SQL only NARROWS the candidate set; correctness comes from the
     * exact in_array() check below, so a loose LIKE costs a few extra rows,
     * never a false positive.
     */
    private function findReferringPosts(int $post_id, string $post_type, string $field_name): array
    {
        global $wpdb;

        $meta_keys = array_unique([$field_name, $this->prefixedMetaKey($post_type, $field_name)]);
        $key_placeholders = implode(', ', array_fill(0, count($meta_keys), '%s'));

        // Candidate narrowing only — see the exact verification below.
        // Serialized: a:2:{i:0;i:11;i:1;i:22;} -> `i:11;`
        // JSON:       [11,22]                  -> `[11,`, `,11,`, `,11]`, `[11]`
        $params = array_merge(
            [$post_type],
            $meta_keys,
            [
                '%i:' . $post_id . ';%',    // PHP-serialized member (WP's real format)
                '[' . $post_id . ']',       // JSON single-element
                '[' . $post_id . ',%',      // JSON first
                '%,' . $post_id . ',%',     // JSON middle
                '%,' . $post_id . ']',      // JSON last
            ],
        );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $key_placeholders is built from array_fill('%s'), never from input.
        $sql = $wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND p.post_status = 'publish'
            AND pm.meta_key IN ({$key_placeholders})
            AND (
                pm.meta_value LIKE %s
                OR pm.meta_value LIKE %s
                OR pm.meta_value LIKE %s
                OR pm.meta_value LIKE %s
                OR pm.meta_value LIKE %s
            )
            ORDER BY p.post_title ASC",
            $params,
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $candidates = $wpdb->get_results($sql) ?: [];

        // Exact verification — guards against a LIKE false positive (ID 6
        // matching inside ID 60) and against unexpected meta shapes.
        $matches = [];
        foreach ($candidates as $candidate) {
            $ids = [];
            foreach ($meta_keys as $meta_key) {
                $raw = get_post_meta($candidate->ID, $meta_key, true);
                if ($raw === '' || $raw === null) {
                    continue;
                }
                $ids = is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);
                if ($ids !== []) {
                    break;
                }
            }

            if (in_array($post_id, array_map('intval', (array) $ids), true)) {
                $matches[] = $candidate;
            }
        }

        return $matches;
    }

    /**
     * The meta_key a model actually writes for one of its fields.
     *
     * A model registered with a `meta_prefix` (Data.php:52,71) stores
     * `<prefix><field>`; one registered without stores the bare field name.
     * Resolved through the registered model rather than a hardcoded
     * convention, so this stays correct if the prefixing rule ever changes.
     *
     * Falls back to the bare field name when the model is not registered
     * (or the Data API is unavailable) — the caller tries both spellings
     * anyway, so a wrong guess here costs one redundant meta_key in an IN(),
     * never a missed match.
     */
    private function prefixedMetaKey(string $post_type, string $field_name): string
    {
        if (!function_exists('ntdst_data')) {
            return $field_name;
        }

        $data = ntdst_data();
        if (!$data->isRegistered($post_type)) {
            return $field_name;
        }

        return $data->get($post_type)->getMetaPrefix() . $field_name;
    }
}

// Auto-initialize when theme is ready
add_action('after_setup_theme', function () {
    // Only initialize if Theme is available
    if (class_exists('NTDST_Theme')) {
        $theme = ntdst_get(NTDST_Theme::class);
        if ($theme) {
            new NTDST_RelationField($theme);
        }
    }
}, 20); // Priority 20 to ensure theme is fully initialized
