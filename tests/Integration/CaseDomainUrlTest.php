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
    protected function setUp(): void
    {
        parent::setUp();

        $this->set_permalink_structure('/%postname%/');
        // Both types registered at boot, before permalinks existed, and WordPress only builds their rules
        // when they did: register them again as the site does on a real request.
        register_post_type('case', (array) get_post_type_object('case'));
        register_taxonomy('domain', 'case', (array) get_taxonomy('domain'));
        flush_rewrite_rules();
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
}
