<?php

/**
 * Tool App Service — serves the interactive tools at /tool-app/<slug>/.
 *
 * A tool app is a standalone page in the theme's `tools/` folder (its own markup, styles and script, in the
 * Jos style, no external requests). A `tool` post of kind "link" points at `url($slug)`; the download form
 * hands the visitor there. The slug is looked up in the folder's file list, never turned into a path, so
 * nothing outside `tools/*.html` can be served. The pages are `noindex`: the form is the way in.
 *
 * The route is a rewrite rule: run `wp rewrite flush --hard` once per environment after the deploy.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

defined('ABSPATH') || exit;

final class ToolAppService
{
    public const QUERY_VAR = 'jw_tool_app';

    public function __construct()
    {
        add_action('init', [$this, 'addRoute']);
        add_filter('query_vars', static fn (array $vars): array => [...$vars, self::QUERY_VAR]);
        add_action('template_redirect', [$this, 'serve']);
    }

    public function addRoute(): void
    {
        add_rewrite_rule('^tool-app/([a-z0-9-]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top');
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

    public function serve(): void
    {
        $slug = (string) get_query_var(self::QUERY_VAR);
        if ($slug === '') {
            return;
        }

        $html = $this->render($slug);
        if ($html === null) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();

            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: public, max-age=3600');
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- a theme file, not user input
        exit;
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
