<?php

/**
 * Vacature Service — registers the `vacature` post type.
 *
 * The closed employment-type list is enforced HERE, not by the framework:
 * `select` only renders the dropdown — it does not reject an out-of-list
 * value written via WP-CLI or the Data API. The `validate` callback is the
 * enforcement: on failure, create()/update() return a WP_Error and never
 * call wp_insert_post()/wp_update_post(), so nothing is written at all.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class VacatureService
{
    /**
     * Key === label on purpose: YOOtheme's binding exposes the STORED value,
     * not the option label — a `full_time` key would print literally as
     * "full_time" on the archive. `validate` below keeps the list closed.
     *
     * @var array<string, string>
     */
    private const EMPLOYMENT_TYPES = [
        'Voltijds' => 'Voltijds',
        'Freelance' => 'Freelance',
    ];

    public function __construct(private readonly NTDST_Data_Manager $dataManager)
    {
        $this->init();
    }

    private function init(): void
    {
        $this->registerDataModel();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFields(): array
    {
        return [
            'employment_type' => [
                'type' => 'select',
                'label' => __('Type tewerkstelling', JOSWORLD_CORE_TEXTDOMAIN),
                'options' => self::EMPLOYMENT_TYPES,
                'required' => true,
                // The enforcement half of the closed list — see class
                // docblock. Must return exactly `true` to pass; any other
                // return becomes the WP_Error message.
                'validate' => static function (mixed $value) {
                    if (! array_key_exists($value, self::EMPLOYMENT_TYPES)) {
                        return sprintf(
                            /* translators: %s: the submitted (invalid) employment type value */
                            __('Ongeldig type tewerkstelling: %s', JOSWORLD_CORE_TEXTDOMAIN),
                            is_scalar($value) ? (string) $value : gettype($value),
                        );
                    }

                    return true;
                },
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFieldGroups(): array
    {
        return [
            'details' => [
                'title' => __('Vacature-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['employment_type'],
                'context' => 'normal',
                'priority' => 'high',
            ],
        ];
    }

    private function registerDataModel(): void
    {
        $this->dataManager->register('vacature', [
            'label' => __('Vacatures', JOSWORLD_CORE_TEXTDOMAIN),
            'labels' => [
                'name' => __('Vacatures', JOSWORLD_CORE_TEXTDOMAIN),
                'singular_name' => __('Vacature', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new' => __('Nieuwe vacature', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new_item' => __('Nieuwe vacature toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'edit_item' => __('Vacature bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                'view_item' => __('Vacature bekijken', JOSWORLD_CORE_TEXTDOMAIN),
                'search_items' => __('Vacatures zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found' => __('Geen vacatures gevonden', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found_in_trash' => __('Geen vacatures gevonden in prullenbak', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'has_archive' => true,
            'description' => __(
                'Vacatures en stageplekken bij Jos: werk mee aan communicatie die dichter bij de mensen staat.',
                JOSWORLD_CORE_TEXTDOMAIN
            ),
            'hierarchical' => false,
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-businessman',
            // `jobs-stages`, not `vacatures`: the archive IS the designed
            // "Jobs & stages" page the nav and footer link to. `rest_base`
            // stays `vacatures` — an API name, not a front-end URL; renaming
            // it would break consumers for no benefit.
            'rewrite' => ['slug' => 'jobs-stages'],
            'show_in_rest' => true,
            'rest_base' => 'vacatures',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'fields' => $this->getFields(),
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'use_tabs' => true,
            'tabs_context' => 'normal',
            'tabs_priority' => 'high',
            'metabox_title' => __('Vacature-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            'field_groups' => $this->getFieldGroups(),
        ]);
    }
}
