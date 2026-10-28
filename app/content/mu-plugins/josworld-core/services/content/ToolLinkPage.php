<?php

/**
 * Tool Link Page — a visitor's personal link wears the tool page's header.
 *
 * The first section of the YOOtheme `single-tool` template ("Pagina-header") is rendered by the builder for the
 * granted tool, its button turned into the link's action (download or open) in the template's own button style.
 * The template is read from the `yootheme` option and changed in memory only. The dead page is the same section
 * without a tool: its headline, its text and the way back. Without the builder or the template every page answers
 * null, and baseline's own link page stands.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\content;

use ntdst\baseline\services\grants\GrantRepository;
use ntdst\baseline\services\grants\LinkRoute;

defined('ABSPATH') || exit;

class ToolLinkPage
{
    private const TEMPLATE = 'single-tool';
    private const SECTION = 'Pagina-header';
    /** The globals setup_postdata() writes. */
    private const POSTDATA = [
        'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages',
    ];
    private string $templateId = '';

    /** @var list<string> the pages this request's builder rendered */
    private array $built = [];

    public function __construct()
    {
        add_action('get_header', [$this, 'markBuilderPage']);
    }

    /** The theme's header wraps a non-builder page in a section and container of its own; these pages are sections. */
    public function markBuilderPage(): void
    {
        if (! LinkRoute::isGrantRequest()) {
            return;
        }
        if (! self::hasBuilder() || $this->section() === null) {
            return;
        }

        // @phpstan-ignore class.notFound, function.notFound
        \YOOtheme\app(\YOOtheme\Config::class)->set('app.isBuilder', true);
    }

    /** @param array<string, mixed> $args baseline's file page arguments */
    public function file(array $args): ?string
    {
        $tool = $this->grantedTool((string) $args['download_url']);

        if ($tool === null) {
            return null;
        }

        return $this->forTool($tool, (string) $args['labels']['download'], (string) $args['download_url']);
    }

    /** The tool's header with one action button: `$label` to `$url`. */
    public function forTool(\WP_Post $tool, string $label, string $url, bool $external = false): ?string
    {
        $layout = $this->section();
        if ($layout === null) {
            return null;
        }

        $this->setButton($layout, $label, $url, $external);
        if (self::hasOwnLayout($tool)) {
            // Its content is the builder's fallback copy of the whole page, the download form included.
            self::prune($layout, static fn (object $node): bool => $node->type === 'text');
        }

        return $this->asCurrentPost($tool, fn (): ?string => $this->render($layout));
    }

    /**
     * True only for a page this object rendered on this request; a renderer's string cannot claim it.
     * Such a page is echoed raw: baseline's wp_kses_post would strip the builder's uk-* attributes.
     */
    public function built(string $html): bool
    {
        return in_array($html, $this->built, true);
    }

    /** @param array<string, mixed> $args baseline's dead page arguments */
    public function dead(array $args): ?string
    {
        $layout = $this->section();
        if ($layout === null) {
            return null;
        }

        $labels = $args['labels'];
        self::prune($layout, static fn (object $node): bool => $node->type === 'image'
            || ($node->type === 'headline' && ($node->props->title_element ?? '') !== 'h1'));

        foreach (self::nodes($layout) as $node) {
            if ($node->type === 'headline') {
                self::fix($node, ['content' => esc_html((string) $labels['dead_title'])]);
            } elseif ($node->type === 'text') {
                self::fix($node, ['content' => '<p>' . esc_html((string) $labels['dead_body']) . '</p>']);
            }
        }

        $back = (string) $args['back_url'];
        if ($back === '') {
            self::prune($layout, static fn (object $node): bool => $node->type === 'button');
        } else {
            $this->setButton($layout, (string) $labels['dead_back'], $back);
        }

        return $this->render($layout);
    }

    /**
     * The builder's render step.
     *
     * @param array<string, mixed> $params
     */
    protected function build(object $layout, array $params): ?string
    {
        if (! self::hasBuilder()) {
            return null;
        }

        // @phpstan-ignore class.notFound, function.notFound
        return \YOOtheme\app(\YOOtheme\Builder::class)->render((string) wp_json_encode($layout), $params);
    }

    private function render(object $layout): ?string
    {
        $html = $this->build($layout, ['prefix' => 'template-' . $this->templateId, 'template' => self::TEMPLATE]);
        if ($html !== null) {
            $this->built[] = $html;
        }

        return $html;
    }

    /** A fresh copy of the template's header section as a layout of its own, or null when there is none. */
    private function section(): ?object
    {
        $option = json_decode((string) get_option('yootheme', ''));

        foreach ((array) ($option->templates ?? []) as $id => $template) {
            if (($template->type ?? '') !== self::TEMPLATE || ($template->status ?? '') === 'disabled') {
                continue;
            }

            $sections = (array) ($template->layout->children ?? []);
            $named = array_filter($sections, static fn (object $s): bool => ($s->name ?? '') === self::SECTION);
            $section = reset($named) ?: ($sections[0] ?? null);
            $this->templateId = (string) $id;
            if ($section === null) {
                return null;
            }

            // The only section on the page: screen-tall below the header, or the reveal footer shows through sliced.
            self::fix($section, ['height' => 'viewport', 'height_viewport_offset' => true]);

            return (object) [
                'type' => 'layout',
                'version' => $template->layout->version ?? null,
                'children' => [$section],
            ];
        }

        return null;
    }

    /** The first button item becomes the one action; the template's own binding and any other item go. */
    private function setButton(object $layout, string $label, string $url, bool $external = false): void
    {
        foreach (self::nodes($layout) as $node) {
            if ($node->type !== 'button') {
                continue;
            }

            $node->children = array_slice((array) ($node->children ?? []), 0, 1);
            if (isset($node->children[0])) {
                self::fix($node->children[0], [
                    'content' => esc_html($label),
                    'link' => esc_url($url),
                    'link_rel_noreferrer' => $external,
                ]);
            }

            return;
        }
    }

    /** @param array<string, mixed> $props static props in place of the node's binding */
    private static function fix(object $node, array $props): void
    {
        unset($node->source);
        $node->props = (object) ($props + (array) ($node->props ?? []));
    }

    /** Drops the matching nodes, then any column they left empty. */
    private static function prune(object $node, callable $drop): void
    {
        if (! isset($node->children)) {
            return;
        }

        $kept = [];
        foreach ($node->children as $child) {
            if ($drop($child)) {
                continue;
            }
            self::prune($child, $drop);
            if ($child->type !== 'column' || ! empty($child->children)) {
                $kept[] = $child;
            }
        }
        $node->children = $kept;
    }

    /** @return list<object> every node under $node, depth first */
    private static function nodes(object $node): array
    {
        $all = [];
        foreach ($node->children ?? [] as $child) {
            $all[] = $child;
            array_push($all, ...self::nodes($child));
        }

        return $all;
    }

    /** The builder's `tools.singleTool` source reads the global post. */
    private function asCurrentPost(\WP_Post $tool, callable $render): ?string
    {
        $previous = $GLOBALS['post'] ?? null;
        $postdata = array_intersect_key($GLOBALS, array_flip(self::POSTDATA));
        $GLOBALS['post'] = $tool;
        setup_postdata($tool);

        try {
            return $render();
        } finally {
            $GLOBALS['post'] = $previous;
            if ($previous instanceof \WP_Post) {
                setup_postdata($previous);
            } else {
                self::restorePostdata($postdata);
                wp_reset_postdata();
            }
        }
    }

    /**
     * wp_reset_postdata() sets nothing when the main query has no post: put back what was there.
     *
     * @param array<string, mixed> $postdata
     */
    private static function restorePostdata(array $postdata): void
    {
        foreach (self::POSTDATA as $name) {
            if (array_key_exists($name, $postdata)) {
                $GLOBALS[$name] = $postdata[$name];
            } else {
                unset($GLOBALS[$name]);
            }
        }
    }

    /** The file page's arguments name no post; the personal download URL carries the token the route just verified. */
    private function grantedTool(string $downloadUrl): ?\WP_Post
    {
        $path = (string) wp_parse_url($downloadUrl, PHP_URL_PATH);
        if (preg_match('#/([0-9a-f]{64})/download/?$#', $path, $match) !== 1) {
            return null;
        }

        $grant = ntdst_get(GrantRepository::class)->verify($match[1]);
        $tool = $grant === null ? null : get_post((int) ($grant->fields['content_id'] ?? 0));

        return $tool instanceof \WP_Post && $tool->post_type === 'tool' ? $tool : null;
    }

    private static function hasOwnLayout(\WP_Post $tool): bool
    {
        return preg_match('/<!--\s?(\{.*})\s?-->/', $tool->post_content) === 1; // YOOtheme's PostHelper::PATTERN
    }

    private static function hasBuilder(): bool
    {
        return function_exists('YOOtheme\\app') && class_exists('YOOtheme\\Builder');
    }
}
