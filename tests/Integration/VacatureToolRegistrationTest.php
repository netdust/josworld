<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Proves the `vacature` + `tool` registration and write-path contract
 * against real WordPress. The denial path is the substance: a `select`
 * field renders a closed dropdown but does NOT validate on save, so the
 * tests write through the Data API (as a raw POST would) and confirm a
 * bad employment type never reaches wp_postmeta.
 */
final class VacatureToolRegistrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/VacatureService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/ToolService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        new VacatureService(ntdst_data());
        new ToolService(ntdst_data());
    }

    public function test_both_post_types_register_as_public_with_an_archive(): void
    {
        $this->assertTrue(post_type_exists('vacature'), 'VacatureService must register a `vacature` post type');
        $this->assertTrue(post_type_exists('tool'), 'ToolService must register a `tool` post type');

        $vacature = get_post_type_object('vacature');
        $this->assertNotNull($vacature);
        $this->assertTrue($vacature->public, 'vacature must be public');
        $this->assertTrue($vacature->has_archive, 'vacature must have an archive (FR-3)');

        $tool = get_post_type_object('tool');
        $this->assertNotNull($tool);
        $this->assertTrue($tool->public, 'tool must be public');
        $this->assertTrue($tool->has_archive, 'tool must have an archive (FR-4)');
    }

    /**
     * Pins `thumbnail` support: dropping it from `supports` regresses
     * silently — the tool templates keep rendering with a hole.
     */
    public function test_a_tool_supports_a_featured_image(): void
    {
        $this->assertTrue(
            post_type_supports('tool', 'thumbnail'),
            'tool must support a featured image — the archive and detail templates bind featuredImage'
        );
    }

    /**
     * The denial path: an employment type outside the closed list must be
     * rejected before the write — WP_Error back, nothing persisted.
     */
    public function test_an_employment_type_outside_the_registered_list_is_rejected_rather_than_persisted(): void
    {
        $model = ntdst_data()->get('vacature');

        $countBefore = wp_count_posts('vacature')->publish ?? 0;

        $result = $model->create([
            'title' => 'Rogue vacancy',
            'content' => 'Should never be written',
            'employment_type' => 'internship', // NOT in the registered list
        ]);

        $this->assertWPError(
            $result,
            'an employment type outside the closed list must be rejected, not silently accepted',
        );

        $countAfter = wp_count_posts('vacature')->publish ?? 0;
        $this->assertSame(
            $countBefore,
            $countAfter,
            'a rejected vacancy must not be persisted at all — the denial must happen before the write',
        );

        // No post with this title anywhere — and so no meta row either.
        $query = new \WP_Query([
            'post_type' => 'vacature',
            'title' => 'Rogue vacancy',
            'post_status' => 'any',
        ]);
        $this->assertSame(
            0,
            $query->found_posts,
            'a rejected vacancy title must not appear anywhere in wp_posts',
        );
    }

    /**
     * The other half: a valid employment type must still save and re-read —
     * a denial that also rejects good input is worse than none.
     */
    public function test_a_valid_employment_type_saves_and_rereads_correctly(): void
    {
        $model = ntdst_data()->get('vacature');

        // The registered values ARE the labels — 'Voltijds' and
        // 'Freelance', not slug-shaped keys.
        $created = $model->create([
            'title' => 'Marketing Freelancer',
            'content' => 'We are looking for a freelance marketer',
            'employment_type' => 'Freelance',
        ]);

        $this->assertNotWPError($created, 'a valid, registered employment type must be accepted');

        $reread = $model->find($created->ID, 'any');
        $this->assertNotWPError($reread);
        $this->assertSame('Freelance', $reread->fields['employment_type'] ?? null);

        // The other registered value must also round-trip.
        $createdFullTime = $model->create([
            'title' => 'Frontend Developer',
            'content' => 'Join our team full-time',
            'employment_type' => 'Voltijds',
        ]);
        $this->assertNotWPError($createdFullTime, 'the other registered employment type must also be accepted');

        $rereadFullTime = $model->find($createdFullTime->ID, 'any');
        $this->assertSame('Voltijds', $rereadFullTime->fields['employment_type'] ?? null);
    }

    public function test_a_tool_saved_with_no_file_still_saves(): void
    {
        $model = ntdst_data()->get('tool');

        $created = $model->create([
            'title' => 'Free Audit Checklist',
            'subtitle' => 'Everything you need to check',
        ]);

        $this->assertNotWPError($created, 'a tool with no file must still save');

        $reread = $model->find($created->ID, 'any');
        $this->assertNotWPError($reread);
        $this->assertSame('Free Audit Checklist', $reread->fields['title'] ?? get_the_title($created->ID));
    }
}
