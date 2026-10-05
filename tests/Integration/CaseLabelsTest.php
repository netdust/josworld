<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * The case card's label row: the `domain` term as a pill link, then the
 * `dienst` terms as plain text.
 */
final class CaseLabelsTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/TeamService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/VacatureService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/ToolService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        // All four services, so "attached to NO other type" checks against
        // real siblings, not an absence of competition.
        new CaseService(ntdst_data());
        new TeamService(ntdst_data());
        new VacatureService(ntdst_data());
        new ToolService(ntdst_data());
    }

    /**
     * @param list<string> $domain
     * @param list<string> $diensten
     */
    private function caseWith(array $domain, array $diensten): object
    {
        $id = self::factory()->post->create(['post_type' => 'case', 'post_status' => 'publish']);
        wp_set_object_terms($id, $domain, 'domain');
        wp_set_object_terms($id, $diensten, 'dienst');

        return get_post($id);
    }

    public function test_domain_is_a_case_only_taxonomy(): void
    {
        $this->assertTrue(is_object_in_taxonomy('case', 'domain'));
        $this->assertFalse(is_object_in_taxonomy('post', 'domain'));
    }

    public function test_labels_are_a_domain_pill_link_then_plain_diensten_text(): void
    {
        $html = \josworld\services\content\resolve_case_labels(
            $this->caseWith(['Overheid'], ['Campagne', 'Digitaal', 'Strategie'])
        );

        $this->assertMatchesRegularExpression(
            '#^<a rel="tag" href="[^"]*(?:/domein/|domain=)overheid/?">Overheid</a>'
            . '<span class="jw-diensten">Campagne, Digitaal &amp; Strategie</span>$#',
            $html
        );
    }

    public function test_labels_without_a_domain_show_only_the_diensten(): void
    {
        $html = \josworld\services\content\resolve_case_labels($this->caseWith([], ['Campagne']));

        $this->assertSame('<span class="jw-diensten">Campagne</span>', $html);
    }

    public function test_labels_are_null_without_terms(): void
    {
        $this->assertNull(\josworld\services\content\resolve_case_labels($this->caseWith([], [])));
    }
}
