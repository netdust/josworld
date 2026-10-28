<?php

/**
 * The personal link page (ntdst-baseline grants): the tool page's header section, rendered by the builder.
 * Without the builder or the `single-tool` template, baseline's own page.
 *
 * @var array{kind: string, title: string, download_url: string, body: string, back_url: string, labels: array<string, string>} $args
 */

defined('ABSPATH') || exit;

$josworld_link_page = ntdst_get(\josworld\services\content\ToolLinkPage::class);

$josworld_link_html = match ($args['kind']) {
    'file' => $josworld_link_page->file($args),
    // wp_kses_post would strip the builder's uk-* attributes: only a page ToolLinkPage rendered skips it.
    'html' => $josworld_link_page->built($args['body']) ? $args['body'] : wp_kses_post($args['body']),
    default => $josworld_link_page->dead($args),
};

if ($josworld_link_html === null) {
    $josworld_link_route = new ReflectionClass(\ntdst\baseline\services\grants\LinkRoute::class);
    load_template(dirname((string) $josworld_link_route->getFileName()) . '/templates/link.php', false, $args);

    return;
}

echo $josworld_link_html; // phpcs:ignore WordPress.Security.EscapeOutput -- ToolLinkPage's own builder output, or kses'd above
