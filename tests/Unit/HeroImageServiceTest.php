<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong -- markup fixtures read better on one line

namespace NtdstTests\Unit;

use josworld\services\site\HeroImageService;

/**
 * The hero photo is a UIkit background painted by JavaScript. For a plain cover background in view it becomes a real
 * <picture> under the section's content, so the browser can find and paint it from the HTML.
 */
final class HeroImageServiceTest extends TestCase
{
    private const SOURCES = '[{"type":"image\/webp","srcset":"https:\/\/jos.agency\/c\/hero-768.webp 768w, https:\/\/jos.agency\/c\/hero-1600.webp 1600w","sizes":"(max-aspect-ratio: 2048\/683) 300vh"}]';
    private const CLASSES = 'uk-background-norepeat uk-background-cover uk-background-top-center uk-section uk-flex';

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/site/HeroImageService.php';
    }

    private function hero(string $classes = self::CLASSES, string $sources = self::SOURCES, string $extra = ''): string
    {
        return '<div class="' . $classes . '" data-sources="' . htmlspecialchars($sources, ENT_QUOTES)
            . '" data-src="/content/uploads/hero.jpg" ' . $extra . 'uk-img style="min-height: 65vh">';
    }

    private function page(string $body): string
    {
        return '<!doctype html><html><head><title>Jos</title></head><body>' . $body . '</body></html>';
    }

    public function test_the_background_becomes_a_picture_laid_under_the_content(): void
    {
        $html = HeroImageService::withHeroImage($this->page('<h1>Hallo</h1>' . $this->hero() . '<p>tekst</p></div>'));

        $this->assertStringContainsString('<picture><source type="image/webp" srcset="', $html);
        $this->assertStringContainsString('hero-768.webp 768w, https://jos.agency/c/hero-1600.webp 1600w"', $html);
        $this->assertStringContainsString('sizes="(max-aspect-ratio: 2048/683) 300vh"', $html);
        $this->assertStringContainsString('<img src="/content/uploads/hero.jpg" alt="" fetchpriority="high"', $html);
        $this->assertStringContainsString('object-fit:cover;object-position:center top;z-index:-1', $html);
        $this->assertStringContainsString('<p>tekst</p></div>', $html, 'the section keeps its content');
    }

    public function test_the_head_preloads_the_same_candidates(): void
    {
        $html = HeroImageService::withHeroImage($this->page($this->hero() . '</div>'));

        $this->assertMatchesRegularExpression(
            '#<link rel="preload" as="image" type="image/webp" imagesrcset="[^"]*hero-768\.webp 768w, [^"]*hero-1600\.webp 1600w"'
            . ' imagesizes="\(max-aspect-ratio: 2048/683\) 300vh" fetchpriority="high">\n</head>#',
            $html,
        );
        $this->assertSame(1, substr_count($html, 'rel="preload"'));
    }

    public function test_uikit_is_no_longer_asked_to_paint_it(): void
    {
        $html = HeroImageService::withHeroImage($this->page($this->hero() . '</div>'));
        $opening = substr($html, strpos($html, '<div class="uk-background') ?: 0, 400);

        $this->assertStringNotContainsString('uk-img', $opening);
        $this->assertStringNotContainsString('data-sources', $opening);
        $this->assertStringNotContainsString('data-src=', $opening);
        $this->assertStringContainsString('class="' . self::CLASSES . '"', $opening, 'the section keeps its classes');
        $this->assertStringContainsString('style="min-height: 65vh;position:relative;isolation:isolate;overflow:hidden"', $opening);
    }

    public function test_the_focal_point_follows_the_background_class(): void
    {
        $html = HeroImageService::withHeroImage($this->page($this->hero('uk-background-cover uk-background-bottom-right uk-section') . '</div>'));
        $plain = HeroImageService::withHeroImage($this->page($this->hero('uk-background-cover uk-section') . '</div>'));

        $this->assertStringContainsString('object-position:right bottom', $html);
        $this->assertStringContainsString('object-position:center;', $plain);
    }

    /** @dataProvider backgroundsUikitMustPaint */
    public function test_a_background_that_moves_or_blends_is_left_to_uikit(string $classes): void
    {
        $page = $this->page($this->hero($classes) . '</div>');

        $this->assertSame($page, HeroImageService::withHeroImage($page));
    }

    /** @return array<string, array{string}> */
    public static function backgroundsUikitMustPaint(): array
    {
        return [
            'fixed' => ['uk-background-cover uk-background-fixed uk-section'],
            'contain' => ['uk-background-contain uk-section'],
            'parallax' => ['uk-background-cover uk-section uk-parallax'],
            'blended' => ['uk-background-cover uk-background-blend-multiply uk-section'],
            'repeating' => ['uk-background-cover uk-background-repeat uk-section'],
            'not a cover at all' => ['uk-background-norepeat uk-section'],
        ];
    }

    /** @dataProvider pagesLeftAlone */
    public function test_a_page_without_a_usable_hero_in_view_is_left_alone(string $page): void
    {
        $this->assertSame($page, HeroImageService::withHeroImage($page));
    }

    /** @return array<string, array{string}> */
    public static function pagesLeftAlone(): array
    {
        $hero = '<div class="uk-background-cover uk-section" data-sources="' . htmlspecialchars(self::SOURCES, ENT_QUOTES)
            . '" data-src="/h.jpg" uk-img></div>';

        return [
            'no background image' => ['<html><head></head><body><p>x</p></body></html>'],
            'an image far down the page' => ['<html><head></head><body>' . str_repeat('<p>filler</p>', 5000) . $hero . '</body></html>'],
            'broken candidates' => ['<html><body><div class="uk-background-cover" data-sources="{nope" data-src="/h.jpg" uk-img></div></body></html>'],
            'no candidates' => ['<html><body><div class="uk-background-cover" data-sources="[]" data-src="/h.jpg" uk-img></div></body></html>'],
            'no fallback image' => ['<html><body><div class="uk-background-cover" data-sources="' . htmlspecialchars(self::SOURCES, ENT_QUOTES) . '" uk-img></div></body></html>'],
            'not a page' => ['{"json": true}'],
            'no head to preload from' => ['<div class="uk-background-cover" data-sources="' . htmlspecialchars(self::SOURCES, ENT_QUOTES) . '" data-src="/h.jpg" uk-img></div>'],
        ];
    }

    public function test_the_candidates_cannot_break_out_of_their_attributes(): void
    {
        $evil = '[{"type":"image\/webp","srcset":"a.webp 1w\"><script>alert(1)<\/script>","sizes":"x\" onload=\"y"}]';

        $html = HeroImageService::withHeroImage($this->page($this->hero(self::CLASSES, $evil) . '</div>'));

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('onload="y"', $html);
    }

    public function test_a_hero_only_some_screens_show_is_skipped_for_the_next_one(): void
    {
        $desktopOnly = $this->hero('uk-background-cover uk-section uk-visible@m', self::SOURCES);
        $everyScreen = str_replace('hero-', 'phone-', $this->hero(self::CLASSES));

        $html = HeroImageService::withHeroImage($this->page($desktopOnly . '</div>' . $everyScreen . '</div>'));

        $this->assertStringContainsString('data-sources', $desktopOnly);
        $this->assertSame(1, substr_count($html, '<picture>'));
        $this->assertSame(1, substr_count($html, 'class="uk-background-cover uk-section uk-visible@m" data-sources='), 'left to UIkit');
        $this->assertStringContainsString('rel="preload"', $html);
    }

    public function test_only_a_section_background_is_swapped_not_an_image_in_a_dropdown(): void
    {
        $page = $this->page($this->hero('uk-background-cover uk-navbar-dropdown') . '</div>');

        $this->assertSame($page, HeroImageService::withHeroImage($page));
    }

    public function test_a_style_that_already_holds_an_entity_is_not_escaped_twice(): void
    {
        $tag = '<div class="' . self::CLASSES . '" data-sources="' . htmlspecialchars(self::SOURCES, ENT_QUOTES)
            . '" data-src="/h.jpg" uk-img style="background-image: url(&quot;x.png&quot;); min-height: 65vh">';

        $html = HeroImageService::withHeroImage($this->page($tag . '</div>'));

        $this->assertStringContainsString('style="background-image: url(&quot;x.png&quot;); min-height: 65vh;position:relative', $html);
        $this->assertStringNotContainsString('&amp;quot;', $html);
    }

    public function test_only_the_first_hero_is_swapped(): void
    {
        $html = HeroImageService::withHeroImage($this->page($this->hero() . '</div>' . $this->hero() . '</div>'));

        $this->assertSame(1, substr_count($html, '<picture>'));
        $this->assertSame(1, substr_count($html, 'data-sources='));
    }
}
