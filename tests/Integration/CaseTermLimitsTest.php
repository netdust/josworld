<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * Client spec: a case has at most ONE domain and at most THREE diensten, picked from a fixed list
 * (editors choose, they do not invent). Enforced where the terms are written, whatever the screen.
 */
final class CaseTermLimitsTest extends WP_UnitTestCase
{
    /** @return list<int> */
    private function terms(string $taxonomy, int $count): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; $i++) {
            $term = wp_insert_term("{$taxonomy} {$i}", $taxonomy);
            $this->assertIsArray($term);
            $ids[] = (int) $term['term_id'];
        }

        return $ids;
    }

    private function newCase(): int
    {
        return self::factory()->post->create(['post_type' => 'case', 'post_status' => 'publish']);
    }

    public function test_a_case_keeps_only_its_first_domain(): void
    {
        $domains = $this->terms('domain', 3);
        $case = $this->newCase();

        wp_set_object_terms($case, $domains, 'domain');

        $this->assertSame([$domains[0]], wp_get_object_terms($case, 'domain', ['fields' => 'ids']));
    }

    public function test_a_case_keeps_only_its_first_three_diensten(): void
    {
        $diensten = $this->terms('dienst', 5);
        $case = $this->newCase();

        wp_set_object_terms($case, $diensten, 'dienst');

        $kept = wp_get_object_terms($case, 'dienst', ['fields' => 'ids']);
        $this->assertEqualsCanonicalizing(array_slice($diensten, 0, 3), $kept);
    }

    public function test_appending_past_the_limit_is_cut_as_well(): void
    {
        $diensten = $this->terms('dienst', 4);
        $case = $this->newCase();
        wp_set_object_terms($case, array_slice($diensten, 0, 3), 'dienst');

        wp_set_object_terms($case, [$diensten[3]], 'dienst', true);

        $this->assertCount(3, wp_get_object_terms($case, 'dienst', ['fields' => 'ids']));
    }

    public function test_the_limits_do_not_touch_other_taxonomies(): void
    {
        $case = $this->newCase();
        $tags = ['a', 'b', 'c', 'd'];

        wp_set_object_terms($case, $tags, 'post_tag');

        $this->assertCount(4, wp_get_object_terms($case, 'post_tag'));
    }

    /** @dataProvider taxonomies */
    public function test_editors_choose_from_the_list_only_administrators_change_it(string $taxonomy): void
    {
        $editor = self::factory()->user->create(['role' => 'editor']);
        $admin = self::factory()->user->create(['role' => 'administrator']);
        $cap = get_taxonomy($taxonomy)->cap;

        $this->assertTrue(user_can($editor, $cap->assign_terms));
        $this->assertFalse(user_can($editor, $cap->edit_terms), 'an editor can not add a new term');
        $this->assertTrue(user_can($admin, $cap->edit_terms));
        $this->assertTrue(get_taxonomy($taxonomy)->hierarchical, 'a checkbox list, not a free-text tag box');
    }

    /** @return array<string, array{string}> */
    public static function taxonomies(): array
    {
        return ['domain' => ['domain'], 'dienst' => ['dienst']];
    }
}
