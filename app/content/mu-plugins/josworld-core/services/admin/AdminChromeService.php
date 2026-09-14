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

        // Priority 9999 on `submenu_file`, which WordPress applies at
        // menu-header.php:53 — after it has already filtered `parent_file`
        // (:43) and immediately before `_wp_menu_output()` (:302) renders the
        // menu. Writing the globals HERE is what makes the Josworld menu stay
        // open on a moved CPT: the `parent_file` filter alone was measured
        // returning `josworld` and the menu still rendered
        // `wp-not-current-submenu`, intermittently. This asserts the state at
        // the last point before it is read instead of asking for it earlier.
        add_action('admin_menu', [$this, 'holdTheMenuOpen'], 9999);
    }

    public function flagOwnScreens(string $classes): string
    {
        if (! $this->ownsCurrentScreen()) {
            return $classes;
        }

        return trim($classes . ' ntdst-admin jw-admin-chrome');
    }

    /**
     * Keep the Josworld menu open on the content types it owns.
     *
     * The supported route is the `parent_file` filter, and ntdst-baseline
     * already uses it. On `edit.php` screens it does not stick: the filter was
     * measured returning `josworld` where `menu-header.php:43` reads it, and
     * the strict comparison at `:53` was true, yet `_wp_menu_output()` still
     * rendered `wp-not-current-submenu` — and by `:308` the global held the
     * unfiltered value again. The taxonomy screens are unaffected, and the
     * behaviour is intermittent, so something in this site's plugin set is
     * racing for that global.
     *
     * Index 4 of a `$menu` row is its class string, which `_wp_menu_output()`
     * appends verbatim. Writing the open-state classes there states the
     * outcome instead of competing for the input. `admin_menu` at 9999 runs
     * after every menu has registered, including baseline's own relocation.
     */
    public function holdTheMenuOpen(): void
    {
        // `admin_menu` fires while the menu is being BUILT, before the screen
        // object exists, so `get_current_screen()` is null here. The post type
        // is read off the request instead — the same fallback baseline's own
        // `screenContext()` keeps for this reason. Read-only routing input:
        // it selects a menu class and writes nothing.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $postType = sanitize_key($_GET['post_type'] ?? '');

        if (! in_array($postType, array_keys(AdminAppService::POST_TYPES), true)) {
            return;
        }

        foreach ($GLOBALS['menu'] ?? [] as $index => $item) {
            if (($item[2] ?? null) !== AdminAppService::MENU_SLUG) {
                continue;
            }

            $classes = array_filter(explode(' ', (string) ($item[4] ?? '')));

            // The taxonomy screens already resolve correctly on their own;
            // only add where core got it wrong.
            if (! in_array('wp-menu-open', $classes, true)) {
                $classes[] = 'wp-has-current-submenu';
                $classes[] = 'wp-menu-open';
            }

            $GLOBALS['menu'][$index][4] = implode(' ', array_unique($classes));

            return;
        }
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
     * The app's OWN pages only — the dashboard and anything namespaced under
     * its slug.
     *
     * Deliberately NOT the CPT list and edit screens (ruled 2026-09-14). Those
     * are WordPress' screens and they stay WordPress': restyling them made
     * them feel neither like WP nor like the app. The menu fix below still
     * runs there, because that is a correctness bug rather than a skin.
     */
    private function ownsCurrentScreen(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        return $screen !== null
            && str_contains((string) $screen->id, 'page_' . AdminAppService::MENU_SLUG);
    }
}
