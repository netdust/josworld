<?php
/**
 * Josworld Theme — Functions
 *
 * YOOtheme Pro child theme wired into ntdst-core. YOOtheme (the parent)
 * owns rendering: this child deliberately carries NO template files —
 * adding header.php/page.php here would bypass the builder. This file is
 * the site's wiring only, and all of it runs through the NTDST_Theme
 * facade below.
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

define('JOSWORLD_VERSION', '1.0.0');
define('JOSWORLD_DIR', get_stylesheet_directory());
define('JOSWORLD_URI', get_stylesheet_directory_uri());

require_once JOSWORLD_DIR . '/helpers/icons.php';
require_once JOSWORLD_DIR . '/helpers/formatting.php';

// ========================================
// BOOTSTRAP (NTDST Core Integration)
// ========================================

$config = require JOSWORLD_DIR . '/theme-config.php';

if (class_exists('NTDST_Bootstrap')) {
    $bootstrap = new NTDST_Bootstrap($config);
    $bootstrap->register();
    ntdst_set(NTDST_Bootstrap::class, fn() => $bootstrap);

    add_action('after_setup_theme', fn() => $bootstrap->bootCore(), 5);
    add_action('after_setup_theme', fn() => $bootstrap->bootFeatures(), 15);
}

// ========================================
// THEME FACADE — all theme wiring below goes through this instance
// ========================================

if (class_exists('NTDST_Theme')) {
    require_once __DIR__ . '/services/frontend/hooks/ViteAssets.php';

    // Theme setup (title-tag, html5, custom-logo, menus, image sizes,
    // sidebars, excerpt, content width) is driven by theme-config.php.
    $theme = new NTDST_Theme([
        'textdomain'    => $config['theme']['textdomain'] ?? 'josworld',
        'content_width' => $config['theme']['content_width'] ?? 1280,
        'theme_support' => $config['support'] ?? [],
        'image_sizes'   => $config['image_sizes'] ?? [],
        'menus'         => $config['menus'] ?? [],
        'sidebars'      => $config['sidebars'] ?? [],
        'excerpt'       => $config['excerpt'] ?? ['length' => 55, 'more' => ''],
    ]);

    $child_css = JOSWORLD_DIR . '/style.css';

    $theme
        // Parent (YOOtheme) stylesheet, then the child's hand-written
        // style.css overrides. Priority 20: after YOOtheme's own enqueues.
        // The brand fonts are NOT enqueued here — YOOtheme's StyleFontLoader
        // self-hosts the woff2 files picked in the Customizer; enqueueing
        // the same families again would double-load them from Google.
        ->on('wp_enqueue_scripts', static function () use ($child_css): void {
            wp_enqueue_style(
                'yootheme',
                get_template_directory_uri() . '/style.css',
                [],
                wp_get_theme(get_template())->get('Version') ?: null,
            );
            wp_enqueue_style(
                'josworld-child',
                JOSWORLD_URI . '/style.css',
                ['yootheme'],
                file_exists($child_css) ? (string) filemtime($child_css) : JOSWORLD_VERSION,
            );
        }, 20);

    // The Vite pipeline (manifest/dev-server fork + inline config) —
    // runtime-conditional, so a hook class rather than static ->script()
    // lines. It attaches through the facade it receives.
    (new \josworld\services\frontend\hooks\ViteAssets())->bind($theme);
}
