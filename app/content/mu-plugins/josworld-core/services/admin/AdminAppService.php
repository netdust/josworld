<?php

/**
 * Admin App Service — declares josworld as an app to ntdst-baseline's
 * `admin_ui` module, which then owns the menu, the page shell, the CPT
 * relocation and the menu-state filters.
 *
 * Nothing here registers a menu page. The module's `registerApp()` reads
 * this config on `admin_menu` priority 9 and does all of it; josworld
 * supplies the config and the dashboard BODY, and that is the whole
 * contract. Re-implementing any of it here would be the drift the
 * ntdst-drift-reviewer exists to catch.
 *
 * Named AdminAppService, not AdminUIService: josworld-core already has an
 * AdminUIService (metabox field JS) and ntdst-baseline has its own, so a
 * third meaning on that name would be actively misleading.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\admin;

defined('ABSPATH') || exit;

final class AdminAppService
{
    public const MENU_SLUG = 'josworld';

    /**
     * The content types this app owns, slug => menu label.
     *
     * PUBLIC so PostTypeRegistryConsistencyTest can assert every slug
     * against the real registry: a slug that drifts out of the registry
     * would silently stop appearing in the menu, and nothing else ties
     * this list to the registered CPTs.
     *
     * @var array<string, string>
     */
    public const POST_TYPES = [
        'case' => 'Cases',
        'team' => 'Teamleden',
        'vacature' => 'Vacatures',
        'tool' => 'Tools',
    ];

    /** Taxonomies that must follow their post type into the menu. */
    private const TAXONOMIES = ['case' => ['dienst' => 'Diensten']];

    public function __construct()
    {
        add_filter('ntdst/baseline/admin/apps', [$this, 'declareApp']);
    }

    /**
     * Union, not array_merge: another site-level filter may already have
     * registered an app, and a numeric-key merge would renumber it.
     *
     * @param array<string, array<string, mixed>> $apps
     * @return array<string, array<string, mixed>>
     */
    public function declareApp(array $apps): array
    {
        $apps[self::MENU_SLUG] = $this->config();

        return $apps;
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        return [
            'menu_slug' => self::MENU_SLUG,
            'menu_title' => __('Josworld', JOSWORLD_CORE_TEXTDOMAIN),
            'dashboard_title' => __('Dashboard', JOSWORLD_CORE_TEXTDOMAIN),
            'description' => __('De inhoud van deze site in één overzicht.', JOSWORLD_CORE_TEXTDOMAIN),
            'menu_icon' => 'dashicons-screenoptions',
            // Directly under Dashboard (2), so the app that replaced four
            // top-level entries sits where they used to be looked for.
            'menu_position' => 3,
            'capability' => 'edit_posts',
            'post_types' => $this->labelled(self::POST_TYPES),
            'taxonomies' => array_map([$this, 'labelled'], self::TAXONOMIES),
            'render' => [$this, 'renderDashboard'],
        ];
    }

    /**
     * Labels are translated HERE rather than in the constants, because a
     * const cannot call __() and a translation resolved at file-load time
     * would run before the textdomain is loaded.
     *
     * @param array<string, string> $map
     * @return array<string, string>
     */
    private function labelled(array $map): array
    {
        return array_map(
            static fn(string $label): string => __($label, JOSWORLD_CORE_TEXTDOMAIN),
            $map,
        );
    }

    public function renderDashboard(): void
    {
    }
}
