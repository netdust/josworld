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
require_once JOSWORLD_DIR . '/helpers/templates.php';

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
    require_once __DIR__ . '/services/frontend/hooks/AssetHooks.php';

    // The YOOtheme dynamic-content bridge. Required explicitly (no
    // autoloader); `ntdst-cached-meta-accessor.php` is deliberately NOT
    // required here — FieldResolver requires it lazily so Brain Monkey can
    // intercept it at the unit tier.
    require_once __DIR__ . '/services/yootheme/SchemaMapper.php';
    require_once __DIR__ . '/services/yootheme/FieldResolver.php';
    require_once __DIR__ . '/services/yootheme/YOOthemeSourcesService.php';

    $josworld_yootheme_sources = new \josworld\services\yootheme\YOOthemeSourcesService();

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
        ->style(
            'yootheme',
            get_template_directory_uri() . '/style.css',
            [],
            wp_get_theme(get_template())->get('Version') ?: null,
            'all',
            20,
        )
        ->style(
            'josworld-child',
            JOSWORLD_URI . '/style.css',
            ['yootheme'],
            file_exists($child_css) ? (string) filemtime($child_css) : JOSWORLD_VERSION,
            'all',
            20,
        )
        // ORDERING CONTRACT (fails SILENTLY if broken): `collect` listens
        // for `ntdst/model/registered`, which josworld-core fires on
        // `after_setup_theme` @5. This file runs at top level BEFORE that
        // hook fires, so attaching here is provably in time; attaching from
        // bootCore()/bootFeatures() is a race / too late, and the only
        // symptom is an empty dynamic-content picker. Contract test:
        // tests/Integration/YOOthemeSourcesBootWiringTest.php.
        ->on('ntdst/model/registered', [$josworld_yootheme_sources, 'collect'], 10, 2)
        // On `init` so YOOtheme has declared `YOOtheme\app` by the time
        // register()'s own guard runs; safe without the licensed parent.
        ->on('init', [$josworld_yootheme_sources, 'register']);

    (new \josworld\services\frontend\hooks\AssetHooks())->bind($theme);
}
