<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * A `domain` / `dienst` archive is the Ons werk archive filtered: every case of the term on ONE page.
 * The builder template lists the main query's posts, so the query must not paginate them at the
 * default of 10 — against real WordPress, because only a real main query runs `pre_get_posts`.
 */
final class CaseTaxonomyArchiveTest extends WP_UnitTestCase
{
    /** @return list<int> */
    private function casesIn(string $taxonomy, string $term, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $id = self::factory()->post->create(['post_type' => 'case', 'post_status' => 'publish']);
            wp_set_object_terms($id, [$term], $taxonomy);
        }

        return $ids;
    }

    /** @dataProvider taxonomies */
    public function test_a_case_taxonomy_archive_shows_every_case_of_the_term_on_one_page(string $taxonomy): void
    {
        $this->casesIn($taxonomy, 'Term', 12);
        $link = get_term_link(get_term_by('name', 'Term', $taxonomy), $taxonomy);
        $this->assertIsString($link);

        $this->go_to($link);

        $this->assertTrue(is_tax($taxonomy));
        $this->assertSame(12, $GLOBALS['wp_query']->post_count, 'all twelve cases on the first page, not ten');
        $this->assertFalse($GLOBALS['wp_query']->max_num_pages > 1, 'no second page');
    }

    /** @return array<string, array{string}> */
    public static function taxonomies(): array
    {
        return ['domain' => ['domain'], 'dienst' => ['dienst']];
    }

    public function test_other_archives_keep_their_pagination(): void
    {
        self::factory()->post->create_many(12, ['post_status' => 'publish']);
        update_option('posts_per_page', 10);

        $this->go_to(home_url('/'));

        $this->assertSame(10, $GLOBALS['wp_query']->post_count);
    }
}
