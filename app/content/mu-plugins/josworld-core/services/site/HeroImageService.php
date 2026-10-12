<?php

/**
 * HeroImageService — the hero photo as a real image, so it needs no JavaScript to appear.
 *
 * YOOtheme paints a section's background photo with UIkit: the candidates sit in a `data-sources` attribute and the
 * photo is requested and painted only once UIkit has run, behind the render-blocking scripts. On a phone that is the
 * largest thing on the page arriving seconds late. For the first cover-style background in view this swaps the
 * attribute for a `<picture>` laid under the section's content (cover, same focal point, high priority), which the
 * browser finds and paints from the HTML alone, and a preload in the head with the same candidates starts the download
 * with the page. Backgrounds that move (fixed, parallax, blended) are left to UIkit.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\site;

defined('ABSPATH') || exit;

final class HeroImageService
{
    /** Bytes of body markup counted as "in view": further down, an eager image would only take bandwidth. */
    private const ABOVE_THE_FOLD = 40000;

    /** Backgrounds that need UIkit to move or blend them. */
    private const NEEDS_UIKIT = '/uk-background-(?:fixed|contain|blend)|uk-blend|uk-parallax'
        . '|uk-background-(?:\w+-)?(?:repeat|attachment)/';

    public function __construct()
    {
        if (PHP_SAPI !== 'cli') {
            add_action('template_redirect', [$this, 'start'], 0);
        }
    }

    public function start(): void
    {
        if (is_feed() || is_robots() || is_embed() || wp_doing_ajax()) {
            return;
        }

        ob_start([self::class, 'withHeroImage']);
    }

    public static function withHeroImage(string $html): string
    {
        $body = stripos($html, '<body');
        if ($body === false) {
            return $html;
        }

        $window = substr($html, $body, self::ABOVE_THE_FOLD);
        if (preg_match('/<div\b[^>]*\sdata-sources="[^"]*"[^>]*>/', $window, $found, PREG_OFFSET_CAPTURE) !== 1) {
            return $html;
        }

        [$tag, $offset] = $found[0];
        $replacement = self::replacement($tag);
        $head = stripos($html, '</head>');
        if ($replacement === null || $head === false || $head > $body) {
            return $html;
        }

        $html = substr_replace($html, $replacement['markup'], $body + $offset, strlen($tag));

        return substr($html, 0, $head) . $replacement['preload'] . substr($html, $head);
    }

    /** @return array{markup: string, preload: string}|null */
    private static function replacement(string $tag): ?array
    {
        $class = self::attribute($tag, 'class') ?? '';
        $sources = json_decode(self::decode(self::attribute($tag, 'data-sources')), true);
        $fallback = self::decode(self::attribute($tag, 'data-src'));

        if (
            ! str_contains($class, 'uk-background-cover') || preg_match(self::NEEDS_UIKIT, $class) === 1
            || ! is_array($sources) || $sources === [] || $fallback === ''
        ) {
            return null;
        }

        $pictures = '';
        $preload = '';
        foreach ($sources as $source) {
            if (! is_array($source) || ! is_string($source['srcset'] ?? null) || $source['srcset'] === '') {
                return null;
            }
            $pictures .= '<source'
                . (is_string($source['type'] ?? null) ? ' type="' . self::escape($source['type']) . '"' : '')
                . ' srcset="' . self::escape($source['srcset']) . '"'
                . (is_string($source['sizes'] ?? null) ? ' sizes="' . self::escape($source['sizes']) . '"' : '')
                . '>';
            $preload = $preload !== '' ? $preload : '<link rel="preload" as="image"'
                . (is_string($source['type'] ?? null) ? ' type="' . self::escape($source['type']) . '"' : '')
                . ' imagesrcset="' . self::escape($source['srcset']) . '"'
                . (is_string($source['sizes'] ?? null) ? ' imagesizes="' . self::escape($source['sizes']) . '"' : '')
                . ' fetchpriority="high">' . "\n";
        }

        $style = trim(self::attribute($tag, 'style') ?? '', "; \t")
            . ';position:relative;isolation:isolate;overflow:hidden';
        $opening = preg_replace('/\s(?:data-sources|data-src|loading|style|uk-img)(?:="[^"]*")?/', '', $tag) ?? $tag;
        $opening = substr($opening, 0, -1) . ' style="' . self::escape(ltrim($style, ';')) . '">';
        $image = '<img src="' . self::escape($fallback) . '" alt="" fetchpriority="high" decoding="async"'
            . ' style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:'
            . self::position($class) . ';z-index:-1">';

        return ['markup' => $opening . '<picture>' . $pictures . $image . '</picture>', 'preload' => $preload];
    }

    /** UIkit's `uk-background-top-center` etc. as an object-position. */
    private static function position(string $class): string
    {
        if (preg_match('/uk-background-(top|center|bottom)-(left|center|right)/', $class, $match) !== 1) {
            return 'center';
        }

        return $match[2] . ' ' . $match[1];
    }

    private static function attribute(string $tag, string $name): ?string
    {
        return preg_match('/\s' . preg_quote($name, '/') . '="([^"]*)"/', $tag, $match) === 1 ? $match[1] : null;
    }

    private static function decode(?string $value): string
    {
        return html_entity_decode($value ?? '', ENT_QUOTES | ENT_HTML5);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
