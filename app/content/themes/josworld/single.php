<?php
/**
 * Single Post Template
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

get_header();
?>

<?php while (have_posts()) : the_post(); ?>
    <article <?php post_class(); ?>>
        <header class="bg-surface-alt border-b border-border">
            <div class="container py-8 lg:py-12 max-w-content">
                <div class="text-sm text-text-muted mb-3">
                    <?php echo esc_html(josworld_format_date(get_the_date('Y-m-d'))); ?>
                </div>
                <h1 class="font-heading text-3xl lg:text-4xl font-bold text-text">
                    <?php the_title(); ?>
                </h1>
            </div>
        </header>

        <div class="container py-8 lg:py-12">
            <?php if (has_post_thumbnail()) : ?>
                <div class="max-w-content mb-8 overflow-hidden rounded-xl">
                    <?php the_post_thumbnail('josworld_wide', ['class' => 'w-full h-auto']); ?>
                </div>
            <?php endif; ?>

            <div class="prose-josworld max-w-content">
                <?php
                the_content();

                wp_link_pages([
                    'before' => '<div class="mt-6 flex gap-2">',
                    'after'  => '</div>',
                ]);
                ?>
            </div>
        </div>
    </article>

    <?php if (comments_open() || get_comments_number()) : ?>
        <div class="container pb-12 max-w-content">
            <?php comments_template(); ?>
        </div>
    <?php endif; ?>
<?php endwhile; ?>

<?php
get_footer();
