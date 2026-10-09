<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * A case belongs to ONE of two target groups — "Voor publieke sector" or "Voor social profit & ondernemers".
 * A pick-from-the-list field like the domain, but with no public overview of its own.
 */
final class CaseDoelgroepTest extends WP_UnitTestCase
{
    public function test_the_doelgroep_taxonomy_belongs_to_cases_only(): void
    {
        $this->assertTrue(taxonomy_exists('doelgroep'));
        $this->assertTrue(is_object_in_taxonomy('case', 'doelgroep'));

        foreach (['post', 'page', 'team', 'vacature', 'tool'] as $other) {
            $this->assertFalse(is_object_in_taxonomy($other, 'doelgroep'), "not on {$other}");
        }
    }

    public function test_it_is_a_pick_list_with_no_public_overview(): void
    {
        $taxonomy = get_taxonomy('doelgroep');

        $this->assertTrue($taxonomy->hierarchical, 'a checkbox list, not a free-text tag box');
        $this->assertFalse($taxonomy->publicly_queryable, 'no front-end archive');
        $this->assertFalse($taxonomy->rewrite, 'no URL of its own');
        $this->assertTrue($taxonomy->show_ui);
        $this->assertTrue($taxonomy->show_in_rest);

        $editor = self::factory()->user->create(['role' => 'editor']);
        $this->assertTrue(user_can($editor, $taxonomy->cap->assign_terms));
        $this->assertFalse(user_can($editor, $taxonomy->cap->edit_terms), 'only administrators change the list');
    }

    public function test_a_case_is_in_either_group_never_both(): void
    {
        $publiek = wp_insert_term('Voor publieke sector', 'doelgroep');
        $social = wp_insert_term('Voor social profit & ondernemers', 'doelgroep');
        $this->assertIsArray($publiek);
        $this->assertIsArray($social);
        $case = self::factory()->post->create(['post_type' => 'case', 'post_status' => 'publish']);

        wp_set_object_terms($case, [$publiek['term_id'], $social['term_id']], 'doelgroep');

        $this->assertSame(
            [(int) $publiek['term_id']],
            array_map('intval', wp_get_object_terms($case, 'doelgroep', ['fields' => 'ids']))
        );
    }
}
