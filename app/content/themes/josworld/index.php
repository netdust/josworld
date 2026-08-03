<?php
/**
 * Main Template File
 *
 * Fallback template for all content (blog index, archives, search).
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

get_header();
?>

<div class="container py-12">
    <?php if (have_posts()) : ?>

        <?php if (is_search()) : ?>
            <header class="mb-8">
                <h1 class="font-heading text-2xl lg:text-3xl font-bold">
                    <?php printf(esc_html__('Zoekresultaten voor: %s', 'josworld'), '<span class="text-primary">' . esc_html(get_search_query()) . '</span>'); ?>
                </h1>
            </header>
        <?php endif; ?>

        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('card p-6'); ?>>
                    <?php if (has_post_thumbnail()) : ?>
                        <a href="<?php the_permalink(); ?>" class="block mb-4 overflow-hidden rounded-lg">
                            <?php the_post_thumbnail('josworld_card', ['class' => 'w-full h-auto']); ?>
                        </a>
                    <?php endif; ?>

                    <h2 class="font-heading text-xl font-semibold mb-2">
                        <a href="<?php the_permalink(); ?>" class="text-text hover:text-primary">
                            <?php the_title(); ?>
                        </a>
                    </h2>

                    <div class="text-text-muted text-sm mb-4">
                        <?php the_excerpt(); ?>
                    </div>

                    <a href="<?php the_permalink(); ?>" class="btn-ghost btn-sm">
                        <?php esc_html_e('Lees meer', 'josworld'); ?>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination([
            'prev_text' => '&larr; ' . esc_html__('Vorige', 'josworld'),
            'next_text' => esc_html__('Volgende', 'josworld') . ' &rarr;',
            'class'     => 'mt-12',
        ]); ?>

    <?php else : ?>
        <?php
        josworld_template_part('partials/empty-state', null, [
            'icon'    => 'search',
            'title'   => __('Geen resultaten', 'josworld'),
            'message' => __('Er zijn geen berichten gevonden.', 'josworld'),
            'action'  => __('Terug naar home', 'josworld'),
            'url'     => home_url('/'),
        ]);
        ?>
    <?php endif; ?>
</div>

<?php
get_footer();
