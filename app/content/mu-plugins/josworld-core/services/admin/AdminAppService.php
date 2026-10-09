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
    private const TAXONOMIES = ['case' => ['domain' => 'Domeinen', 'dienst' => 'Diensten']];

    private ?ActivitySource $bookings = null;
    private ?ActivitySource $submissions = null;

    public function __construct()
    {
        add_filter('ntdst/baseline/admin/apps', [$this, 'declareApp']);
    }

    /**
     * Swap the activity feeds.
     *
     * NOT constructor injection, deliberately: ntdst-core's container resolves
     * every non-builtin type hint BEFORE it considers a default value
     * (`Container::resolveParameters()`), so a `?ActivitySource $x = null`
     * parameter makes it try to resolve the INTERFACE and throw
     * "Service … ActivitySource not found" the moment the service is wired.
     * A setter keeps the sources swappable without asking the container to
     * resolve something it cannot.
     */
    public function useSources(ActivitySource $bookings, ActivitySource $submissions): void
    {
        $this->bookings = $bookings;
        $this->submissions = $submissions;
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

    /**
     * One tile per content type.
     *
     * Public so the unit tier can assert the data without WordPress and
     * without parsing markup — the counts and the capability decision are
     * the part worth testing; the `<div>`s around them are not.
     *
     * @return list<array{label: string, count: int, drafts: int, list_url: string, new_url: string|null}>
     */
    public function tiles(): array
    {
        $tiles = [];

        foreach (self::POST_TYPES as $postType => $label) {
            // wp_count_posts() returns an object whose properties are the
            // registered statuses, so a status a type never uses is simply
            // absent rather than zero.
            $counts = wp_count_posts($postType);

            $tiles[] = [
                'label' => __($label, JOSWORLD_CORE_TEXTDOMAIN),
                'count' => (int) ($counts->publish ?? 0),
                'drafts' => (int) ($counts->draft ?? 0),
                'list_url' => self::listUrl($postType),
                'new_url' => $this->canCreate($postType)
                    ? admin_url('post-new.php?post_type=' . $postType)
                    : null,
            ];
        }

        return $tiles;
    }

    /**
     * The list screen for a content type — the ONE place that URL is built.
     *
     * Both the tile and the activity panels' "all of them" footers link here,
     * and a second spelling of the same URL is how the two drift apart.
     */
    public static function listUrl(string $postType): string
    {
        return admin_url('edit.php?post_type=' . $postType);
    }

    /**
     * Hiding the link is a courtesy, not a control — WordPress enforces the
     * capability on post-new.php regardless. It is read off the post type's
     * OWN `create_posts` cap rather than assumed to be `edit_posts`, because
     * a type mapping its capabilities gets a different one.
     */
    private function canCreate(string $postType): bool
    {
        $object = get_post_type_object($postType);
        $capability = $object->cap->create_posts ?? 'edit_posts';

        return current_user_can($capability);
    }

    /**
     * The dashboard BODY only. ntdst-baseline's Shell has already opened
     * `.ntdst-admin__page`, the header and `.ntdst-admin__body` around this,
     * and closes them after — see AdminUIService::registerApp().
     *
     * Every class here is the shell's own (`assets/css/admin-ui.css`); this
     * app ships no stylesheet, which is the entire point of the module.
     */
    public function renderDashboard(): void
    {
        echo '<div class="ntdst-admin__tiles">';

        foreach ($this->tiles() as $tile) {
            echo '<div class="ntdst-admin__tile">';
            // The count and the label are one link to the list; "Nieuw" stays
            // a separate action, so a tile offers both destinations.
            echo '<a class="ntdst-admin__tile-main" href="' . esc_url($tile['list_url']) . '">';
            echo '<div class="ntdst-admin__tile-value">' . esc_html((string) $tile['count']) . '</div>';
            echo '<div class="ntdst-admin__tile-label">' . esc_html($tile['label']);

            if ($tile['drafts'] > 0) {
                echo '<span class="ntdst-admin__hint">' . esc_html(sprintf(
                    /* translators: %d is a number of draft items. */
                    _n('%d concept', '%d concepten', $tile['drafts'], JOSWORLD_CORE_TEXTDOMAIN),
                    $tile['drafts'],
                )) . '</span>';
            }

            echo '</div></a>';

            if ($tile['new_url'] !== null) {
                echo '<a class="ntdst-admin__tile-link" href="' . esc_url($tile['new_url']) . '">'
                    . esc_html__('Nieuw', JOSWORLD_CORE_TEXTDOMAIN) . '</a>';
            }

            echo '</div>';
        }

        echo '</div>';

        $this->renderActivity();
    }

    /**
     * The two activity panels, side by side.
     *
     * Every field printed here is visitor-authored — a booker and a form
     * submitter both type their own name — so every one of them goes through
     * `esc_html()`. That is the mitigation for the plan's T1, not a
     * formality.
     */
    private function renderActivity(): void
    {
        echo '<div class="ntdst-admin__split">';

        $this->renderPanel(
            __('Laatste boekingen', JOSWORLD_CORE_TEXTDOMAIN),
            ($this->bookings ??= new BookingRepository())->latest(),
            __('Nog geen boekingen.', JOSWORLD_CORE_TEXTDOMAIN),
            static fn(array $row): array => [
                (string) ($row['who'] ?? ''),
                trim((string) ($row['event'] ?? '') . ' · ' . (string) ($row['when'] ?? ''), ' ·'),
            ],
        );

        $this->renderPanel(
            __('Laatste inzendingen', JOSWORLD_CORE_TEXTDOMAIN),
            ($this->submissions ??= new SubmissionRepository())->latest(),
            __('Nog geen inzendingen.', JOSWORLD_CORE_TEXTDOMAIN),
            static fn(array $row): array => [
                (string) ($row['who'] ?? ''),
                trim((string) ($row['form'] ?? '') . ' · ' . (string) ($row['when'] ?? ''), ' ·'),
            ],
        );

        echo '</div>';
    }

    /**
     * One panel. Its empty state is rendered inside its own panel, so an empty
     * source never blanks the panel beside it — which is the normal case here:
     * there are no form submissions yet while bookings already exist.
     *
     * @param list<array<string, mixed>> $rows
     * @param callable(array<string, mixed>): array{0: string, 1: string} $line
     */
    private function renderPanel(string $title, array $rows, string $emptyText, callable $line): void
    {
        echo '<div class="ntdst-admin__panel">';
        echo '<div class="ntdst-admin__panel-head">';
        echo '<span class="ntdst-admin__panel-title">' . esc_html($title) . '</span>';
        echo '</div>';

        if ($rows === []) {
            echo '<div class="ntdst-admin__empty">';
            echo '<p class="ntdst-admin__empty-text">' . esc_html($emptyText) . '</p>';
            echo '</div></div>';

            return;
        }

        echo '<div class="ntdst-admin__list">';

        foreach ($rows as $row) {
            [$main, $meta] = $line($row);
            $url = (string) ($row['url'] ?? '');

            echo '<div class="ntdst-admin__row">';
            echo '<a class="ntdst-admin__row-title" href="' . esc_url($url) . '">' . esc_html($main) . '</a>';
            echo '<span class="ntdst-admin__row-meta">' . esc_html($meta) . '</span>';
            echo '</div>';
        }

        echo '</div></div>';
    }
}
