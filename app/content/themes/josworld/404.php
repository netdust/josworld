<?php
/**
 * 404 Template
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

get_header();
?>

<div class="container py-16">
    <?php
    josworld_template_part('partials/empty-state', null, [
        'icon'    => 'search',
        'title'   => __('Pagina niet gevonden', 'josworld'),
        'message' => __('De pagina die je zoekt bestaat niet of is verplaatst.', 'josworld'),
        'action'  => __('Terug naar home', 'josworld'),
        'url'     => home_url('/'),
    ]);
    ?>
</div>

<?php
get_footer();
