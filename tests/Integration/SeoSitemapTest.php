<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_Sitemaps_Posts;
use WP_UnitTestCase;

/**
 * What a search engine is told about: the real core providers, through josworld's filters.
 */
final class SeoSitemapTest extends WP_UnitTestCase
{
    /** @return list<string> */
    private function pagePaths(): array
    {
        add_filter('wp_sitemaps_enabled', '__return_true');

        return array_map(
            static fn (array $entry): string => (string) wp_parse_url($entry['loc'], PHP_URL_PATH),
            (new WP_Sitemaps_Posts())->get_url_list(1, 'page'),
        );
    }

    public function test_the_application_form_page_is_not_offered_to_search_engines(): void
    {
        $this->set_permalink_structure('/%postname%/');
        $this->factory->post->create(['post_type' => 'page', 'post_name' => 'stageformulier']);
        $this->factory->post->create(['post_type' => 'page', 'post_name' => 'contact']);

        $paths = $this->pagePaths();

        $this->assertContains('/contact/', $paths);
        $this->assertNotContains('/stageformulier/', $paths);
    }

    public function test_the_sitemap_taxonomies_are_the_two_with_a_page_of_their_own(): void
    {
        $offered = apply_filters('wp_sitemaps_taxonomies', get_taxonomies(['public' => true], 'objects'));

        $this->assertEqualsCanonicalizing(['domain', 'dienst'], array_keys($offered));
    }

    /** @dataProvider listingTypes */
    public function test_every_listing_has_a_description_for_its_meta_tag(string $type): void
    {
        $this->assertNotSame('', trim((string) get_post_type_object($type)?->description));
    }

    /** @return array<string, array{string}> */
    public static function listingTypes(): array
    {
        return ['vacature' => ['vacature'], 'case' => ['case'], 'team' => ['team'], 'tool' => ['tool']];
    }
}
