<?php

/**
 * Josworld - Theme Configuration
 *
 * All configuration in one place — no logic, just data.
 * This is the single source of truth for theme settings, read by
 * functions.php to wire NTDST_Bootstrap + NTDST_Theme.
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

return [
    // ========================================
    // THEME METADATA
    // ========================================
    'theme' => [
        'textdomain' => 'josworld',
        'version' => '1.0.0',
        'content_width' => 1280,
    ],

    // ========================================
    // WORDPRESS FEATURES
    // ========================================
    'support' => [
        'title-tag' => true,
        'post-thumbnails' => true,
        'automatic-feed-links' => true,
        'customize-selective-refresh-widgets' => true,
        'html5' => ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'],
        'custom-logo' => [
            'height' => 100,
            'width' => 400,
            'flex-height' => true,
            'flex-width' => true,
        ],
        'responsive-embeds' => true,
    ],

    // ========================================
    // IMAGE SIZES
    // Generic sizes only — add brand-specific sizes as needed.
    // Format: [width, height, crop, label]
    // ========================================
    'image_sizes' => [
        'josworld_thumbnail' => [150, 150, true, 'Thumbnail'],
        'josworld_card' => [400, 225, true, 'Card'],
        'josworld_wide' => [1200, 675, true, 'Wide'],
    ],

    // ========================================
    // NAVIGATION MENUS
    // ========================================
    'menus' => [
        'primary' => 'Hoofdmenu',
        'footer' => 'Footermenu',
    ],

    // ========================================
    // SIDEBAR WIDGET AREAS
    // Note: Translation handled at registration time, not here.
    // ========================================
    'sidebars' => [
        [
            'name' => 'Main Sidebar',
            'id' => 'sidebar-main',
            'description' => 'Main sidebar area',
        ],
    ],

    // ========================================
    // EXCERPT SETTINGS
    // ========================================
    'excerpt' => [
        'length' => 55,
        'more' => ' <a href="%s">Lees meer</a>',
    ],

    // ========================================
    // SERVICES CONFIGURATION
    // Frontend hook classes are bound in functions.php; no auto-discovered
    // services ship with the starter. Business logic belongs in a
    // <project>-core mu-plugin, not the theme.
    // ========================================
    'services' => [
        'core' => [],
        'admin' => [],
        'conditional' => [],
    ],

    // ========================================
    // MODULE-SPECIFIC DEFAULTS
    // Only the generic, always-useful modules are kept. Domain modules
    // (enrollment, vouchers, invoicing, ...) do not belong in a starter.
    // ========================================
    'modules' => [
        'security' => [
            'hide_wp_version' => true,
            'remove_generator_tags' => true,
            'disable_xmlrpc' => true,
            'generic_login_errors' => true,
        ],

        'performance' => [
            'post_revisions' => 5,
            'autosave_interval' => 300,
        ],
    ],
];
