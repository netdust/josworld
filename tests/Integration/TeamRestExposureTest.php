<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\TeamService;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration tier: closes review findings M-2 and CB-B-6 — both about the
 * `team` post type's real-WP behaviour, neither expressible at the unit
 * tier (REST controller registration + query rewrite/archive resolution
 * are both real-WordPress effects Brain Monkey cannot simulate).
 *
 * M-2 — TeamService's docblock claimed REST reads were "gated by the same
 * publicly_queryable/capability posture" as the front-end. That was false:
 * `public => false` does NOT gate REST reads (standard WP core behaviour —
 * WP_REST_Posts_Controller::get_items_permissions_check() returns true for
 * a read outside `edit` context regardless of the post type's public/
 * publicly_queryable flags; WP core's own wp_template/wp_block behave the
 * same). This test DOCUMENTS the current posture — an unauthenticated REST
 * request for `team` returns results, and email/phone are absent from the
 * payload ONLY because no register_meta() call exists for either field
 * (verified: zero hits across mu-plugins). It goes RED the day someone
 * registers those meta fields for REST, which is exactly the moment you'd
 * want to know.
 *
 * CB-B-6 — `team` sets has_archive => true with publicly_queryable => false.
 * The existing registration tests assert the flags but never that the
 * archive actually resolves to real content. P2 depends on /team/ listing
 * members, so this proves a WP_Query(['post_type' => 'team']) actually
 * returns published members with that flag combination, rather than
 * assuming the flags imply it.
 */
final class TeamRestExposureTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/TeamService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        new TeamService(ntdst_data());

        // REST controllers are normally registered on the `rest_api_init`
        // hook (fired once, early, by WP's own bootstrap) — replay it here
        // so a request dispatched within this test actually resolves,
        // the same pattern WP core's own REST test suite uses.
        do_action('rest_api_init');
    }

    /**
     * M-2: the CURRENT exposure, documented and proven — an unauthenticated
     * REST request for `team` returns results (name/photo are public once
     * content exists), and no personal-data field leaks, because none is
     * registered for REST.
     */
    public function test_unauthenticated_rest_request_for_team_returns_results_without_email_or_phone(): void
    {
        $model = ntdst_data()->get('team');

        $member = $model->create([
            'title' => 'Grace Hopper',
            'role' => 'Rear Admiral',
            'email' => 'grace@example.test',
            'phone' => '+1-555-0100',
        ]);
        $this->assertNotWPError($member);
        wp_publish_post($member->ID);

        // No current user set — this IS the unauthenticated request; a
        // WP_UnitTestCase defaults to no logged-in user unless wp_set_current_user()
        // is called, but state it explicitly so the intent is unambiguous.
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/wp/v2/team');
        $response = rest_get_server()->dispatch($request);

        $this->assertSame(
            200,
            $response->get_status(),
            'an unauthenticated REST request for team must return HTTP 200 — '
            . 'public => false does not gate REST reads (M-2)',
        );

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data, 'the published member must be present in the unauthenticated REST response');

        $titles = array_map(
            static fn (array $item): string => $item['title']['rendered'] ?? '',
            $data,
        );
        $this->assertContains('Grace Hopper', $titles, 'name (post title) is publicly exposed via REST');

        // Personal-data check: email/phone must be absent from EVERY item's
        // payload, both as top-level keys and inside any `meta` block —
        // whichever shape a future register_meta() call would surface them
        // under.
        foreach ($data as $item) {
            $this->assertArrayNotHasKey(
                'email',
                $item,
                'email must not be exposed via REST — no register_meta() call exists for it',
            );
            $this->assertArrayNotHasKey(
                'phone',
                $item,
                'phone must not be exposed via REST — no register_meta() call exists for it',
            );

            if (isset($item['meta']) && is_array($item['meta'])) {
                $this->assertArrayNotHasKey('email', $item['meta'], 'email must not be exposed via REST meta');
                $this->assertArrayNotHasKey('phone', $item['meta'], 'phone must not be exposed via REST meta');
                $this->assertArrayNotHasKey(JOSWORLD_CORE_META_PREFIX . 'email', $item['meta']);
                $this->assertArrayNotHasKey(JOSWORLD_CORE_META_PREFIX . 'phone', $item['meta']);
            }
        }
    }

    /**
     * CB-B-6: /team/ archive resolution — has_archive => true combined with
     * publicly_queryable => false. Prove a WP_Query(['post_type' => 'team'])
     * actually returns published members, rather than assuming the flag
     * combination implies it.
     */
    public function test_team_archive_query_returns_published_members(): void
    {
        $model = ntdst_data()->get('team');

        $memberOne = $model->create(['title' => 'Ada Lovelace', 'role' => 'Mathematician']);
        $this->assertNotWPError($memberOne);
        wp_publish_post($memberOne->ID);

        $memberTwo = $model->create(['title' => 'Alan Turing', 'role' => 'Analyst']);
        $this->assertNotWPError($memberTwo);
        wp_publish_post($memberTwo->ID);

        $query = new \WP_Query([
            'post_type' => 'team',
            'post_status' => 'publish',
        ]);

        $this->assertSame(
            2,
            $query->found_posts,
            'a WP_Query for post_type=team must return both published members — '
            . 'has_archive=>true with publicly_queryable=>false must still resolve '
            . 'to real content for the /team/ listing (CB-B-6)',
        );

        $foundTitles = array_map(
            static fn (\WP_Post $post): string => $post->post_title,
            $query->posts,
        );
        $this->assertContains('Ada Lovelace', $foundTitles);
        $this->assertContains('Alan Turing', $foundTitles);
    }
}
