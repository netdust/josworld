<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * Client spec: the overview of one domain lives at /ons-werk/<domain> (not /domein/<domain>), next to the
 * cases at /ons-werk/<case>. Both share one URL level, so a term and a case must never fight over a slug
 * — against real WordPress, because the rewrite rules and `parse_request` decide it.
 */
final class CaseDomainUrlTest extends WP_UnitTestCase
{
    private ?\WP_Post_Type $originalCase = null;
    private ?\WP_Taxonomy $originalDomain = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCase = clone get_post_type_object('case');
        $this->originalDomain = clone get_taxonomy('domain');

        $this->set_permalink_structure('/%postname%/');
        // Both types registered at boot, before permalinks existed, and WordPress only builds their rules
        // when they did: register them again as the site does on a real request.
        register_post_type('case', (array) get_post_type_object('case'));
        register_taxonomy('domain', 'case', (array) get_taxonomy('domain'));
        flush_rewrite_rules();
    }

    protected function tearDown(): void
    {
        // The registrations above are rebuilt for the rewrite rules: hand the REAL ones back to later tests.
        $GLOBALS['wp_post_types']['case'] = $this->originalCase;
        $GLOBALS['wp_taxonomies']['domain'] = $this->originalDomain;
        $this->set_permalink_structure('');
        flush_rewrite_rules();

        parent::tearDown();
    }

    private function domain(string $name): \WP_Term
    {
        $term = wp_insert_term($name, 'domain');
        $this->assertIsArray($term);

        return get_term((int) $term['term_id'], 'domain');
    }

    public function test_a_domain_overview_is_ons_werk_slash_the_domain(): void
    {
        $klimaat = $this->domain('Klimaat');

        $this->assertSame(home_url('/ons-werk/klimaat/'), get_term_link($klimaat));
        $this->go_to(home_url('/ons-werk/klimaat/'));

        $this->assertTrue(is_tax('domain', 'klimaat'), 'the domain overview resolves');
    }

    public function test_a_case_still_lives_at_ons_werk_slash_its_slug(): void
    {
        $this->domain('Klimaat');
        $id = self::factory()->post->create(
            ['post_type' => 'case', 'post_status' => 'publish', 'post_name' => 'energiecollectief']
        );

        $this->go_to(home_url('/ons-werk/energiecollectief/'));

        $this->assertTrue(is_singular('case'));
        $this->assertSame($id, get_queried_object_id());
    }

    public function test_an_unknown_slug_under_ons_werk_is_a_404_not_an_empty_overview(): void
    {
        $this->go_to(home_url('/ons-werk/bestaat-niet/'));

        $this->assertTrue(is_404());
    }

    public function test_a_case_can_not_take_a_domains_slug(): void
    {
        $this->domain('Klimaat');
        $id = self::factory()->post->create(
            ['post_type' => 'case', 'post_status' => 'publish', 'post_title' => 'Klimaat', 'post_name' => 'klimaat']
        );

        $this->assertNotSame('klimaat', get_post_field('post_name', $id), 'the slug is kept free for the overview');
        $this->go_to(home_url('/ons-werk/klimaat/'));
        $this->assertTrue(is_tax('domain', 'klimaat'));
    }

    public function test_two_cases_that_both_lose_a_domains_slug_do_not_share_the_next_one(): void
    {
        $this->domain('Klimaat');
        $args = ['post_type' => 'case', 'post_status' => 'publish', 'post_name' => 'klimaat'];
        $first = self::factory()->post->create($args);
        $second = self::factory()->post->create($args);

        $slugs = [get_post_field('post_name', $first), get_post_field('post_name', $second)];

        $this->assertNotContains('klimaat', $slugs);
        $this->assertCount(2, array_unique($slugs), 'each case keeps its own URL');
    }

    public function test_a_domain_can_not_take_the_slug_of_an_existing_case(): void
    {
        self::factory()->post->create(['post_type' => 'case', 'post_status' => 'publish', 'post_name' => 'energie']);

        $result = wp_insert_term('Energie', 'domain');

        $this->assertWPError($result, 'the case would go dark behind the overview');
        $this->assertNull(term_exists('energie', 'domain'));
    }
}
