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
            'benefits' => [
                ['benefit' => 'Saves time'],
                ['benefit' => 'No sign-up required'],
            ],
        ]);

        $this->assertNotWPError($created, 'a tool with no file must still save');

        $reread = $model->find($created->ID, 'any');
        $this->assertNotWPError($reread);
        $this->assertSame('Free Audit Checklist', $reread->fields['title'] ?? get_the_title($created->ID));
    }

    public function test_a_tool_relating_two_other_tools_returns_exactly_two(): void
    {
        $model = ntdst_data()->get('tool');

        $toolA = $model->create(['title' => 'Tool A', 'subtitle' => 'A']);
        $this->assertNotWPError($toolA);

        $toolB = $model->create(['title' => 'Tool B', 'subtitle' => 'B']);
        $this->assertNotWPError($toolB);

        $mainTool = $model->create([
            'title' => 'Main Tool',
            'subtitle' => 'The one that relates to two others',
            'related_tools' => [$toolA->ID, $toolB->ID],
        ]);
        $this->assertNotWPError($mainTool);

        $reread = $model->find($mainTool->ID, 'any');
        $this->assertNotWPError($reread);

        $related = $reread->fields['related_tools'] ?? [];
        $this->assertCount(2, $related, 'a tool relating two other tools must return exactly two');
        $this->assertContains($toolA->ID, array_map('intval', $related));
        $this->assertContains($toolB->ID, array_map('intval', $related));
    }

    /**
     * The tool->tool SELF-relation edge: a deleted related tool must leave
     * the surviving tool readable — the same-model shape needs its own
     * proof, distinct from the cross-model case->team one.
     */
    public function test_deleting_a_related_tool_leaves_the_surviving_tool_readable(): void
    {
        $model = ntdst_data()->get('tool');

        $toolB = $model->create(['title' => 'Tool B (to be deleted)', 'subtitle' => 'B']);
        $this->assertNotWPError($toolB);

        $toolA = $model->create([
            'title' => 'Tool A (survives)',
            'subtitle' => 'A',
            'related_tools' => [$toolB->ID],
        ]);
        $this->assertNotWPError($toolA);

        wp_delete_post($toolB->ID, true);

        $reread = $model->find($toolA->ID, 'any');
        $this->assertNotWPError($reread, 'a tool relating to a since-deleted tool must still read cleanly');
        $this->assertSame('Tool A (survives)', $reread->fields['title'] ?? get_the_title($toolA->ID));

        // Relation reads never dereference into WP_Post, so the dangling id
        // stays present as a plain int — asserted, not assumed.
        $related = $reread->fields['related_tools'] ?? [];
        $this->assertIsArray($related, 'related_tools must still be a well-formed array after the target is deleted');
        $this->assertContains(
            (int) $toolB->ID,
            array_map('intval', $related),
            'the dangling related_tools id is expected to remain present but inert — '
            . 'relation reads never dereference into a WP_Post (Data.php formatMeta())',
        );
    }
}
