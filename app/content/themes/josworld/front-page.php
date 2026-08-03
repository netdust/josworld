<?php
/**
 * Homepage Template
 *
 * Minimal hero + content starting point. Replace with the brand's homepage
 * sections — this exists so a fresh install renders a sensible front page.
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

get_header();
?>

<!-- Hero Section -->
<section class="section">
    <div class="container">
        <div class="max-w-2xl">
            <span class="inline-block text-primary font-semibold tracking-widest uppercase text-xs mb-6">
                <?php bloginfo('description'); ?>
            </span>
            <h1 class="font-heading text-4xl lg:text-6xl font-bold leading-tight mb-6">
                <?php bloginfo('name'); ?>
            </h1>
            <p class="text-lg text-text-muted leading-relaxed mb-8">
                <?php esc_html_e('Een schone start. Vervang deze sectie met de homepage van het merk.', 'josworld'); ?>
            </p>
            <div class="flex flex-wrap gap-4">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary btn-lg">
                    <?php esc_html_e('Aan de slag', 'josworld'); ?>
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// If a static front page has content, render it below the hero.
if (have_posts()) :
    while (have_posts()) : the_post();
        $content = trim(get_the_content());
        if ($content !== '') :
            ?>
            <section class="section-alt">
                <div class="container">
                    <div class="prose-josworld max-w-content mx-auto">
                        <?php the_content(); ?>
                    </div>
                </div>
            </section>
            <?php
        endif;
    endwhile;
endif;

get_footer();
