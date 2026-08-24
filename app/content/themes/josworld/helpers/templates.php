<?php
/**
 * Template helpers — thin wrappers around NTDST_Response::html().
 *
 * Why wrappers and not direct ntdst_response() calls everywhere?
 * - Callers get NTDST's locate cache for free.
 * - Client mu-plugins can override templates by registering their own
 *   directories via NTDST_Template_Loader::addPath(), no filter needed.
 *
 * @package josworld
 */

declare(strict_types=1);

/**
 * Echo a template part with NTDST's cached lookup.
 *
 * Resolution order (highest priority first):
 *   1. Paths registered via NTDST_Template_Loader::addPath()  (client plugins)
 *   2. WordPress's own locate_template() fallback — child theme
 *      root last, exactly where a theme-root template lives.
 *
 * Slug semantics: relative to a registered directory or the theme root,
 * e.g. 'partials/empty-state'. No leading slash, no .php extension.
 *
 * Template-side contract (ntdst-core 5.0): the data dictionary reaches the
 * template as `$args` (WordPress's own load_template() scope). Keys are NOT
 * extracted as loose variables any more — a loose `$title` in a template
 * reads WordPress's query var of that name, silently. Read `$args['title']`.
 *
 * An unresolvable slug renders as '' and logs a warning (html() fails
 * closed since 5.0) — check the return of josworld_template_html() when
 * the slug can be wrong.
 *
 * @param string      $slug Template slug (e.g., 'partials/empty-state')
 * @param string|null $name Optional name variant — appended as '-{name}'
 * @param array       $args Data exposed to the template as $args
 */
function josworld_template_part(string $slug, ?string $name = null, array $args = []): void
{
    echo josworld_template_html($slug, $name, $args);
}

/**
 * Render a template part and return its output as a string.
 *
 * Same resolution and $args contract as josworld_template_part(), but
 * returns instead of echoing — for shortcodes and any caller that needs
 * the rendered HTML as a value.
 *
 * @param string      $slug Template slug (e.g., 'partials/empty-state')
 * @param string|null $name Optional name variant — appended as '-{name}'
 * @param array       $args Data exposed to the template as $args
 */
function josworld_template_html(string $slug, ?string $name = null, array $args = []): string
{
    $template = $name ? "{$slug}-{$name}" : $slug;

    return ntdst_response()->html($template, $args);
}
