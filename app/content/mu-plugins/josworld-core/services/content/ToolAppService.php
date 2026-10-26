<?php

/**
 * Tool App Service — what a visitor's personal link shows for a link-kind tool.
 *
 * A tool app is a standalone page in the theme's `tools/` folder (its own markup, styles and script, in the
 * Jos style, no external requests). A `tool` post of kind "link" whose link's path is `/tool-app/<slug>/` points
 * at one. That path has no route of its own: the app opens only at a personal link, through baseline's grants
 * module calling `renderForGrant()`. The slug is looked up in the folder's file list, never turned into a path,
 * so nothing outside `tools/*.html.php` can be served. The apps are PHP files that exit without ABSPATH: a static
 * `.html` under the theme would be public to anyone who guessed its address.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use ntdst\baseline\services\grants\GrantService;

defined('ABSPATH') || exit;

final class ToolAppService
{
    private const APP_PATH = '#^/tool-app/([^/]+)/?$#';
    private const EXT = '.html.php';

    public function __construct(private readonly ToolService $tools, private readonly ToolLinkPage $page)
    {
    }

    /**
     * Baseline's `render` for a granted tool: null when the app was echoed (the grant route sent the headers and
     * ends the request; a header here would undo its `no-store`), the "Open" page body for a link to another
     * site, false for everything else so baseline answers with its file page or its dead page.
     * The link's host is ignored: staging runs on production's database.
     */
    public function renderForGrant(\WP_Post $tool, \WP_Post $grant): string|false|null
    {
        $link = $this->tools->link($tool->ID);
        if ($link === '') {
            return false;
        }

        if (preg_match(self::APP_PATH, (string) wp_parse_url($link, PHP_URL_PATH), $match) === 1) {
            $html = $this->render($match[1]);
            if ($html === null) {
                return false;
            }
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- a theme file, not user input

            return null;
        }

        return $this->openPage($tool, $link, (string) ($grant->fields['lang'] ?? ''));
    }

    /** The page's HTML, or null when no such tool app exists. */
    private function render(string $slug): ?string
    {
        if (! in_array($slug, $this->slugs(), true)) {
            return null;
        }

        ob_start();
        include $this->dir() . $slug . self::EXT;

        return (string) ob_get_clean();
    }

    /** The tool page's header with an "Open" button; without the builder, a heading and the button. */
    private function openPage(\WP_Post $tool, string $link, string $lang): string
    {
        $label = GrantService::label('open', $lang);
        $page = $this->page->forTool($tool, $label, $link, true);
        if ($page !== null) {
            return $page;
        }

        return sprintf(
            '<h1>%s</h1><p><a class="button" href="%s" rel="noreferrer noopener">%s</a></p>',
            esc_html(get_the_title($tool)),
            esc_url($link),
            esc_html($label)
        );
    }

    /** @return list<string> the tool apps that exist */
    private function slugs(): array
    {
        $files = glob($this->dir() . '*' . self::EXT) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $file): string => basename($file, self::EXT), $files),
            static fn (string $slug): bool => preg_match('/^[a-z0-9-]+$/', $slug) === 1
        ));
    }

    private function dir(): string
    {
        return trailingslashit(get_theme_root()) . 'josworld/tools/';
    }
}
