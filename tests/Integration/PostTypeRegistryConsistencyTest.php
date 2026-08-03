<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\admin\AdminUIService;
use josworld\services\content\CaseService;
use josworld\services\multilingual\MultilingualService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Integration tier: closes Cluster A review finding CB-4.
 *
 * THE PROBLEM CB-4 NAMED: `AdminUIService::POST_TYPES` is a hand-maintained
 * list (`['case', 'team', 'vacature', 'tool']`). Nothing forced it to match
 * the CPT slugs the content services actually register — a future typo
 * (e.g. T07 registering `vacatures`, plural) would silently stop enqueueing
 * the metabox field JS on that screen with NO test failure anywhere,
 * because AdminUIServiceEnqueueTest registers its own fixture post types
 * rather than depending on the real services.
 *
 * T07: `vacature` and `tool` now register (VacatureService/ToolService), so
 * all four POST_TYPES slugs are real registered content models. Per this
 * file's own TODO (left by T05/T06), the narrow "known slugs are honestly
 * named as pending" tripwire is now retired in favour of the single full
 * equality assertion it was always structured to become —
 * test_every_registered_post_type_matches_admin_ui_service_post_types()
 * below asserts `AdminUIService::POST_TYPES` against the REAL registry
 * directly, with no pending/known-slugs bookkeeping left to drift.
 */
final class PostTypeRegistryConsistencyTest extends WP_UnitTestCase
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

        new CaseService(ntdst_data());
        new TeamService(ntdst_data());
        new VacatureService(ntdst_data());
        new ToolService(ntdst_data());
    }

    public function test_post_types_constant_is_publicly_readable(): void
    {
        // The read-access half of CB-4: a consuming test/reviewer can name
        // the list without instantiating the service or duplicating it.
        $this->assertSame(['case', 'team', 'vacature', 'tool'], AdminUIService::POST_TYPES);
    }

    public function test_case_is_a_real_registered_content_model_and_is_named_in_post_types(): void
    {
        $this->assertContains(
            'case',
            AdminUIService::POST_TYPES,
            'case must remain listed in POST_TYPES while it is a live josworld content model',
        );

        $this->assertTrue(
            ntdst_data()->isRegistered('case'),
            'case must be a REALLY registered ntdst content model, not merely named in the static list — '
            . 'isRegistered() (unlike get()) never auto-creates a phantom model as a side effect',
        );
    }

    /**
     * T06's mirror of the check above — `team` moved from
     * KNOWN_REGISTERED_SLUGS's pending list into the fixture, so it earns
     * the same real-registration proof `case` has.
     */
    public function test_team_is_a_real_registered_content_model_and_is_named_in_post_types(): void
    {
        $this->assertContains(
            'team',
            AdminUIService::POST_TYPES,
            'team must remain listed in POST_TYPES while it is a live josworld content model',
        );

        $this->assertTrue(
            ntdst_data()->isRegistered('team'),
            'team must be a REALLY registered ntdst content model, not merely named in the static list — '
            . 'isRegistered() (unlike get()) never auto-creates a phantom model as a side effect',
        );
    }

    /**
     * T07's mirror of the check above — `vacature` is now a real registered
     * content model (VacatureService).
     */
    public function test_vacature_is_a_real_registered_content_model_and_is_named_in_post_types(): void
    {
        $this->assertContains(
            'vacature',
            AdminUIService::POST_TYPES,
            'vacature must remain listed in POST_TYPES while it is a live josworld content model',
        );

        $this->assertTrue(
            ntdst_data()->isRegistered('vacature'),
            'vacature must be a REALLY registered ntdst content model, not merely named in the static list — '
            . 'isRegistered() (unlike get()) never auto-creates a phantom model as a side effect',
        );
    }

    /**
     * T07's mirror of the check above — `tool` is now a real registered
     * content model (ToolService).
     */
    public function test_tool_is_a_real_registered_content_model_and_is_named_in_post_types(): void
    {
        $this->assertContains(
            'tool',
            AdminUIService::POST_TYPES,
            'tool must remain listed in POST_TYPES while it is a live josworld content model',
        );

        $this->assertTrue(
            ntdst_data()->isRegistered('tool'),
            'tool must be a REALLY registered ntdst content model, not merely named in the static list — '
            . 'isRegistered() (unlike get()) never auto-creates a phantom model as a side effect',
        );
    }

    /**
     * T07: all four POST_TYPES slugs are now real registered content
     * models — the single full-equality assertion this file's TODO (left by
     * T05/T06) named as the eventual strengthening point, now that there is
     * no incomplete world left to special-case. Replaces the narrow
     * "pending slugs are named honestly" tripwire that existed only because
     * `vacature`/`tool` were not yet real models.
     */
    public function test_every_registered_post_type_matches_admin_ui_service_post_types(): void
    {
        $registeredSlugs = array_values(array_filter(
            AdminUIService::POST_TYPES,
            static fn (string $slug): bool => ntdst_data()->isRegistered($slug),
        ));

        $this->assertSame(
            AdminUIService::POST_TYPES,
            $registeredSlugs,
            'every slug in AdminUIService::POST_TYPES must be a REALLY registered ntdst content model — '
            . 'a typo or an unregistered slug here means the metabox field JS silently never enqueues (CB-4)',
        );
    }

    /**
     * The FORWARD direction — the half CB-4 actually needs (review finding
     * CB-4-1, T05 task review).
     *
     * Every other assertion in this file reads
     * POST_TYPES -> registry: "is the slug I expect registered?" That
     * direction cannot see a slug nobody expected. The exact CB-4 scenario —
     * T07 registering `vacatures` (plural) while POST_TYPES still says
     * `vacature` — passes all of them, because `vacature` genuinely IS still
     * unregistered. True statement, wrong reason, silent bug: the field JS
     * never enqueues on that screen and nothing goes red.
     *
     * Proven, not assumed: registering a `vacatures` fixture against the suite
     * as it stood before this test left it green at 21/21.
     *
     * So this reads registry -> POST_TYPES instead. Any josworld-owned model
     * that registers under a slug POST_TYPES does not carry fails HERE, the
     * moment it lands, rather than waiting for a human to notice the fixture
     * above never got updated.
     *
     * Scoped by META PREFIX, not merely by "registered through ntdst_data()".
     * Other suites register their own throwaway models through the same Data
     * API (DataLayerRoundTripTest's `gate_item`, meta_prefix `gate_`), and in
     * a shared test process those leak into the registry — an `isRegistered()`
     * filter alone flags them and this assertion fails on a fixture rather
     * than on a real defect. Verified: the unscoped version failed on
     * `gate_item`.
     *
     * `_jw_` is what makes a model josworld's (FR-12/SC-5), so it is also the
     * honest boundary for "a model this constant is responsible for".
     * WordPress core's own types and third-party CPTs are likewise excluded.
     *
     * CB-B-4 (review finding): candidates are scoped by the `_jw_` META
     * PREFIX, not WP's `public` flag. The prior version filtered candidates
     * via `get_post_types(['public' => true])`, which is what "a model this
     * constant is responsible for" actually meant in T05/T06 — but `team`
     * registers `public => false` (TeamService — FR-2, no public single
     * view), so it escaped that specific net: a typo in TEAM's slug would
     * not have been caught HERE (the other per-model assertions in this
     * file would still catch it — proven below to be a gap, not a hole,
     * before this fix). Iterating ALL registered post types and narrowing
     * by meta prefix instead is the boundary already established in commit
     * 2287cc6 ("`_jw_` is what makes a model josworld's") and is what
     * actually makes a model josworld's, independent of its public-facing
     * posture.
     */
    public function test_no_ntdst_registered_post_type_is_missing_from_the_post_types_constant(): void
    {
        $ours = array_filter(
            get_post_types([], 'names'),
            static function (string $slug): bool {
                if (! ntdst_data()->isRegistered($slug)) {
                    return false;
                }

                return ntdst_data()->get($slug)->getMetaPrefix() === JOSWORLD_CORE_META_PREFIX;
            },
        );

        $this->assertNotEmpty(
            $ours,
            'precondition: at least one josworld model must be registered, or this '
            . 'assertion passes vacuously over an empty set',
        );

        foreach ($ours as $slug) {
            $this->assertContains(
                $slug,
                AdminUIService::POST_TYPES,
                "post type '{$slug}' is registered through ntdst_data() but is NOT in "
                . 'AdminUIService::POST_TYPES — either the slug is a typo, or POST_TYPES '
                . 'needs it. Left as-is, the metabox field JS silently never enqueues on '
                . "the '{$slug}' edit screen (CB-4).",
            );
        }
    }

    /**
     * CB-B-4 (review finding): the forward check above filters candidates
     * via `get_post_types(['public' => true])`. `team` registers with
     * `public => false` (TeamService — FR-2, no public single view), so it
     * escapes that specific net: a typo in TEAM's slug would not be caught
     * by the forward check (the other per-model assertions in this file
     * still catch it, so this was a gap, not a hole).
     *
     * MUTATION PROOF (this test): registers a `_jw_`-prefixed, NON-PUBLIC
     * fixture model under a slug that is deliberately absent from
     * AdminUIService::POST_TYPES, then asserts that the OLD, `public=>true`
     * -filtered forward check fails to see it (proving the gap is real, not
     * theoretical) while the FIXED, prefix-scoped forward check
     * (test_no_ntdst_registered_post_type_is_missing_from_the_post_types_constant
     * above, now scoped by `_jw_` meta prefix instead of WP's `public`
     * flag) correctly flags it.
     */
    public function test_public_true_filter_misses_a_non_public_model_that_prefix_scoping_catches(): void
    {
        register_post_type('jw_fixture_hidden', [
            'public' => false,
            'show_ui' => true,
        ]);
        ntdst_data()->register('jw_fixture_hidden', [
            'label' => 'Fixture (non-public, not in POST_TYPES)',
            'public' => false,
            'meta_prefix' => JOSWORLD_CORE_META_PREFIX,
            'auto_metabox' => false,
        ]);

        // THE GAP, proven: the OLD forward check's own candidate set —
        // `_jw_`-scoped but filtered through `public => true` — does not
        // even contain the fixture, so it could never have flagged the
        // missing-from-POST_TYPES slug.
        $oldForwardCheckCandidates = array_filter(
            get_post_types(['public' => true], 'names'),
            static function (string $slug): bool {
                if (! ntdst_data()->isRegistered($slug)) {
                    return false;
                }

                return ntdst_data()->get($slug)->getMetaPrefix() === JOSWORLD_CORE_META_PREFIX;
            },
        );
        $this->assertNotContains(
            'jw_fixture_hidden',
            $oldForwardCheckCandidates,
            'MUTATION PROOF (gap side): a public=>true-filtered candidate set must NOT '
            . 'contain a non-public model — this is the exact CB-B-4 blind spot',
        );

        // THE FIX, proven: the prefix-scoped candidate set (ALL registered
        // WP post types — no `public` filter — narrowed only by the `_jw_`
        // meta prefix, the actual "is this ours" boundary per FR-12/SC-5)
        // DOES contain it, and therefore the forward check now fails
        // loudly on it — since the fixture is deliberately absent from
        // POST_TYPES, we assert the candidate set contains it (the
        // precondition for the real forward check to fail on it), rather
        // than duplicating the assertion loop.
        $prefixScopedCandidates = array_filter(
            get_post_types([], 'names'),
            static function (string $slug): bool {
                if (! ntdst_data()->isRegistered($slug)) {
                    return false;
                }

                return ntdst_data()->get($slug)->getMetaPrefix() === JOSWORLD_CORE_META_PREFIX;
            },
        );
        $this->assertContains(
            'jw_fixture_hidden',
            $prefixScopedCandidates,
            'MUTATION PROOF (fix side): a `_jw_`-prefix-scoped candidate set must see a '
            . 'non-public josworld model that public=>true filtering hides',
        );
        $this->assertNotContains(
            'jw_fixture_hidden',
            AdminUIService::POST_TYPES,
            'precondition: the fixture must be absent from POST_TYPES for this to be a real gap',
        );
    }

    /**
     * THE THIRD LIST (Cluster C review finding).
     *
     * CB-4 closed the drift between `AdminUIService::POST_TYPES` and the live
     * registry. T09 then introduced a third independent copy of the same four
     * slugs — `MultilingualService::TRANSLATABLE_POST_TYPES` — with nothing
     * tying it to either.
     *
     * Its failure mode is safe-direction, which is exactly what makes it
     * dangerous: `MultilingualService` filters through `isRegistered()`, so a
     * stale or misspelled entry is silently EXCLUDED rather than phantom-
     * included. A post type would quietly stop being declared translatable to
     * Polylang with no test failure anywhere — discoverable only by an editor
     * noticing a missing language switcher, long after the fact.
     *
     * Asserting equality with POST_TYPES (itself already pinned to the live
     * registry in both directions by the tests above) transitively pins this
     * third list to the code too, without inventing a fourth source of truth.
     */
    public function test_the_translatable_post_type_list_matches_the_admin_ui_list(): void
    {
        $translatable = MultilingualService::TRANSLATABLE_POST_TYPES;
        $adminUi = AdminUIService::POST_TYPES;

        sort($translatable);
        sort($adminUi);

        $this->assertSame(
            $adminUi,
            $translatable,
            'MultilingualService::TRANSLATABLE_POST_TYPES has drifted from '
            . 'AdminUIService::POST_TYPES. Because MultilingualService filters through '
            . 'isRegistered(), a drifted entry does NOT fatal — it silently stops that '
            . 'post type being declared translatable to Polylang, with no other test '
            . 'failing. Keep the two lists identical, or collapse them into one.',
        );
    }
}
