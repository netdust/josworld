<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Integration tier: proves the T08 CONTRACT against REAL WordPress — taxonomy
 * registration + post-type attachment is real-WP behaviour (rewrite rules,
 * `object_type`) that Brain Monkey cannot express, and a wrong `object_type`
 * fails SILENTLY (the taxonomy still registers, still looks correct in the
 * admin, and simply never surfaces terms on the post type that needed them).
 *
 * THE CONTRACT (tasks.md T08 Integration test line):
 *  1. `dienst` is registered and attached to `case` — AND to NO other type
 *     (team, vacature, tool, post). The negative half is the part a wrong
 *     `object_type` breaks silently; asserting only the positive half would
 *     stay green even if `dienst` were attached to everything.
 *  2. Terms attached to a case are retrievable — this file also states
 *     PLAINLY, as a finding rather than a silent substitution, what "order"
 *     actually means here: WP's default term retrieval orders by `name`,
 *     NOT by insertion (confirmed against WP_Term_Query's `orderby` default
 *     and against `wp_set_object_terms()`, which never populates
 *     `wp_term_relationships.term_order` — that column stays 0 for every row
 *     it inserts, so `orderby => 'term_order'` carries no real signal here
 *     either). The test below proves that with terms named specifically to
 *     EXPOSE the difference (a non-alphabetical insertion order), so an
 *     alphabetical default cannot pass by accident.
 *  3. A case with zero diensten returns an empty list, not an error.
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

        // All four content services, so the "attached to case and to NO
        // other type" assertion has real siblings to check against — not
        // just an absence of competition.
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
     * The negative half — this is the assertion a wrong `object_type` fails
     * SILENTLY on. A taxonomy attached to case AND team AND vacature AND
     * tool would still pass "attached to case" while never being what the
     * design needs (a service list scoped to cases only, per spec FR-5's
     * assumption that `dienst` applies to `case` ONLY).
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
     * Proves retrieval actually works, using term names chosen specifically
     * to expose an alphabetical-default bug: "Zoeken" and "Analyse" inserted
     * in THAT (non-alphabetical) order. If retrieval silently defaulted to
     * insertion order, this would need to assert [Zoeken, Analyse]; if it
     * defaults to name order (which WP does — see class docblock), the
     * honest assertion is [Analyse, Zoeken]. This test asserts the latter
     * because that is what `wp_get_object_terms()` / `get_the_terms()`
     * actually return with no explicit `orderby`, and states it as a
     * deliberate finding rather than assuming naive insertion order.
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

        // The FINDING this test proves: WP's default retrieval order is by
        // `name`, not by insertion — so this is what the design's
        // comma-joined list will actually render.
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
