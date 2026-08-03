<?php
/**
 * Search Form
 *
 * Used by get_search_form() and the html5 'search-form' theme support.
 *
 * @package josworld
 */

defined('ABSPATH') || exit;
?>
<form role="search" method="get" class="flex items-center gap-2" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="sr-only" for="search-field"><?php esc_html_e('Zoeken naar:', 'josworld'); ?></label>
    <input
        type="search"
        id="search-field"
        class="input-text"
        placeholder="<?php esc_attr_e('Zoeken...', 'josworld'); ?>"
        value="<?php echo esc_attr(get_search_query()); ?>"
        name="s"
    />
    <button type="submit" class="btn-primary" aria-label="<?php esc_attr_e('Zoeken', 'josworld'); ?>">
        <?php echo josworld_icon('search', 'w-5 h-5'); ?>
    </button>
</form>
