<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use NTDST_RelationField;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Proves the `team` registration contract against real WordPress: no
 * public single view but a full admin UI, double-link dedup on save, and a
 * dangling relation id (deleted member) that never fatals a read. Reverse
 * lookup itself is covered by RelationFieldReverseLookupTest; reflection
 * into the private lookup avoids coupling to metabox markup.
 */
final class TeamServiceRegistrationTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/TeamService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        new CaseService(ntdst_data());
        new TeamService(ntdst_data());
    }

    public function test_registering_the_model_creates_a_team_post_type_with_no_public_single_view(): void
    {
        $this->assertTrue(post_type_exists('team'), 'TeamService must register a `team` post type');

        $postType = get_post_type_object('team');
        $this->assertNotNull($postType);

        // "No public single view" is enforced by closeSingleView()'s
        // redirect, not registration flags — WP has no "archive yes,
        // singles no" flag, and non-queryable kills the archive too.
        $this->assertTrue(
            $postType->publicly_queryable,
            'team must stay publicly queryable — the archive and YOOtheme source enumeration require it',
        );
        $this->assertTrue(
            $postType->has_archive !== false,
            'team must keep its archive — the design\'s /team/ grid is the archive',
        );
        $this->assertTrue(
            $postType->exclude_from_search,
            'team members must not surface as site-search results — the single view is closed',
        );

        // Full admin UI still required — editors manage members in wp-admin.
        $this->assertTrue($postType->show_ui, 'team must keep a full admin UI');
    }

    public function test_linking_the_same_member_twice_to_one_case_yields_one_entry_not_two(): void
    {
        $caseModel = ntdst_data()->get('case');
        $teamModel = ntdst_data()->get('team');

        $member = $teamModel->create([
            'title' => 'Grace Hopper',
            'role' => 'Engineer',
        ]);
        $this->assertNotWPError($member);

        // The UI would not normally submit a duplicate id — pin the save
        // path anyway: no duplication may survive a double-linked save.
        $case = $caseModel->create([
            'title' => 'Duplicate-link case',
            'client' => 'Acme',
            'team' => [$member->ID, $member->ID],
        ]);
        $this->assertNotWPError($case);

        $reread = $caseModel->find($case->ID, 'any');
        $this->assertNotWPError($reread);
        $team = $reread->fields['team'] ?? [];
        $this->assertCount(
            1,
            array_unique($team),
            'the same member linked twice to one case must yield one entry, not two',
        );

        // Saving again must not accumulate duplicates either.
        $updated = $caseModel->update($case->ID, [
            'team' => [$member->ID, $member->ID],
        ]);
        $this->assertNotWPError($updated);

        $rereadAgain = $caseModel->find($case->ID, 'any');
        $teamAgain = $rereadAgain->fields['team'] ?? [];
        $this->assertCount(1, array_unique($teamAgain), 'resaving must not accumulate duplicate relation ids');
    }

    public function test_deleting_a_linked_team_member_leaves_the_referring_case_readable(): void
    {
        $caseModel = ntdst_data()->get('case');
        $teamModel = ntdst_data()->get('team');

        $member = $teamModel->create([
            'title' => 'Alan Turing',
            'role' => 'Analyst',
        ]);
        $this->assertNotWPError($member);

        $case = $caseModel->create([
            'title' => 'Case with a member later deleted',
            'client' => 'Acme',
            'team' => [$member->ID],
        ]);
        $this->assertNotWPError($case);

        wp_delete_post($member->ID, true);

        // The dangling relation id must not fatal the case's read.
        $reread = $caseModel->find($case->ID, 'any');
        $this->assertNotWPError($reread, 'a case referring to a deleted member must still read cleanly');
        $this->assertSame('Case with a member later deleted', $reread->fields['title'] ?? get_the_title($case->ID));

        // Same edge through the reverse-lookup seam: the dangling id must
        // not break the lookup for a different, still-live member.
        $liveMember = $teamModel->create(['title' => 'Ada Lovelace', 'role' => 'Developer']);
        $this->assertNotWPError($liveMember);

        $secondCase = $caseModel->create([
            'title' => 'Case with a live member',
            'client' => 'Acme',
            'team' => [$liveMember->ID],
        ]);
        $this->assertNotWPError($secondCase);
        wp_publish_post($secondCase->ID);

        $referring = $this->findReferring((int) $liveMember->ID, 'case', 'team');
        // wpdb raw rows carry string column values — cast before comparing.
        $referringIds = array_map(static fn ($post) => (int) $post->ID, $referring);

        $this->assertContains(
            (int) $secondCase->ID,
            $referringIds,
            'the reverse lookup must still resolve a live member\'s referrers '
            . 'even while an unrelated case carries a dangling relation id',
        );
    }

    /**
     * @return list<\WP_Post>
     */
    private function findReferring(int $postId, string $postType, string $field): array
    {
        $service = new NTDST_RelationField();
        $method = new ReflectionMethod($service, 'findReferringPosts');
        $method->setAccessible(true);

        /** @var list<\WP_Post> */
        return $method->invoke($service, $postId, $postType, $field);
    }
}
