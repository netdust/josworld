<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Integration tier: proves the T07 CONTRACT against REAL WordPress + the
 * real (dedicated) wptests database — CPT registration effects and real
 * write-path validation are none of which Brain Monkey (unit tier) can
 * express.
 *
 * THE CONTRACT (tasks.md T07 Integration test line + spec FR-3/FR-4/FR-8/
 * FR-9):
 *  1. Both `vacature` and `tool` register as public post types with an
 *     archive.
 *  2. THE DENIAL PATH (the substance of this task, per the task brief): an
 *     employment type outside the registered closed list
 *     (`full_time`/`freelance`) is REJECTED rather than persisted. `select`
 *     renders a closed dropdown but does NOT validate on save — confirmed
 *     against MetaboxGenerator.php (a `<select>` and nothing else) and
 *     Data.php (validateData() only enforces a `validate` callback this
 *     project supplies). Proving "the dropdown lists exactly two options"
 *     would be a PRESENCE check and would NOT satisfy this contract — the
 *     assertion below writes directly through the Data API (bypassing the
 *     dropdown entirely, the same as a raw POST or a WP-CLI write would)
 *     and confirms the bad value never reaches wp_postmeta.
 *  3. THE FAILURE MODE VERIFIED BOTH WAYS: a valid employment type (from the
 *     closed list) must still save and re-read correctly — a denial test
 *     that also rejects good input is worse than none (task brief).
 *  4. A tool saved with NO file still saves (the file relation is optional).
 *  5. A tool relating two other tools returns exactly two.
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
     * THE DENIAL PATH — the substance of this task. An employment type
     * outside the closed list must be rejected rather than persisted: the
     * create() call must return a WP_Error, and no `_jw_employment_type`
     * meta row (nor any post at all, since create() rolls back on
     * validation failure before wp_insert_post runs) may exist afterwards.
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

        // Belt + braces: confirm no vacature post with this title exists,
        // and therefore no meta row for it either (a post that was never
        // created cannot carry meta).
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
     * The other half of the failure-mode check the task brief requires: a
     * VALID employment type must still save and re-read correctly. A
     * denial test that also rejects good input would be worse than none.
     */
    public function test_a_valid_employment_type_saves_and_rereads_correctly(): void
    {
        $model = ntdst_data()->get('vacature');

        $created = $model->create([
            'title' => 'Marketing Freelancer',
            'content' => 'We are looking for a freelance marketer',
            'employment_type' => 'freelance',
        ]);

        $this->assertNotWPError($created, 'a valid, registered employment type must be accepted');

        $reread = $model->find($created->ID, true);
        $this->assertNotWPError($reread);
        $this->assertSame('freelance', $reread->fields['employment_type'] ?? null);

        // The other registered value must also round-trip.
        $createdFullTime = $model->create([
            'title' => 'Frontend Developer',
            'content' => 'Join our team full-time',
            'employment_type' => 'full_time',
        ]);
        $this->assertNotWPError($createdFullTime, 'the other registered employment type must also be accepted');

        $rereadFullTime = $model->find($createdFullTime->ID, true);
        $this->assertSame('full_time', $rereadFullTime->fields['employment_type'] ?? null);
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

        $reread = $model->find($created->ID, true);
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

        $reread = $model->find($mainTool->ID, true);
        $this->assertNotWPError($reread);

        $related = $reread->fields['related_tools'] ?? [];
        $this->assertCount(2, $related, 'a tool relating two other tools must return exactly two');
        $this->assertContains($toolA->ID, array_map('intval', $related));
        $this->assertContains($toolB->ID, array_map('intval', $related));
    }

    /**
     * CB-B-3 (review finding): the AF-4 mid-flow-failure edge — "related
     * tool deleted -> surviving tool still reads" — mirrors
     * TeamServiceRegistrationTest::
     * test_deleting_a_linked_team_member_leaves_the_referring_case_readable(),
     * the case->team equivalent, but this project's own tool->tool
     * self-relation (`related_tools`, FR-8) had no equivalent proof.
     *
     * Deliberately a same-model self-relation (tool relates to tool), not
     * the cross-model case->team shape — the risk this closes is specific
     * to `related_tools` pointing at another `tool` post, per this task's
     * brief.
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

        // The surviving tool must still read cleanly — no WP_Error, no
        // fatal from dereferencing the now-dangling related_tools id.
        $reread = $model->find($toolA->ID, true);
        $this->assertNotWPError($reread, 'a tool relating to a since-deleted tool must still read cleanly');
        $this->assertSame('Tool A (survives)', $reread->fields['title'] ?? get_the_title($toolA->ID));

        // The relation field degrades sanely: formatMeta()'s `relation` case
        // (Data.php) only intval()s the stored ids — it never dereferences
        // them into a WP_Post — so the dangling id is expected to remain
        // present as a plain int, not silently vanish or fatal. Assert that
        // explicitly rather than assuming either shape.
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
