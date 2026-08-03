<?php
/**
 * Josworld Theme — Functions
 *
 * YOOtheme Pro child theme wired into ntdst-core (Tailwind + Alpine + Vite).
 *
 * YOOtheme (the parent) owns rendering: it ships the page templates and the
 * builder, so this child deliberately carries NO template files — adding a
 * header.php/page.php/etc. here would override the parent and bypass the
 * builder entirely. This file is for the site's own wiring only.
 *
 * Wiring pattern (mirrors stridence, minus all LMS/domain code):
 *   1. Define theme constants.
 *   2. Load helper functions.
 *   3. Register NTDST_Bootstrap from theme-config.php, boot core @5 / features @15.
 *   4. Hand theme setup (menus, image sizes, support, ...) to NTDST_Theme.
 *   5. Enqueue the parent stylesheet, then this child's own assets.
 *   6. Bind the frontend hook classes to the theme.
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

// ========================================
// CONSTANTS
// ========================================

define('JOSWORLD_VERSION', '1.0.0');
define('JOSWORLD_DIR', get_stylesheet_directory());
define('JOSWORLD_URI', get_stylesheet_directory_uri());

// ========================================
// LOAD HELPERS
// ========================================

require_once JOSWORLD_DIR . '/helpers/icons.php';
require_once JOSWORLD_DIR . '/helpers/formatting.php';
require_once JOSWORLD_DIR . '/helpers/templates.php';

// ========================================
// BOOTSTRAP (NTDST Core Integration)
// ========================================

$config = require JOSWORLD_DIR . '/theme-config.php';

// Create and register bootstrap instance (from ntdst-core)
if (class_exists('NTDST_Bootstrap')) {
    $bootstrap = new NTDST_Bootstrap($config);
    $bootstrap->register();
    ntdst_set(NTDST_Bootstrap::class, fn() => $bootstrap);

    // Boot core services (priority 5)
    add_action('after_setup_theme', fn() => $bootstrap->bootCore(), 5);

    // Boot feature services (priority 15)
    add_action('after_setup_theme', fn() => $bootstrap->bootFeatures(), 15);
}

// Hand theme setup to NTDST_Theme: title-tag, html5, custom-logo,
// menus, image sizes, sidebars, excerpt and content width are all
// driven by theme-config.php.
if (class_exists('NTDST_Theme')) {
    new NTDST_Theme([
        'textdomain'    => $config['theme']['textdomain'] ?? 'josworld',
        'content_width' => $config['theme']['content_width'] ?? 1280,
        'theme_support' => $config['support'] ?? [],
        'image_sizes'   => $config['image_sizes'] ?? [],
        'menus'         => $config['menus'] ?? [],
        'sidebars'      => $config['sidebars'] ?? [],
        'excerpt'       => $config['excerpt'] ?? ['length' => 55, 'more' => ''],
        'assets'        => $config['assets'] ?? ['styles' => [], 'scripts' => []],
    ]);
}

// ========================================
// PARENT (YOOtheme) STYLESHEET
// ========================================

/**
 * Load the parent (YOOtheme) stylesheet, then this child's own style.css after
 * it. The Vite-built CSS is enqueued separately by AssetHooks; this only covers
 * the child's hand-written style.css overrides.
 *
 * Priority 20 so it lands after YOOtheme's own enqueues.
 */
add_action('wp_enqueue_scripts', static function (): void {
    // NOTE: the brand fonts are NOT enqueued here. They are selected in
    // Customizer → Theme → Style → Fonts, and YOOtheme's StyleFontLoader
    // (packages/styler/src/StyleFontLoader.php) downloads and self-hosts the
    // woff2 files — so adding a wp_enqueue_style for the same families would
    // load them twice, and from Google's CDN instead of this server.
    wp_enqueue_style(
        'yootheme',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme(get_template())->get('Version') ?: null,
    );

    $child_css = JOSWORLD_DIR . '/style.css';
    wp_enqueue_style(
        'josworld-child',
        JOSWORLD_URI . '/style.css',
        ['yootheme'],
        file_exists($child_css) ? (string) filemtime($child_css) : JOSWORLD_VERSION,
    );
}, 20);

// ========================================
// HOOKS (bound to NTDST_Theme)
// ========================================

require_once __DIR__ . '/services/frontend/hooks/AssetHooks.php';

if (class_exists('NTDST_Theme')) {
    $theme = ntdst_get(\NTDST_Theme::class);
    (new \josworld\services\frontend\hooks\AssetHooks())->bind($theme);
}

// ========================================
// ntdst-core integration point
// ========================================

/**
 * The framework is loaded by the `ntdst-coreloader.php` mu-plugin and booted
 * above, so `ntdst_get()`, `ntdst_endpoints()`, … are available anywhere in
 * this theme — no bootstrap required in template or service code.
 *
 * The Tailwind nav walker and fallback menu that shipped with the standalone
 * starter were removed with the template files: YOOtheme renders navigation
 * through its own builder elements, so a bespoke `Walker_Nav_Menu` has nothing
 * to walk here. If a custom menu ever IS needed, add it back alongside the
 * builder element that calls it.
 */
