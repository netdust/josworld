<?php

/**
 * Tool App Service — what a visitor's personal link shows for a link-kind tool.
 *
 * A tool app is a standalone page in the theme's `tools/` folder (its own markup, styles and script, in the
 * Jos style, no external requests). A `tool` post of kind "link" whose link's path is `/tool-app/<slug>/` points
 * at one. That path has no route of its own: the app opens only at a personal link, through baseline's grants
 * module calling `renderForGrant()`. The slug is looked up in the folder's file list, never turned into a path,
 * so nothing outside `tools/*.html` can be served.
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

    public function __construct(private readonly ToolService $tools)
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
    public function render(string $slug): ?string
    {
        $html = in_array($slug, $this->slugs(), true) ? file_get_contents($this->dir() . $slug . '.html') : false;

        return is_string($html) ? $html : null;
    }

    private function openPage(\WP_Post $tool, string $link, string $lang): string
    {
        return sprintf(
            '<h1>%s</h1><p><a class="button" href="%s" rel="noreferrer noopener">%s</a></p>',
            esc_html(get_the_title($tool)),
            esc_url($link),
            esc_html(GrantService::label('open', $lang))
        );
    }

    /** @return list<string> the tool apps that exist */
    private function slugs(): array
    {
        $files = glob($this->dir() . '*.html') ?: [];

        return array_values(array_filter(
            array_map(static fn (string $file): string => basename($file, '.html'), $files),
            static fn (string $slug): bool => preg_match('/^[a-z0-9-]+$/', $slug) === 1
        ));
    }

    private function dir(): string
    {
        return trailingslashit(get_theme_root()) . 'josworld/tools/';
    }
}
