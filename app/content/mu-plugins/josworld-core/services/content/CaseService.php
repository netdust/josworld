<?php

/**
 * Case Service — registers the `case` post type, its `dienst` taxonomy,
 * and the case admin list columns.
 *
 * Meta keys are ALWAYS ENGLISH — a durable storage/API contract the
 * YOOtheme templates read directly. __() labels are ALWAYS DUTCH admin UI.
 * Never conflate the two.
 *
 * The framework has no `group`/`object` field type, so title+body pairs
 * (uitdaging, resultaat) are two flat fields. Optional blocks (takeaways,
 * interview) are repeaters: the ORM only writes keys present in the
 * payload, so an unused block has NO `_jw_*` row in wp_postmeta at all —
 * absent, not empty-stringed.
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
            // CLIENT / INTRO
            'client' => [
                'type' => 'text',
                'label' => __('Klant', JOSWORLD_CORE_TEXTDOMAIN),
                'required' => true,
            ],
            'tagline' => [
                'type' => 'text',
                'label' => __('Tagline', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'what_we_did' => [
                'type' => 'textarea',
                'label' => __('Wat we hebben gedaan', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'target_audience' => [
                'type' => 'textarea',
                'label' => __('Doelgroep', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // IMPACT — repeating figure + description (e.g. "+7 mio")
            'impact' => [
                'type' => 'repeater',
                'label' => __('Impact', JOSWORLD_CORE_TEXTDOMAIN),
                'button_text' => __('Impactcijfer toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'sub_fields' => [
                    'figure' => [
                        'type' => 'text',
                        'label' => __('Cijfer', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'description' => [
                        'type' => 'text',
                        'label' => __('Omschrijving', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                ],
            ],

            // UITDAGING
            'challenge_title' => [
                'type' => 'text',
                'label' => __('Uitdaging — titel', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'challenge_body' => [
                'type' => 'textarea',
                'label' => __('Uitdaging — tekst', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // RESULTAAT
            'result_title' => [
                'type' => 'text',
                'label' => __('Resultaat — titel', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'result_body' => [
                'type' => 'textarea',
                'label' => __('Resultaat — tekst', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // TAKEAWAYS — optional. `condition` is PRESENTATION-ONLY: the
            // repeater's inputs are never removed from the DOM, so a value
            // entered while hidden still round-trips on save. `has_takeaways`
            // is a UI toggle, never read by the save path — it decides only
            // whether the editor SAW the block, not whether values persist.
            'has_takeaways' => [
                'type' => 'boolean',
                'label' => __('Heeft take-aways?', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'takeaways' => [
                'type' => 'repeater',
                'label' => __('Take-aways', JOSWORLD_CORE_TEXTDOMAIN),
                'button_text' => __('Take-away toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'condition' => ['has_takeaways' => true],
                'sub_fields' => [
                    'title' => [
                        'type' => 'text',
                        'label' => __('Titel', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'body' => [
                        'type' => 'textarea',
                        'label' => __('Tekst', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                ],
            ],

            // TESTIMONIAL (quote / name / role)
            'testimonial_quote' => [
                'type' => 'textarea',
                'label' => __('Testimonial — quote', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'testimonial_name' => [
                'type' => 'text',
                'label' => __('Testimonial — naam', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'testimonial_role' => [
                'type' => 'text',
                'label' => __('Testimonial — functie', JOSWORLD_CORE_TEXTDOMAIN),
            ],

            // INTERVIEW — optional; same `condition`/round-trip contract as
            // takeaways above.
            'has_interview' => [
                'type' => 'boolean',
                'label' => __('Heeft interview?', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'interview' => [
                'type' => 'repeater',
                'label' => __('Interview', JOSWORLD_CORE_TEXTDOMAIN),
                'button_text' => __('Interviewblok toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'condition' => ['has_interview' => true],
                'sub_fields' => [
                    'title' => [
                        'type' => 'text',
                        'label' => __('Titel', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                    'body' => [
                        'type' => 'textarea',
                        'label' => __('Tekst', JOSWORLD_CORE_TEXTDOMAIN),
                    ],
                ],
            ],

            // TEAM — top-level, not nested in the impact repeater: repeater
            // sub_fields support only text/textarea/select/number/date/url,
            // no nested `relation`. `reverse_label` is explicit because the
            // framework's fallback is hardcoded English ("Featured in …"),
            // which this Dutch admin forbids.
            'team' => [
                'type' => 'relation',
                'label' => __('Team', JOSWORLD_CORE_TEXTDOMAIN),
                'post_type' => 'team',
                'multiple' => true,
                'reverse_label' => __('Cases', JOSWORLD_CORE_TEXTDOMAIN),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFieldGroups(): array
    {
        return [
            'intro' => [
                'title' => __('Klant & intro', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['client', 'tagline', 'what_we_did', 'target_audience'],
                'context' => 'normal',
                'priority' => 'high',
            ],
            'impact' => [
                'title' => __('Impact', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['impact'],
                'context' => 'normal',
                'priority' => 'high',
            ],
            'narrative' => [
                'title' => __('Uitdaging & resultaat', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['challenge_title', 'challenge_body', 'result_title', 'result_body'],
                'context' => 'normal',
                'priority' => 'default',
            ],
            'takeaways' => [
                'title' => __('Take-aways', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['has_takeaways', 'takeaways'],
                'context' => 'normal',
                'priority' => 'default',
            ],
            'testimonial' => [
                'title' => __('Testimonial', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['testimonial_quote', 'testimonial_name', 'testimonial_role'],
                'context' => 'normal',
                'priority' => 'default',
            ],
            'interview' => [
                'title' => __('Interview', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['has_interview', 'interview'],
                'context' => 'normal',
                'priority' => 'default',
            ],
            'team' => [
                'title' => __('Team', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['team'],
                'context' => 'side',
                'priority' => 'default',
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
            'use_tabs' => true,
            'tabs_context' => 'normal',
            'tabs_priority' => 'high',
            'metabox_title' => __('Case-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            'field_groups' => $this->getFieldGroups(),
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
                $newColumns['jw_impact_count'] = __('Impact', JOSWORLD_CORE_TEXTDOMAIN);
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

            case 'jw_impact_count':
                $impact = $this->dataManager->get('case')->getMeta($postId, 'impact', [], 'any');
                $count = is_array($impact) ? count($impact) : 0;
                echo $count > 0
                    ? '<strong>' . esc_html((string) $count) . '</strong>'
                    : '<span style="color:#999">—</span>';
                break;
        }
    }
}
