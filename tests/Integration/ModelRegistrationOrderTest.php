<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Integration tier: GROUND-TRUTH SPIKE for Cluster B (T03).
 *
 * De-risks the bridge's chosen enumeration strategy BEFORE T04-T07 build on
 * it (plan.md "Ordering"): `NTDST_Data_Manager::register()` fires
 * `do_action('ntdst/model/registered', $name, $config)` on every
 * registration (Data.php:1555), and the bridge plans to attach ONE listener
 * for that action at THEME-SERVICE-CONSTRUCTION time — i.e. from top-level
 * code in `functions.php`, the same way `AssetHooks` is bound there today
 * (functions.php:113-118), not from inside another `after_setup_theme`
 * closure. This test proves that attachment point genuinely sees all four
 * josworld models, and that the ordering is not an accident this project can
 * silently lose.
 *
 * GROUND-TRUTHED REAL ORDER (verified against this project's own source,
 * not assumed):
 *   1. `wp_get_mu_plugins()` loop (wp-settings.php:498) requires every
 *      mu-plugin, INCLUDING `josworld-coreloader.php` -> `josworld-core.php`.
 *      That file's TOP LEVEL only defines constants and registers a
 *      `plugins_loaded` closure — it does NOT call `register()` yet.
 *   2. `do_action('plugins_loaded')` (wp-settings.php:622) fires. Inside it,
 *      josworld-core.php requires the four content-service files and
 *      registers a SECOND, nested closure on `after_setup_theme` at
 *      priority 5 (josworld-core.php:81-103) — THIS nested closure is what
 *      actually calls `ntdst_get(CaseService::class)` etc., and each of
 *      those constructors calls `NTDST_Data_Manager::register()`.
 *   3. The active theme's `functions.php` is `include`d
 *      (wp-settings.php:739) — BEFORE `after_setup_theme` fires
 *      (wp-settings.php:749). This is the theme's TOP-LEVEL execution point
 *      — where `AssetHooks` is bound today, and where the planned
 *      `YOOthemeSourcesService` will be constructed (theme-config.php:
 *      `services.core => []`, `auto_discover => false` — no framework
 *      auto-discovery, only this explicit top-level wiring, exactly the
 *      pattern this test's positive case exercises).
 *   4. `do_action('after_setup_theme')` (wp-settings.php:749) fires,
 *      running josworld-core's priority-5 closure, which resolves all four
 *      content services and therefore fires `ntdst/model/registered` four
 *      times.
 *
 * So a listener attached at step 3 (functions.php top level, BEFORE
 * `after_setup_theme` fires at all) is provably early enough to observe
 * every registration that happens in step 4 — that is the premise this test
 * proves, not assumes.
 *
 * PROOF TECHNIQUE — re-fire the REAL registered hook, mirroring
 * JosworldCoreBootTest's clauses 1+2 (this project's own established
 * technique for this exact class of problem): wp-phpunit's bootstrap has
 * already run the REAL boot sequence above ONCE, before any test method
 * runs — so josworld-core's real `after_setup_theme:5` closure is already
 * registered as a genuine WP_Hook callback. Attaching a bare listener to
 * `ntdst/model/registered` NOW (before re-firing) and then RE-FIRING that
 * same real, already-registered `after_setup_theme` action (do_action()
 * invokes the actual closure under test a second time — this is not a
 * reimplementation of the boot block) is functionally identical to
 * attaching at step 3: in both cases the listener exists before
 * `after_setup_theme`'s priority-5 callback runs. This is a faithful
 * ordering probe, not a "call do_action myself and see my own listener
 * fire" tautology, because the four `register()` calls are made by the
 * REAL josworld-core boot code, not by this test.
 *
 * DENIAL PATH / MUTATION PROOF (proves the refire technique itself can
 * actually go RED, not just green-by-construction — see
 * test_a_listener_attached_after_the_real_boot_already_ran_sees_nothing_without_eviction()):
 * the SAME listener-attach-then-refire sequence, with the eviction step
 * removed, is exactly what a bridge attached too late — or a broken
 * assumption about singleton caching — would look like: `ntdst_get()`
 * returns the ALREADY-CONSTRUCTED singleton instead of re-running
 * `registerDataModel()`, so a re-fired `after_setup_theme` produces zero
 * `ntdst/model/registered` events. This is not a vacuous "nothing happened
 * so of course it's empty" assertion — it fails specifically the same way a
 * genuinely-too-late listener would fail in production (sees none of the
 * four models), proving the eviction step in the test above is load-bearing,
 * not incidental.
 */
final class ModelRegistrationOrderTest extends WP_UnitTestCase
{
    private const EXPECTED_MODELS = ['case', 'team', 'vacature', 'tool'];

    private const CONTENT_SERVICE_CLASSES = [
        CaseService::class,
        TeamService::class,
        VacatureService::class,
        ToolService::class,
    ];

    /**
     * The ORIGINAL container instances this test evicts via forget() in the
     * refire test, so tearDown can put the very same object references back
     * — mirrors BaselineBootTest's rationale exactly: re-warming here (in a
     * child tearDown, which runs BEFORE WP_UnitTestCase::tear_down()'s
     * _restore_hooks()) would wire a fresh instance's listeners just before
     * they get wiped by the hook-table restore, leaving the container
     * holding a service wired to nothing for every later test file.
     *
     * @var array<class-string, object>
     */
    private array $originalServiceInstances = [];

    protected function tearDown(): void
    {
        foreach ($this->originalServiceInstances as $id => $instance) {
            \ntdst_container()->set($id, $instance);
        }
        $this->originalServiceInstances = [];

        parent::tearDown();
    }

    public function test_real_boot_already_registered_all_four_models_before_any_test_ran(): void
    {
        // Sanity-anchor (mirrors JosworldCoreBootTest's own anchor pattern):
        // confirms the premise under test actually already happened for
        // real during bootstrap, not merely that this test CAN make it
        // happen.
        $this->assertSame(
            1,
            did_action('after_setup_theme'),
            'the real after_setup_theme hook must already have fired once during bootstrap',
        );

        foreach (self::EXPECTED_MODELS as $slug) {
            $this->assertTrue(
                ntdst_data()->isRegistered($slug),
                "model '{$slug}' must already be registered by the real boot pass "
                . '(josworld-core.php:81-103 resolves all four content services '
                . 'unconditionally on after_setup_theme:5)',
            );
        }
    }

    public function test_a_listener_attached_before_after_setup_theme_sees_all_four_models_on_refire(): void
    {
        // Attach the listener NOW — the equivalent of attaching it from
        // functions.php's top-level code, which this project's own
        // wp-settings.php proves runs BEFORE after_setup_theme fires
        // (include at :739, do_action at :749).
        //
        // ntdst_get() caches singletons, and the real boot already
        // constructed all four content services once (with all their
        // registerDataModel() calls already fired) before this test method
        // ran — so simply RE-FIRING after_setup_theme proves nothing on its
        // own: PROVEN below at
        // test_a_listener_attached_after_the_real_boot_already_ran_sees_nothing_without_eviction().
        // Each service must first be EVICTED from the container (forget() —
        // the container's own documented eviction seam, same technique
        // BaselineBootTest uses for the identical reason), so that
        // ntdst_get() inside the real, re-fired after_setup_theme:5 closure
        // genuinely CONSTRUCTS a fresh instance and therefore genuinely
        // calls NTDST_Data_Manager::register() again — a real second
        // invocation of the code path under test, not a simulation of it.
        foreach (self::CONTENT_SERVICE_CLASSES as $class) {
            $this->originalServiceInstances[$class] = ntdst_get($class);
            \ntdst_container()->forget($class);
        }

        $seen = [];
        $listener = static function (string $name) use (&$seen): void {
            $seen[] = $name;
        };
        add_action('ntdst/model/registered', $listener, 10, 1);

        do_action('after_setup_theme');

        remove_action('ntdst/model/registered', $listener, 10);

        foreach (self::EXPECTED_MODELS as $slug) {
            $this->assertContains(
                $slug,
                $seen,
                "a listener attached before after_setup_theme fires must observe model '{$slug}' "
                . 'being (re-)registered when the real josworld-core boot closure runs — '
                . 'this is the ordering premise the whole bridge design depends on',
            );
        }
    }

    public function test_a_listener_attached_after_the_real_boot_already_ran_sees_nothing_without_eviction(): void
    {
        // MUTATION PROOF: run the EXACT SAME sequence as the passing test
        // above — attach listener, do_action('after_setup_theme') — but
        // WITHOUT the forget() eviction step. This proves the eviction step
        // is load-bearing: without it, ntdst_get() inside josworld-core's
        // real priority-5 closure returns the cached singletons from the
        // real boot that already ran before this test method started, so
        // registerDataModel() (and therefore NTDST_Data_Manager::register())
        // never runs again — the listener observes NONE of the four models,
        // exactly the failure mode a genuinely-too-late/broken listener
        // would produce. This is the RED this test suite would show if the
        // refire technique above were meaningless: run it once with the
        // eviction step removed and confirm it fails to observe anything.
        $seen = [];
        $listener = static function (string $name) use (&$seen): void {
            $seen[] = $name;
        };
        add_action('ntdst/model/registered', $listener, 10, 1);

        do_action('after_setup_theme');

        remove_action('ntdst/model/registered', $listener, 10);

        $this->assertSame(
            [],
            $seen,
            'MUTATION PROOF: without evicting the cached singletons first, re-firing '
            . 'after_setup_theme observes NONE of the four models — proving the eviction '
            . 'step in test_a_listener_attached_before_after_setup_theme_sees_all_four_models_on_refire() '
            . 'is what actually makes that test discriminating, not incidental scaffolding',
        );
    }
}
