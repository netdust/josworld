<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\TeamService;
use WP_UnitTestCase;

/**
 * Cluster A's behaviour RED (specs/cpt-builder-singles): a CPT single that
 * carries a builder layout is a public page. The team single is the one
 * that was closed by a 301, so it is the one this test pins.
 *
 * @group cluster-red
 */
final class BuilderSinglesTest extends WP_UnitTestCase
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

    public function test_a_team_single_answers_200_and_renders_its_builder_layout(): void
    {
        $member = ntdst_data()->get('team')->create(['title' => 'Grace Hopper', 'role' => 'Engineer']);
        $this->assertNotWPError($member);

        wp_update_post([
            'ID' => $member->ID,
            'post_content' => "\n<!-- {\"type\":\"layout\",\"version\":\"5.0.43\",\"children\":[]} -->",
        ]);

        $this->go_to(get_permalink($member->ID));

        $this->assertTrue(is_singular('team'), 'the request must resolve to the team single');
        $this->assertSame(
            [],
            $this->singleTemplateClosuresFor('team'),
            'no single-template closure may claim the team single — the closed-view 301 ended '
            . 'in exit, so its mere registration is the thing to pin',
        );
        $this->assertTrue(post_type_supports('team', 'editor'), 'the builder needs editor support on team');
    }

    /**
     * The closures NTDST_Pages::single() registered for a post type. The
     * registry is private; reflection is the only read that does not run
     * the closure (which would `exit` the test process).
     *
     * @return array<int, array<string, mixed>>
     */
    private function singleTemplateClosuresFor(string $postType): array
    {
        $property = new \ReflectionProperty(ntdst_pages(), 'template_hooks');
        $property->setAccessible(true);
        $hooks = (array) $property->getValue(ntdst_pages());

        return array_values(array_filter(
            $hooks,
            static fn (array $hook): bool => ($hook['type'] ?? '') === 'single'
                && ($hook['post_type'] ?? null) === $postType,
        ));
    }
}
