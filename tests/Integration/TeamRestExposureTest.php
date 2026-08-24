<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\TeamService;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Pins the `team` REST posture: the public flags do NOT gate REST reads,
 * so name/photo are readable unauthenticated, and email/phone stay private
 * ONLY because nothing calls register_meta() for them — this goes RED the
 * day someone does. Also proves the /team/ archive query returns real
 * content rather than assuming the registration flags imply it.
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

        // Replay rest_api_init so a dispatched request resolves — the same
        // pattern WP core's own REST test suite uses.
        do_action('rest_api_init');
    }

    /**
     * The current exposure, proven: unauthenticated REST returns members,
     * and no personal-data field leaks because none is registered for REST.
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

        // Explicitly unauthenticated.
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

        // email/phone must be absent both top-level and in any `meta` block
        // — whichever shape a future register_meta() would surface them under.
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
     * The /team/ archive must resolve to real content — proven by query,
     * not assumed from the registration flags.
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
