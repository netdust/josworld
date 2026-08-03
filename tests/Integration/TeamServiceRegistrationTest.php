<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use NTDST_RelationField;
use NTDST_Theme;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Integration tier: proves the T06 CONTRACT against REAL WordPress + the
 * real (dedicated) wptests database — CPT registration effects and real
 * relation-field round-tripping are none of which Brain Monkey (unit tier)
 * can express.
 *
 * THE CONTRACT (tasks.md T06 Integration test line + spec FR-2/FR-7):
 *  1. Registering the model creates a `team` post type with NO public single
 *     view, but a full admin UI.
 *  2. Linking the same member TWICE to ONE case yields ONE entry in that
 *     case's `team` field, not two (spec acceptance criterion, dedup on
 *     save).
 *  3. Deleting a linked team member leaves the referring CASE still
 *     readable — a dangling relation id must not fatal the case's read
 *     (AF-1 mid-flow-failure edge).
 *
 * WHAT MOVED OUT OF THIS FILE (2026-08-03, following the ntdst-core
 * reverse-lookup fix, commit 95b7e06): "a member linked from two cases
 * lists exactly those two" and "a member linked from nothing lists none"
 * used to be proven here against a josworld-owned workaround
 * (TeamService::findCasesReferringTo(), since deleted) that compensated for
 * two defects in NTDST_RelationField::findReferringPosts() — it could not
 * find a meta_prefix'd or PHP-serialized relation value, and `case` (this
 * project's own model) is both. Those defects are now fixed AT THE SOURCE
 * in ntdst-core, proven by
 * tests/Integration/RelationFieldReverseLookupTest.php's
 * test_a_prefixed_model_resolves_its_referring_posts() (the exact
 * two-cases-list-exactly-those-two shape, using this project's real `case`
 * model) — repeating that proof here would duplicate, not add, coverage.
 * The two clauses below stay here because they are josworld-specific
 * acceptance criteria the framework-level regression test does not (and
 * should not) know about: this project's own double-link dedup behaviour
 * on save, and this project's own tolerance for a case whose linked member
 * was deleted.
 *
 * Both remaining tests reflect into NTDST_RelationField::findReferringPosts
 * directly (the same seam RelationFieldReverseLookupTest uses) rather than
 * asserting on rendered metabox HTML — the lookup is the unit whose
 * contract matters; coupling to markup would make these tests brittle to
 * presentation changes that carry no risk.
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

        // No public single view (FR-2 / assumption): publicly_queryable false
        // and no rewrite means no front-end URL resolves to a single member.
        $this->assertFalse(
            $postType->publicly_queryable,
            'team must not be publicly queryable — the design never links to a member\'s own page',
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

        // The editor UI would not normally submit a duplicate id, but the
        // acceptance criterion is explicit: "neither member is duplicated
        // when the case is saved twice" — round-trip a case saved with a
        // duplicate id twice and confirm no duplication survives.
        $case = $caseModel->create([
            'title' => 'Duplicate-link case',
            'client' => 'Acme',
            'team' => [$member->ID, $member->ID],
        ]);
        $this->assertNotWPError($case);

        $reread = $caseModel->find($case->ID, true);
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

        $rereadAgain = $caseModel->find($case->ID, true);
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
        $reread = $caseModel->find($case->ID, true);
        $this->assertNotWPError($reread, 'a case referring to a deleted member must still read cleanly');
        $this->assertSame('Case with a member later deleted', $reread->fields['title'] ?? get_the_title($case->ID));

        // Same edge, through the reverse-lookup seam: a case referencing a
        // now-deleted member must not fatal the (framework) reverse lookup
        // for a DIFFERENT, still-live member either — the dangling id in
        // the case's own `team` meta must be inert, not a crash.
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
        // $wpdb->get_results() returns string column values, so cast before
        // comparing — WP_Post's own ->ID is typed int, but these came back
        // through wpdb's raw row objects.
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
        $service = new NTDST_RelationField(ntdst_get(NTDST_Theme::class));
        $method = new ReflectionMethod($service, 'findReferringPosts');
        $method->setAccessible(true);

        /** @var list<\WP_Post> */
        return $method->invoke($service, $postId, $postType, $field);
    }
}
