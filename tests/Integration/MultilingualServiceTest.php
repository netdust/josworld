<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use josworld\services\multilingual\MultilingualService;
use WP_UnitTestCase;

/**
 * Active-path half of the multilingual contract: with Polylang active, all
 * four post types plus `dienst` report translatable and NL/EN/FR are
 * configured (NL default).
 *
 * Polylang is not installed; the test defines the same filter hooks and
 * global functions Polylang itself installs (its documented extension
 * contract) to stand in for "active", and asserts josworld's own wiring
 * participates in them. The absence half lives in
 * MultilingualServiceAbsenceTest (a real subprocess) — simulating "absent"
 * in-process would be dishonest once these shims are defined.
 */
final class MultilingualServiceTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/TeamService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/VacatureService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/ToolService.php';
        require_once __DIR__
            . '/../../app/content/mu-plugins/josworld-core/services/multilingual/MultilingualService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        // Content services must be real registered models before
        // MultilingualService runs — the real boot ordering.
        new CaseService(ntdst_data());
        new TeamService(ntdst_data());
        new VacatureService(ntdst_data());
        new ToolService(ntdst_data());

        $this->simulate_polylang_being_active();
    }

    protected function tearDown(): void
    {
        remove_all_filters('pll_get_post_types');
        remove_all_filters('pll_get_taxonomies');
        if (function_exists('pll_languages_list')) {
            // Functions cannot be undefined; reset the backing state instead.
            $GLOBALS['__pll_languages'] = [];
        }

        parent::tearDown();
    }

    private function simulate_polylang_being_active(): void
    {
        // The shim declares GLOBAL functions deliberately — Polylang's own
        // pll_* API is global, and \function_exists() checks the exact name.
        if (! \function_exists('pll_languages_list')) {
            require __DIR__ . '/fixtures/polylang-active-shim.php';
        }

        $GLOBALS['__pll_languages'] = [];
        unset($GLOBALS['__pll_default_language']);
    }

    public function test_all_four_post_types_report_translatable_when_polylang_is_active(): void
    {
        new MultilingualService(ntdst_data());

        $translatablePostTypes = (array) apply_filters('pll_get_post_types', [], false);

        foreach (['case', 'team', 'vacature', 'tool'] as $postType) {
            $this->assertContains(
                $postType,
                $translatablePostTypes,
                "'{$postType}' must report translatable via the pll_get_post_types filter "
                . 'when Polylang is active (FR-10)',
            );
        }
    }

    public function test_dienst_taxonomy_reports_translatable_when_polylang_is_active(): void
    {
        new MultilingualService(ntdst_data());

        $translatableTaxonomies = (array) apply_filters('pll_get_taxonomies', [], false);

        $this->assertContains(
            'dienst',
            $translatableTaxonomies,
            'the dienst taxonomy must report translatable via the pll_get_taxonomies filter '
            . 'when Polylang is active (FR-10) — declaring the four post types translatable '
            . 'without also declaring dienst is the classic partial-coverage miss the plan '
            . 'names (Sibling-site audit: "doing them per-type is the classic partial-coverage miss")',
        );
    }

    public function test_three_languages_are_configured_with_nl_as_default(): void
    {
        new MultilingualService(ntdst_data());

        $configured = pll_languages_list();
        sort($configured);

        $this->assertSame(
            ['en', 'fr', 'nl'],
            $configured,
            'exactly NL, EN and FR must be configured (FR-10)',
        );
        $this->assertSame(
            'nl',
            pll_default_language(),
            'NL must be the default language (FR-10 / plan.md Technical context)',
        );
    }
}
