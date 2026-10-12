<?php

/**
 * Tool App Service — serves the interactive tools at /tool-app/<slug>/.
 *
 * A tool app is a standalone page in the theme's `tools/` folder (its own markup, styles and script, in the
 * Jos style, no external requests). A `tool` post of kind "link" points at `url($slug)`; the download form
 * hands the visitor there. The slug is looked up in the folder's file list, never turned into a path, so
 * nothing outside `tools/*.html` can be served. The pages are `noindex`: the form is the way in.
 *
 * The route is ntdst-core's page router: it flushes the rewrite rules itself when its rule set changes, so a deploy
 * needs no hand-run flush.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

defined('ABSPATH') || exit;

final class ToolAppService
{
    public function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    public function register(): void
    {
        ntdst_pages()->path('/tool-app/:slug', [$this, 'serve']);
    }

    /** Where a link-kind tool points. */
    public function url(string $slug): string
    {
        return home_url('/tool-app/' . $slug . '/');
    }

    /** The page's HTML, or null when no such tool app exists. */
    public function render(string $slug): ?string
    {
        $html = in_array($slug, $this->slugs(), true) ? file_get_contents($this->dir() . $slug . '.html') : false;

        return is_string($html) ? $html : null;
    }

    /**
     * The page route's callback: the page itself, or false for WordPress's own 404.
     *
     * @param array<string, string> $params
     */
    public function serve(array $params): ?bool
    {
        $html = $this->render($params['slug'] ?? '');
        if ($html === null) {
            return false;
        }

        if (! headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex');
            header('Cache-Control: public, max-age=3600');
        }
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- a theme file, not user input

        return null;
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
