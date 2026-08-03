<?php

/**
 * Josworld Core — business-logic plugin.
 *
 * Hosts Josworld's services OUTSIDE the theme, per the josworld convention
 * ("business logic belongs in a <project>-core mu-plugin, not the theme" —
 * see theme-config.php).
 *
 * LOAD ORDER: this file is included by josworld-coreloader.php, which sorts
 * alphabetically BEFORE ntdst-coreloader.php, so `ntdst_get()` and the rest
 * of the ntdst-core framework are NOT yet defined at include time. All
 * framework-dependent wiring is therefore deferred to `plugins_loaded`
 * (fires after every mu-plugin is included, so ntdst-core is guaranteed
 * present by then). ntdst-core itself is UNTOUCHED — it is shared with
 * other sites.
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

add_action('plugins_loaded', function (): void {
    // Fail quiet if ntdst-core didn't load (framework missing/disabled) —
    // no fatal, no partial registration.
    if (!function_exists('ntdst_get')) {
        return;
    }

    // T02 lands the service boot block here: explicit require_once of each
    // service file, then ntdst_get() resolution on after_setup_theme:5.
}, 5);
