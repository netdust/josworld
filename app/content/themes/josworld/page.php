<?php
/**
 * Page Template
 *
 * Default template for WordPress pages. Renders title + content with
 * shortcode/block support.
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

get_header();
?>

<?php while (have_posts()) : the_post(); ?>
    <article <?php post_class(); ?>>
        <header class="bg-surface-alt border-b border-border">
            <div class="container py-8 lg:py-12">
                <h1 class="font-heading text-3xl lg:text-4xl font-bold text-text">
                    <?php the_title(); ?>
                </h1>
            </div>
        </header>

        <div class="container py-8 lg:py-12">
            <div class="prose-josworld max-w-content">
                <?php the_content(); ?>
            </div>
        </div>
    </article>
<?php endwhile; ?>

<?php
get_footer();
