<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Functions;
use josworld\services\admin\AdminAppService;

/**
 * The dashboard's tile data, without WordPress.
 *
 * The substance is the negative cases: a positive-only assertion passes
 * just as happily against a dashboard that shows every user an "add new"
 * link they cannot use, or that prints a draft line reading "0 concepten"
 * on a type with none.
 */
final class AdminAppDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        require_once dirname(__DIR__, 2)
            . '/app/content/mu-plugins/josworld-core/services/admin/AdminAppService.php';
    }

    /** @param array<string, int> $counts */
    private function stubWordPress(array $counts, bool $canCreate): void
    {
        Functions\when('add_filter')->justReturn(true);
        Functions\when('__')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('current_user_can')->justReturn($canCreate);
        Functions\when('admin_url')->alias(
            static fn(string $path): string => 'https://josworld.test/wp/wp-admin/' . $path,
        );
        Functions\when('wp_count_posts')->justReturn((object) $counts);
        Functions\when('get_post_type_object')->justReturn(
            (object) ['cap' => (object) ['create_posts' => 'edit_cases']],
        );
    }

    public function test_a_tile_reports_the_published_count_for_every_content_type(): void
    {
        $this->stubWordPress(['publish' => 12, 'draft' => 0], true);

        $tiles = (new AdminAppService())->tiles();

        $this->assertCount(4, $tiles, 'one tile per content type');
        $this->assertSame(
            ['Cases', 'Teamleden', 'Vacatures', 'Tools'],
            array_column($tiles, 'label'),
        );
        foreach ($tiles as $tile) {
            $this->assertSame(12, $tile['count']);
        }
    }

    public function test_the_draft_count_is_carried_only_when_there_are_drafts(): void
    {
        $this->stubWordPress(['publish' => 3, 'draft' => 2], true);
        $this->assertSame(2, (new AdminAppService())->tiles()[0]['drafts']);

        $this->stubWordPress(['publish' => 3, 'draft' => 0], true);
        $this->assertSame(0, (new AdminAppService())->tiles()[0]['drafts']);
    }

    public function test_a_user_who_cannot_create_gets_no_new_link(): void
    {
        $this->stubWordPress(['publish' => 1, 'draft' => 0], false);

        foreach ((new AdminAppService())->tiles() as $tile) {
            $this->assertNull(
                $tile['new_url'],
                'a "Nieuw" link must not be offered to a user who cannot create the type',
            );
        }
    }

    public function test_a_user_who_can_create_gets_a_post_new_link_per_type(): void
    {
        $this->stubWordPress(['publish' => 1, 'draft' => 0], true);

        $urls = array_column((new AdminAppService())->tiles(), 'new_url');

        $this->assertSame([
            'https://josworld.test/wp/wp-admin/post-new.php?post_type=case',
            'https://josworld.test/wp/wp-admin/post-new.php?post_type=team',
            'https://josworld.test/wp/wp-admin/post-new.php?post_type=vacature',
            'https://josworld.test/wp/wp-admin/post-new.php?post_type=tool',
        ], $urls);
    }

    public function test_a_missing_count_object_reads_as_zero_rather_than_fatalling(): void
    {
        $this->stubWordPress([], true);

        $this->assertSame(0, (new AdminAppService())->tiles()[0]['count']);
        $this->assertSame(0, (new AdminAppService())->tiles()[0]['drafts']);
    }
}
