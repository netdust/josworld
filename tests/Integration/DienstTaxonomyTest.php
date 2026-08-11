<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Proves the `dienst` taxonomy registers, attaches to `case` and to NO
 * other type, and retrieves terms — against real WP, because a wrong
 * `object_type` fails silently (taxonomy registers, terms never surface).
 */
final class DienstTaxonomyTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/TeamService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/VacatureService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/ToolService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        // All four services, so "attached to NO other type" checks against
        // real siblings, not an absence of competition.
        new CaseService(ntdst_data());
        new TeamService(ntdst_data());
        new VacatureService(ntdst_data());
        new ToolService(ntdst_data());
    }

    public function test_dienst_taxonomy_is_registered(): void
    {
        $this->assertTrue(
            taxonomy_exists('dienst'),
            'CaseService must register a `dienst` taxonomy',
        );
    }

    public function test_dienst_is_attached_to_case(): void
    {
        $this->assertContains(
            'dienst',
            get_object_taxonomies('case'),
            '`dienst` must be attached to the `case` post type',
        );

        $this->assertTrue(
            is_object_in_taxonomy('case', 'dienst'),
            '`case` must be registered as an object type for `dienst`',
        );
    }

    /**
     * The negative half a wrong `object_type` fails silently on: attached
     * to everything would still pass "attached to case".
     */
    public function test_dienst_is_attached_to_case_and_no_other_josworld_post_type(): void
    {
        foreach (['team', 'vacature', 'tool', 'post', 'page'] as $otherType) {
            $this->assertFalse(
                is_object_in_taxonomy($otherType, 'dienst'),
                "`dienst` must NOT be attached to `{$otherType}` — FR-5's assumption is case-only",
            );
        }
    }

    /**
     * WP's default term retrieval orders by name, NOT insertion
     * (wp_set_object_terms() never populates term_order). Term names are
     * chosen non-alphabetical so an insertion-order default cannot pass.
     */
    public function test_terms_attached_to_a_case_are_retrievable_and_wp_orders_them_by_name(): void
    {
        $model = ntdst_data()->get('case');
        $created = $model->create([
            'title' => 'Case with services',
            'client' => 'Acme Corp',
        ]);
        $this->assertNotWPError($created);

        // Inserted in this (non-alphabetical) order on purpose.
        $result = wp_set_object_terms($created->ID, ['Zoeken', 'Analyse'], 'dienst');
        $this->assertNotWPError($result);

        $terms = wp_get_object_terms($created->ID, 'dienst', ['fields' => 'names']);
        $this->assertNotWPError($terms);

        $this->assertSame(
            ['Analyse', 'Zoeken'],
            $terms,
            'wp_get_object_terms() with no explicit orderby sorts by term name — '
            . 'insertion order is NOT preserved by the natural WP retrieval API',
        );
    }

    public function test_a_case_with_zero_diensten_returns_an_empty_list_not_an_error(): void
    {
        $model = ntdst_data()->get('case');
        $created = $model->create([
            'title' => 'Case with no services',
            'client' => 'Acme Corp',
        ]);
        $this->assertNotWPError($created);

        $terms = wp_get_object_terms($created->ID, 'dienst', ['fields' => 'names']);

        $this->assertNotWPError($terms, 'a case with zero diensten must not error on term retrieval');
        $this->assertSame(
            [],
            $terms,
            'a case with zero diensten must retrieve an empty list, not a placeholder or error',
        );
    }
}
