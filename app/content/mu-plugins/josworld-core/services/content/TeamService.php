<?php

/**
 * Team Service — registers the `team` post type.
 *
 * Photo is the post thumbnail: the framework has no single-image field
 * type.
 *
 * No public single view, but NOT via registration flags — WP has no
 * "archive yes, singles no" flag, and `public => false` would also hide
 * `team` from YOOtheme's source enumeration. closeSingleView()
 * 301-redirects singles to the /team/ archive instead.
 *
 * REST tripwire: the public flags do not gate REST. name + photo are
 * readable unauthenticated today; email/phone stay private ONLY because
 * nothing calls register_meta() for them — registering that meta with
 * `show_in_rest => true` exposes PII with no other code change. See
 * tests/Integration/TeamRestExposureTest.php.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class TeamService
{
    public function __construct(private readonly NTDST_Data_Manager $dataManager)
    {
        $this->init();
    }

    private function init(): void
    {
        $this->registerDataModel();
        $this->closeSingleView();
    }

    /**
     * Close the per-member single view, keeping the archive open.
     *
     * 301 rather than 404: a leaked member URL should land on the archive,
     * and crawlers should stop asking. wp_safe_redirect() directly —
     * ntdst-core 5.0 removed Pages::redirect(), and Response::redirect()
     * carries no status argument, so the 301 needs WordPress's own call.
     * The target stays host-restricted.
     */
    private function closeSingleView(): void
    {
        ntdst_pages()->single('team', static function (): void {
            $archive = get_post_type_archive_link('team');

            // Fall back to the home page rather than returning: returning
            // would let the single render, which is the one outcome this
            // method exists to prevent.
            wp_safe_redirect(
                is_string($archive) && $archive !== '' ? $archive : home_url('/'),
                301
            );
            exit;
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFields(): array
    {
        return [
            'role' => [
                'type' => 'text',
                'label' => __('Functie', JOSWORLD_CORE_TEXTDOMAIN),
                'required' => true,
            ],
            // The archive card shows bio; the contact card shows role —
            // both are needed. A field rather than post content: there is
            // no editor (`supports`), and fields are what the YOOtheme
            // bridge exposes as bindable properties.
            'bio' => [
                'type' => 'textarea',
                'label' => __('Korte bio', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'email' => [
                'type' => 'email',
                'label' => __('E-mailadres', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            'phone' => [
                'type' => 'text',
                'label' => __('Telefoonnummer', JOSWORLD_CORE_TEXTDOMAIN),
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
                'title' => __('Teamlid-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
                'fields' => ['role', 'bio', 'email', 'phone'],
                'context' => 'normal',
                'priority' => 'high',
            ],
        ];
    }

    private function registerDataModel(): void
    {
        $this->dataManager->register('team', [
            'label' => __('Teamleden', JOSWORLD_CORE_TEXTDOMAIN),
            'labels' => [
                'name' => __('Teamleden', JOSWORLD_CORE_TEXTDOMAIN),
                'singular_name' => __('Teamlid', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new' => __('Nieuw teamlid', JOSWORLD_CORE_TEXTDOMAIN),
                'add_new_item' => __('Nieuw teamlid toevoegen', JOSWORLD_CORE_TEXTDOMAIN),
                'edit_item' => __('Teamlid bewerken', JOSWORLD_CORE_TEXTDOMAIN),
                'view_item' => __('Teamlid bekijken', JOSWORLD_CORE_TEXTDOMAIN),
                'search_items' => __('Teamleden zoeken', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found' => __('Geen teamleden gevonden', JOSWORLD_CORE_TEXTDOMAIN),
                'not_found_in_trash' => __('Geen teamleden gevonden in prullenbak', JOSWORLD_CORE_TEXTDOMAIN),
            ],
            // Public, single view closed by closeSingleView()'s redirect —
            // `public => false` would hide `team` from YOOtheme's source
            // enumeration (it filters on `public => true`), and no flag
            // combination gives "archive yes, singles no".
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => 'team',
            'has_archive' => true,
            'hierarchical' => false,
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-groups',
            'rewrite' => ['slug' => 'team', 'with_front' => false],
            'show_in_rest' => true,
            'rest_base' => 'team',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'fields' => $this->getFields(),
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'use_tabs' => true,
            'tabs_context' => 'normal',
            'tabs_priority' => 'high',
            'metabox_title' => __('Teamlid-gegevens', JOSWORLD_CORE_TEXTDOMAIN),
            'field_groups' => $this->getFieldGroups(),
        ]);
    }
}
