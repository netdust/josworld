<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * ntdst-baseline's duplicate module against real WordPress: what a copy holds is what the
 * source held, byte for byte — the slashing recipe cannot be proven in Brain Monkey — and
 * josworld opts in with Dutch labels.
 */
final class DuplicateRoundTripTest extends WP_UnitTestCase
{
    private const SERVICE = 'ntdst\\baseline\\services\\duplicate\\DuplicateService';
    private const COPIER = 'ntdst\\baseline\\services\\duplicate\\PostCopier';
    private const LAYOUT = '<!-- {"type":"layout","children":[{"props":{"content":'
        . '"<p>caf\u00e9 \"quoted\" a\/b<\/p>"}}]} -->';

    private function copy(\WP_Post $source): \WP_Post
    {
        // As the door runs it: a user holding `unfiltered_html`, or kses mangles the layout comment.
        $adminId = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($adminId);
        $copier = ntdst_get(self::COPIER);
        $newId = $copier->copy($source, $adminId, ntdst_get(self::SERVICE)->config());

        $this->assertIsInt($newId);

        return get_post($newId);
    }

    public function test_a_builder_page_copies_its_layout_and_meta_byte_for_byte(): void
    {
        $id = self::factory()->post->create(
            ['post_type' => 'page', 'post_title' => 'Voor wie', 'post_status' => 'publish']
        );
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the layout must be stored unfiltered, as the builder saves it
        $wpdb->update($wpdb->posts, ['post_content' => self::LAYOUT], ['ID' => $id]);
        clean_post_cache($id);
        update_post_meta($id, '_jw_text', wp_slash('back\\slash and "quotes"'));
        update_post_meta($id, '_jw_list', wp_slash(['a' => ['b\\c', 'd'], 'e' => 1]));
        add_post_meta($id, '_jw_multi', 'one');
        add_post_meta($id, '_jw_multi', 'two');

        $copy = $this->copy(get_post($id));

        $this->assertSame(self::LAYOUT, $copy->post_content, 'the builder layout survives the copy untouched');
        $this->assertSame('draft', $copy->post_status);
        $this->assertSame('Voor wie (kopie)', $copy->post_title);
        $this->assertSame('back\\slash and "quotes"', get_post_meta($copy->ID, '_jw_text', true));
        $this->assertSame(['a' => ['b\\c', 'd'], 'e' => 1], get_post_meta($copy->ID, '_jw_list', true));
        $this->assertSame(['one', 'two'], get_post_meta($copy->ID, '_jw_multi'));
        $this->assertSame('', get_post_meta($copy->ID, '_edit_lock', true), 'the edit lock is not copied');
    }

    public function test_a_case_copies_its_fields_and_terms_and_keeps_pointing_at_the_same_records(): void
    {
        $this->assertTrue(post_type_exists('case'));
        $related = self::factory()->post->create(['post_type' => 'page']);
        $id = self::factory()->post->create(
            ['post_type' => 'case', 'post_title' => 'Case', 'post_status' => 'publish']
        );
        update_post_meta($id, '_jw_client', 'Acme');
        update_post_meta($id, '_jw_related', [$related]);
        wp_set_object_terms($id, ['Campagne', 'Digitaal'], 'dienst');
        wp_set_object_terms($id, ['Overheid'], 'domain');

        $copy = $this->copy(get_post($id));

        $this->assertSame('case', $copy->post_type);
        $this->assertSame('Acme', get_post_meta($copy->ID, '_jw_client', true));
        $this->assertSame([$related], get_post_meta($copy->ID, '_jw_related', true), 'a relation is copied as stored');
        $names = ['fields' => 'names'];
        $this->assertEqualsCanonicalizing(['Campagne', 'Digitaal'], wp_get_object_terms($copy->ID, 'dienst', $names));
        $this->assertSame(['Overheid'], wp_get_object_terms($copy->ID, 'domain', $names));
    }

    public function test_the_copy_keeps_the_sources_language_and_is_not_a_translation(): void
    {
        if (! function_exists('pll_set_post_language') || ! function_exists('pll_get_post_translations')) {
            $this->markTestSkipped(
                'Polylang loads its API only once a language exists, and the wptests database has none.'
            );
        }

        $id = self::factory()->post->create(['post_type' => 'page', 'post_status' => 'publish']);
        $lang = pll_get_post_language($id); // @phpstan-ignore function.notFound
        $copy = $this->copy(get_post($id));

        $this->assertSame($lang, pll_get_post_language($copy->ID)); // @phpstan-ignore function.notFound
        $group = array_values(pll_get_post_translations($copy->ID));
        $this->assertSame([$copy->ID], $group, 'the copy is on its own, not grouped with the source');
    }

    public function test_the_duplicate_link_is_offered_in_dutch_to_an_administrator(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $page = get_post(self::factory()->post->create(['post_type' => 'page', 'post_status' => 'publish']));

        $actions = apply_filters('page_row_actions', [], $page);

        $this->assertArrayHasKey('ntdst_duplicate', $actions);
        $this->assertStringContainsString('Dupliceren', $actions['ntdst_duplicate']);
    }
}
