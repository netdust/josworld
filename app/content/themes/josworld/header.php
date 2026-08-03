<?php
/**
 * Theme Header
 *
 * @package josworld
 */

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // Wire brand webfonts here when needed, e.g.:
    // <link rel="preconnect" href="https://fonts.googleapis.com">
    // <link href="https://fonts.googleapis.com/css2?family=..." rel="stylesheet">
    wp_head();
    ?>
</head>
<body <?php body_class('bg-surface text-text'); ?>>
<?php wp_body_open(); ?>

<div id="page" class="min-h-screen flex flex-col">

    <!-- Skip Link -->
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 bg-primary text-text-inverse px-4 py-2 rounded-lg z-50">
        <?php esc_html_e('Naar hoofdinhoud', 'josworld'); ?>
    </a>

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-surface/90 backdrop-blur border-b border-border" x-data="mobileMenu()">
        <div class="container">
            <div class="flex items-center justify-between h-16 lg:h-20">

                <!-- Logo -->
                <a href="<?php echo esc_url(home_url('/')); ?>" class="flex-shrink-0">
                    <?php if (has_custom_logo()) : ?>
                        <?php the_custom_logo(); ?>
                    <?php else : ?>
                        <span class="text-xl font-heading font-bold text-text">
                            <?php bloginfo('name'); ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- Desktop Navigation -->
                <nav class="hidden lg:flex items-center gap-1">
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'primary',
                        'container'      => false,
                        'menu_class'     => 'flex items-center gap-1',
                        'fallback_cb'    => 'josworld_fallback_menu',
                        'walker'         => new Ntdst_Nav_Walker(),
                    ]);
                    ?>
                </nav>

                <!-- Mobile Menu Toggle -->
                <button @click="toggle()" class="lg:hidden p-2 -mr-2" aria-label="<?php esc_attr_e('Menu', 'josworld'); ?>">
                    <span x-show="!open"><?php echo josworld_icon('menu', 'w-6 h-6'); ?></span>
                    <span x-show="open" x-cloak><?php echo josworld_icon('x', 'w-6 h-6'); ?></span>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div x-show="open"
             x-transition:enter="transition ease-out duration-normal"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-fast"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             x-cloak
             class="lg:hidden border-t border-border bg-surface-card">
            <div class="container py-4">
                <nav>
                    <?php
                    wp_nav_menu([
                        'theme_location' => 'primary',
                        'container'      => false,
                        'menu_class'     => 'space-y-1',
                        'fallback_cb'    => 'josworld_fallback_menu',
                        'walker'         => new Ntdst_Nav_Walker(),
                    ]);
                    ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main id="main" class="flex-1">
