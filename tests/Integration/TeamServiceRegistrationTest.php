<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\TeamService;
use WP_UnitTestCase;

/**
 * Proves the `team` registration contract against real WordPress: no
 * public single view but a full admin UI. Reverse lookup (the `case` ->
 * `team` relation, when case still carried it) is covered by
 * RelationFieldReverseLookupTest.
 */
final class TeamServiceRegistrationTest extends WP_UnitTestCase
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
    }

    public function test_registering_the_model_creates_a_team_post_type_with_a_public_single_and_editor_support(): void
    {
        $this->assertTrue(post_type_exists('team'), 'TeamService must register a `team` post type');

        $postType = get_post_type_object('team');
        $this->assertNotNull($postType);

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

        $this->assertTrue(post_type_supports('team', 'editor'), 'the builder needs editor support on team');
    }
}
