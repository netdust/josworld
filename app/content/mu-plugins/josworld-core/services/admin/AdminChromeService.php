<?php

/**
 * Gives the screens this app owns the same chrome as its dashboard.
 *
 * TWO classes on <body>, and the split matters:
 *
 *  - `ntdst-admin` is ntdst-baseline's own, and it is here for ONE reason:
 *    its token block (`--ntdst-admin-*`) is declared on that selector rather
 *    than on `:root`. Putting the class on <body> makes the whole palette
 *    cascade into WordPress' own markup, so this project never carries a
 *    second copy of those colours — the drift the ntdst-drift-reviewer
 *    exists to catch. The one rule on that selector that does NOT belong on
 *    <body> (`margin-right: 20px`) is undone in admin-chrome.css.
 *  - `jw-admin-chrome` is ours, and everything in that stylesheet is scoped
 *    to it, so a screen we do not own is never restyled.
 *
 * A tweak, not a redesign: no layout moves and no WordPress control is
 * replaced. baseline's stylesheet is already enqueued on these screens by
 * AdminUIService::enqueueChrome(), which matches on the app's post types.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\admin;

defined('ABSPATH') || exit;

final class AdminChromeService
{
    private const HANDLE = 'josworld-admin-chrome';

    public function __construct()
    {
        add_filter('admin_body_class', [$this, 'flagOwnScreens']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function flagOwnScreens(string $classes): string
    {
        if (! $this->ownsCurrentScreen()) {
            return $classes;
        }

        return trim($classes . ' ntdst-admin jw-admin-chrome');
    }

    public function enqueue(): void
    {
        if (! $this->ownsCurrentScreen()) {
            return;
        }

        // Baseline's own sheet is already on these screens; ask for it by name
        // anyway so this one never lands alone if that ever changes.
        do_action('ntdst/baseline/admin/enqueue');

        $file = dirname(__DIR__, 2) . '/assets/css/admin-chrome.css';
        if (! is_file($file)) {
            return;
        }

        wp_enqueue_style(
            self::HANDLE,
            plugins_url('assets/css/admin-chrome.css', dirname(__DIR__, 2) . '/josworld-core.php'),
            ['ntdst-admin-ui'],
            (string) filemtime($file),
        );
    }

    /**
     * The app's own post types on any of their screens — list, edit, new and
     * the taxonomy term screens — plus the app's own pages, which need the
     * same sheet for the tile links and the panel row.
     */
    private function ownsCurrentScreen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null) {
            return false;
        }

        if (in_array((string) $screen->post_type, array_keys(AdminAppService::POST_TYPES), true)) {
            return true;
        }

        return str_contains((string) $screen->id, 'page_' . AdminAppService::MENU_SLUG);
    }
}
