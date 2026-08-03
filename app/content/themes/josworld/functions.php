<?php
/**
 * Josworld Theme — Functions
 *
 * Clean Bedrock starter wired into ntdst-core (Tailwind + Alpine + Vite).
 *
 * Wiring pattern (mirrors stridence, minus all LMS/domain code):
 *   1. Define theme constants.
 *   2. Load helper functions.
 *   3. Register NTDST_Bootstrap from theme-config.php, boot core @5 / features @15.
 *   4. Hand theme setup (menus, image sizes, support, ...) to NTDST_Theme.
 *   5. Bind the frontend hook classes to the theme.
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
// HOOKS (bound to NTDST_Theme)
// ========================================

require_once __DIR__ . '/services/frontend/hooks/AssetHooks.php';

if (class_exists('NTDST_Theme')) {
    $theme = ntdst_get(\NTDST_Theme::class);
    (new \josworld\services\frontend\hooks\AssetHooks())->bind($theme);
}

// ========================================
// NAVIGATION WALKER
// ========================================

/**
 * Tailwind-classed nav walker shared by the desktop and mobile menus.
 *
 * Deliberately minimal: it applies the `.nav-link` component class and an
 * active state, and appends a chevron icon to top-level items that have
 * children. No dropdown markup, no LMS classes — add what the brand needs.
 */
class Ntdst_Nav_Walker extends Walker_Nav_Menu
{
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void
    {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes, true);

        $output .= '<li>';

        $link_classes = 'nav-link';
        if (in_array('current-menu-item', $classes, true) || in_array('current-menu-ancestor', $classes, true)) {
            $link_classes .= ' nav-link-active';
        }

        $atts = [
            'href'  => !empty($item->url) ? esc_url($item->url) : '',
            'class' => $link_classes,
        ];
        if (!empty($item->target)) {
            $atts['target'] = $item->target;
        }

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (!empty($value)) {
                $attributes .= ' ' . $attr . '="' . esc_attr($value) . '"';
            }
        }

        $title = apply_filters('the_title', $item->title, $item->ID);
        $output .= '<a' . $attributes . '>' . esc_html($title);

        if ($has_children && $depth === 0) {
            $output .= ' ' . josworld_icon('chevron-down', 'w-3 h-3 inline-block');
        }

        $output .= '</a>';
    }

    public function end_el(&$output, $item, $depth = 0, $args = null): void
    {
        $output .= '</li>';
    }
}

/**
 * Fallback menu used when no menu is assigned to a location.
 * Keeps the header/footer functional on a fresh install.
 */
function josworld_fallback_menu(): void
{
    echo '<ul class="flex items-center gap-1">';
    echo '<li><a href="' . esc_url(home_url('/')) . '" class="nav-link">' . esc_html__('Home', 'josworld') . '</a></li>';
    echo '</ul>';
}
