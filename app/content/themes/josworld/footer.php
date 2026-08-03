<?php
/**
 * Theme Footer
 *
 * @package josworld
 */

defined('ABSPATH') || exit;
?>
    </main><!-- #main -->

    <footer class="bg-surface-alt border-t border-border mt-auto">
        <div class="container py-12 lg:py-16">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-8">

                <!-- Brand Column -->
                <div class="max-w-sm">
                    <?php if (has_custom_logo()) : ?>
                        <?php the_custom_logo(); ?>
                    <?php else : ?>
                        <span class="text-xl font-heading font-bold text-text">
                            <?php bloginfo('name'); ?>
                        </span>
                    <?php endif; ?>
                    <p class="mt-4 text-sm text-text-muted">
                        <?php bloginfo('description'); ?>
                    </p>
                </div>

                <!-- Footer Menu -->
                <nav class="text-sm">
                    <?php
                    if (has_nav_menu('footer')) {
                        wp_nav_menu([
                            'theme_location' => 'footer',
                            'container'      => false,
                            'menu_class'     => 'space-y-2',
                            'walker'         => new Ntdst_Nav_Walker(),
                        ]);
                    }
                    ?>
                </nav>
            </div>

            <!-- Bottom Bar -->
            <div class="mt-12 pt-8 border-t border-border">
                <p class="text-sm text-text-muted">
                    &copy; <?php echo esc_html(date('Y')); ?> <?php bloginfo('name'); ?>.
                    <?php esc_html_e('Alle rechten voorbehouden.', 'josworld'); ?>
                </p>
            </div>
        </div>
    </footer>

</div><!-- #page -->

<!-- Toast Notification Container -->
<div x-data="toastStore()"
     @toast.window="show($event.detail)"
     class="fixed bottom-6 inset-x-0 flex justify-center z-50 pointer-events-none px-4">
    <div x-show="visible"
         x-transition:enter="transition ease-out duration-normal"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-fast"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-2"
         :class="type === 'error' ? 'bg-error' : 'bg-primary'"
         class="text-text-inverse text-sm px-5 py-3 rounded-lg shadow-overlay pointer-events-auto max-w-sm text-center"
         x-text="message"
         role="alert">
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
