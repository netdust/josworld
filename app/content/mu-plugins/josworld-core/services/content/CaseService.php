<?php

/**
 * Case Service — registers the `case` post type, its `dienst` taxonomy,
 * and the case admin list columns.
 *
 * Meta keys are ALWAYS ENGLISH — a durable storage/API contract the
 * YOOtheme templates read directly. __() labels are ALWAYS DUTCH admin UI.
 * Never conflate the two.
 *
 * The model is its card: the one field a grid or CTA on another page needs
 * to show a case. The narrative (uitdaging, resultaat, testimonial, team…)
 * is built in the YOOtheme builder now, not stored here.
 *
 * The `dienst` taxonomy is declared WITH the model via register()'s
 * `taxonomies` key — no direct register_taxonomy() call anywhere in
 * josworld-core (it converges on NTDST_Data_Manager::registerTaxonomy()).
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class CaseService
{
    public function __construct(private readonly NTDST_Data_Manager $dataManager)
    {
        $this->init();
    }

    private function init(): void
    {
        $this->registerDataModel();
        $this->registerAdminColumns();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFields(): array
    {
        return [
            'client' => [
                'type' => 'text',
                'label' => __('Klant', JOSWORLD_CORE_TEXTDOMAIN),
                'required' => true,
            ],
        ];
    }

    private function registerDataModel(): void
    {
        $this->dataManager->register('case', [
            'label' => __('Cases', JOSWORLD_CORE_TEXTDOMAIN),
            'labels' => [
                'name' => __('Cases', JOSWORLD_CORE_TEXTDOMAIN),
                'singular_name' => __('Case', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new' => __('Nieuwe case', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new_item' => __('Nieuwe case toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'edit_item' => __('Case bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                'view_item' => __('Case bekijken', JOSWORLD_CORE_TEXTDOMAIN),
                'search_items' => __('Cases zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found' => __('Geen cases gevonden', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found_in_trash' => __('Geen cases gevonden in prullenbak', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon' => 'dashicons-portfolio',
            'rewrite' => ['slug' => 'ons-werk'],
            'show_in_rest' => true,
            'rest_base' => 'cases',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'fields' => $this->getFields(),
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'metabox_title' => __('Case-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            // `dienst`, case-only, declared with the model — see class
            // docblock (no direct register_taxonomy() call).
            'taxonomies' => [
                'dienst' => [
                    'hierarchical' => false,
                    'labels' => [
                        'name' => __('Diensten', JOSWORLD_CORE_TEXTDOMAIN),
                        'singular_name' => __('Dienst', JOSWORLD_CORE_TEXTDOMAIN),
                        'search_items' => __('Diensten zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                        'all_items' => __('Alle diensten', JOSWORLD_CORE_TEXTDOMAIN),
                        'edit_item' => __('Dienst bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                        'update_item' => __('Dienst bijwerken', JOSWORLD_CORE_TEXTDOMAIN),
                        'add_new_item' => __('Nieuwe dienst toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                        'new_item_name' => __('Nieuwe dienstnaam', JOSWORLD_CORE_TEXTDOMAIN),
                        'menu_name' => __('Diensten', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'show_admin_column' => true,
                    'show_in_rest' => true,
                    'rewrite' => ['slug' => 'dienst'],
                ],
            ],
        ]);
    }

    /**
     * Columns read through the model's getMeta() (status 'any' — the admin
     * list shows drafts too), which resolves the `_jw_` prefix; raw
     * get_post_meta() would silently read the wrong (unprefixed) key. The
     * `jw_` column-key prefix keeps the keys from colliding with a native
     * WP column or another plugin's.
     */
    private function registerAdminColumns(): void
    {
        add_filter('manage_case_posts_columns', [$this, 'addAdminColumns']);
        add_action('manage_case_posts_custom_column', [$this, 'renderAdminColumn'], 10, 2);
    }

    /**
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addAdminColumns(array $columns): array
    {
        $newColumns = [];

        foreach ($columns as $key => $label) {
            $newColumns[$key] = $label;

            if ($key === 'title') {
                $newColumns['jw_client'] = __('Klant', JOSWORLD_CORE_TEXTDOMAIN);
                $newColumns['jw_diensten'] = __('Diensten', JOSWORLD_CORE_TEXTDOMAIN);
            }
        }

        return $newColumns;
    }

    public function renderAdminColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'jw_client':
                $client = $this->dataManager->get('case')->getMeta($postId, 'client', '', 'any');
                echo $client !== '' ? esc_html((string) $client) : '<span style="color:#999">—</span>';
                break;

            case 'jw_diensten':
                $terms = get_the_terms($postId, 'dienst');
                if (! is_array($terms) || $terms === []) {
                    echo '<span style="color:#999">—</span>';
                    break;
                }
                echo esc_html(implode(', ', array_map(static fn ($term) => $term->name, $terms)));
                break;
        }
    }
}
