<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use josworld\services\yootheme\YOOthemeSourcesService;
use WP_UnitTestCase;

/**
 * Integration tier: proves the T08 CONTRACT — `YOOthemeSourcesService` is
 * actually WIRED INTO THE THEME BOOT, not merely implemented.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * WHY THIS TEST EXISTS, AND WHY IT MUST BE AT THE INTEGRATION TIER.
 *
 * T07 (commit 7648bd4) implemented and unit-tested the service completely:
 * `collect()` vets, `buildTypes()` emits, `register()` attaches. Every one of
 * those unit tests was, and still is, GREEN — while ZERO josworld fields
 * appeared in YOOtheme's dynamic-content picker, because nothing ever
 * CONSTRUCTED the class. A fully-tested, fully-correct, never-instantiated
 * service.
 *
 * That is precisely the gap a per-task unit test is structurally blind to:
 * the unit suite proves the piece works IN ISOLATION and stays green whether
 * or not the piece is mounted. Only a test that exercises the REAL boot can
 * tell the difference, which is what makes this an `Integration test:`
 * contract rather than a unit one — the risk lives in WP hook lifecycle
 * (does the listener exist BEFORE `after_setup_theme` fires?), which Brain
 * Monkey cannot express at all.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * THE FAILURE MODE IS SILENT — which is the whole reason for the mutation
 * proof below.
 *
 * `collect()` is the `ntdst/model/registered` listener. If it is attached
 * even slightly too late — from the theme's `bootFeatures()` at
 * `after_setup_theme` priority 15, or from `bootCore()` at priority 5 (a
 * race against josworld-core's OWN priority-5 closure, decided by
 * hook-registration order) — then every `do_action('ntdst/model/registered')`
 * has ALREADY fired by the time the listener exists. The registry stays
 * empty, `buildTypes()` returns `[]`, YOOtheme registers no object types,
 * the picker shows nothing.
 *
 * There is NO error. No exception, no notice, no log line, no white page.
 * The site loads perfectly and the feature is simply absent. A regression
 * here would ship silently and stay shipped, which is exactly the class of
 * bug that must be bought a test rather than a code comment.
 *
 * GROUND-TRUTHED REAL ORDER (verified against this project's own source,
 * and re-proven by T03's `ModelRegistrationOrderTest`, whose technique this
 * file mirrors):
 *   1. mu-plugins load; `josworld-core.php` top level only registers a
 *      `plugins_loaded` closure.
 *   2. `plugins_loaded` fires -> josworld-core requires the four content
 *      services and registers a nested `after_setup_theme` PRIORITY 5
 *      closure (josworld-core.php:81-103).
 *   3. The active theme's `functions.php` is included
 *      (wp-settings.php:739) — the theme's TOP-LEVEL execution point.
 *   4. `after_setup_theme` fires (wp-settings.php:749), running
 *      josworld-core's priority-5 closure, which resolves all four content
 *      services; each constructor calls `registerDataModel()` synchronously
 *      (e.g. CaseService.php:110-119) -> `NTDST_Data_Manager::register()` ->
 *      `do_action('ntdst/model/registered')` (ntdst-core Data.php:1555).
 *
 * So the ONLY safe attachment point is step 3 — `functions.php` top level,
 * strictly before `after_setup_theme` fires at all, and therefore free of
 * any priority race. That is the point this test pins.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * PROOF TECHNIQUE — run the REAL, UNMODIFIED `functions.php`; do not
 * reimplement its wiring.
 *
 * The integration tier boots WordPress with `WP_DEFAULT_THEME` =
 * `twentytwentyfive` (tests/wp-tests-config.php:32), which is not installed,
 * so `get_stylesheet()` resolves to wp-phpunit's own fixture theme
 * `default` and the josworld theme's `functions.php` NEVER RUNS during the
 * bootstrap's real boot pass. Verified by probe: `get_stylesheet() ===
 * 'default'`, `JOSWORLD_VERSION` undefined, `AssetHooks` not declared. That
 * is a pre-existing, deliberate property of this harness (89 existing
 * integration tests boot under it) and is NOT something this test changes
 * globally.
 *
 * Instead, this test reconstructs step 3 faithfully and locally:
 *
 *   a. `register_theme_directory()` + `switch_theme('josworld')` so
 *      `get_stylesheet_directory()` genuinely resolves to the josworld
 *      theme. This matters: `functions.php` computes `JOSWORLD_DIR` from
 *      `get_stylesheet_directory()` and requires its helpers from there, so
 *      without the switch the requires resolve into wp-phpunit's fixture
 *      dir and fatal (verified by probe).
 *   b. Evict the four content services from the container via `forget()` —
 *      the container's own documented eviction seam, the same technique
 *      `ModelRegistrationOrderTest` and `BaselineBootTest` use for the
 *      identical reason. Without this, `ntdst_get()` inside the re-fired
 *      boot closure returns the singletons the real bootstrap already
 *      constructed, `registerDataModel()` never runs again, and NO
 *      `ntdst/model/registered` action fires. Its load-bearingness is
 *      proven, not assumed — see the mutation-proof test below.
 *   c. `require_once` the REAL, UNMODIFIED `functions.php`. This executes
 *      the ACTUAL production wiring line under test — not a copy of it, not
 *      a simulation. If the wiring is deleted from that file, this test
 *      goes RED. That is the entire point.
 *   d. Re-fire the REAL, already-registered `after_setup_theme` action.
 *      `do_action()` invokes josworld-core's genuine priority-5 closure a
 *      second time, so the four `register()` calls are made by REAL boot
 *      code, not by this test.
 *   e. Assert against REAL EMITTED DATA — `buildTypes()`'s actual output —
 *      rather than grepping the source for an `add_action` call. A source
 *      grep would pass against a wiring that is present but attached at the
 *      wrong priority; this cannot.
 *
 * `require_once` (not `require`) is deliberate and is what keeps this test
 * order-independent: `functions.php` calls `define()` on JOSWORLD_VERSION
 * and friends, so a second execution in the same process would emit
 * "constant already defined" notices, which this suite escalates to
 * failures (`failOnWarning="true"`). Both test methods below therefore go
 * through the one shared helper, and only the FIRST one to run actually
 * executes the file — which is also why the mutation proof asserts on
 * `flushRegistry()` state rather than on re-execution.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * STATE DISCIPLINE (`tearDown`). Three distinct kinds of leak are restored,
 * each for a reason this project has already been bitten by:
 *
 *   - The container: evicted services are put BACK as the very same object
 *     references, mirroring `ModelRegistrationOrderTest`. Re-warming with
 *     `ntdst_get()` instead would construct a fresh instance whose listeners
 *     are then wiped by `WP_UnitTestCase::tear_down()`'s `_restore_hooks()`
 *     — the exact silent cross-file defect `ZZBaselineHookLeakGuardTest`
 *     exists to catch.
 *   - The active theme: switched back, so the 89 other integration tests
 *     (and `ZZBaselineHookLeakGuardTest` in particular) observe the same
 *     boot state they were written against.
 *   - `YOOthemeSourcesService::$registry` is STATIC (see its docblock — it
 *     must be, because YOOtheme serialises the resolver `func` as a literal
 *     string, so the resolver has no `$this`). Static state leaks across
 *     test methods AND across test FILES in one PHPUnit process, so it is
 *     flushed via the class's own `flushRegistry()` test seam both before
 *     and after — otherwise this test could pass on residue from
 *     `SourcesServiceTest`, or poison a later file.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class YOOthemeSourcesBootWiringTest extends WP_UnitTestCase
{
    /**
     * The four josworld models, and therefore the four YOOtheme object types
     * the bridge must emit — `ucfirst()` of the model slug, the derivation
     * `collect()` performs (YOOthemeSourcesService.php:195).
     *
     * @var list<string>
     */
    private const EXPECTED_TYPE_NAMES = ['Case', 'Team', 'Vacature', 'Tool'];

    /**
     * @var list<class-string>
     */
    private const CONTENT_SERVICE_CLASSES = [
        CaseService::class,
        TeamService::class,
        VacatureService::class,
        ToolService::class,
    ];

    /**
     * The ORIGINAL container instances evicted via `forget()`, restored by
     * reference in tearDown — never re-warmed. See the class docblock.
     *
     * @var array<class-string, object>
     */
    private array $originalServiceInstances = [];

    private ?string $originalStylesheet = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Load the CLASS independently of the WIRING, so this test's RED is
        // BEHAVIOURAL ("the registry is empty") rather than structural
        // ("class not found"). The distinction is the whole contract: a
        // "class not found" RED would go green the moment functions.php
        // merely `require_once`s the file, even if `collect()` were never
        // subscribed to `ntdst/model/registered` — i.e. it would pass
        // against the exact silent bug this test exists to catch. Requiring
        // the symbol here forces the assertions below to be about observed
        // BOOT BEHAVIOUR and nothing else.
        //
        // The theme's services carry no autoloader (same as `AssetHooks`),
        // hence the explicit require. `SchemaMapper` comes with it because
        // `buildTypes()` calls it statically.
        require_once dirname(__DIR__, 2)
            . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';
        require_once dirname(__DIR__, 2)
            . '/app/content/themes/josworld/services/yootheme/YOOthemeSourcesService.php';

        // The registry is static: flush BEFORE so this test cannot pass on
        // residue left by another file in the same process.
        YOOthemeSourcesService::flushRegistry();
    }

    protected function tearDown(): void
    {
        YOOthemeSourcesService::flushRegistry();

        foreach ($this->originalServiceInstances as $id => $instance) {
            \ntdst_container()->set($id, $instance);
        }
        $this->originalServiceInstances = [];

        if ($this->originalStylesheet !== null) {
            switch_theme($this->originalStylesheet);
            $this->originalStylesheet = null;
        }

        parent::tearDown();
    }

    /**
     * THE CONTRACT: after the real theme boot, the service's registry is
     * genuinely populated with the josworld types, AND `buildTypes()` emits
     * real YOOtheme object-type configs for them.
     *
     * This is the assertion that goes RED when the wiring is missing from
     * `functions.php` — which is exactly the state the repo was in before
     * T08 (service implemented at T07, never instantiated, no fields in the
     * picker, no error anywhere).
     *
     * BOTH halves live in ONE test method deliberately. The real
     * `functions.php` can only be executed ONCE per PHP process (it
     * `define()`s constants, and this suite escalates the resulting
     * "already defined" warnings to failures), so a second method
     * re-invoking the boot helper would observe a no-op require and a
     * registry that `setUp()` had just flushed — failing for a test-harness
     * reason rather than a contract one. Keeping the registry assertion and
     * the emission assertion in the same method keeps both anchored to the
     * single genuine execution of the production wiring.
     */
    public function test_the_real_theme_boot_populates_and_emits_the_josworld_yootheme_types(): void
    {
        $this->bootTheRealThemeWithModelsFreshlyRegistered();

        foreach (self::EXPECTED_TYPE_NAMES as $typeName) {
            $this->assertNotNull(
                YOOthemeSourcesService::registryEntry($typeName),
                "the real theme boot must leave type '{$typeName}' in the vetted registry — "
                . 'a null entry means YOOthemeSourcesService::collect() was never subscribed to '
                . "'ntdst/model/registered' at functions.php top level, or was subscribed too "
                . 'late to observe the registrations josworld-core fires on after_setup_theme:5. '
                . 'That failure is SILENT in production: no error, just an empty '
                . 'dynamic-content picker',
            );
        }

        // Assert against REAL EMITTED DATA — the exact flat `name => config`
        // payload `register()` hands YOOtheme's `source.init` listener — not
        // merely against registry internals. This is what makes it a proof
        // that the picker would actually be populated.
        $types = (new YOOthemeSourcesService())->buildTypes();

        foreach (self::EXPECTED_TYPE_NAMES as $typeName) {
            $this->assertArrayHasKey(
                $typeName,
                $types,
                "buildTypes() must emit the '{$typeName}' object type after the real theme "
                . 'boot — this is the payload register() hands YOOtheme\'s source.init '
                . 'listener, so a missing key here is a type that never reaches the builder',
            );

            $this->assertNotEmpty(
                $types[$typeName]['fields'] ?? [],
                "the '{$typeName}' object type must carry at least one field — an emitted "
                . 'type with no fields registers a picker entry a user cannot select anything '
                . 'from',
            );
        }

        // The repeater SUB-types must be emitted into the SAME flat map:
        // a field typed `['listOf' => 'CaseImpactItem']` is a dangling
        // GraphQL reference unless `CaseImpactItem` is itself registered
        // through `objectType()`. Asserting one real sub-type proves the
        // wiring carries the complete payload, not just the parent types.
        $this->assertArrayHasKey(
            'CaseImpactItem',
            $types,
            'the flat payload must also carry repeater sub-types — a `listOf` reference to '
            . 'an unregistered type is a dangling reference in the builder schema',
        );
    }

    /**
     * MUTATION PROOF — proves the two tests above are DISCRIMINATING rather
     * than green-by-construction, mirroring
     * `ModelRegistrationOrderTest::test_a_listener_attached_after_the_real_boot_already_ran_sees_nothing_without_eviction()`.
     *
     * Run the SAME re-fire sequence WITHOUT the container eviction: the
     * cached singletons from the real bootstrap are returned unchanged,
     * `registerDataModel()` never runs again, so `ntdst/model/registered`
     * never fires and `collect()` — however correctly wired — is handed
     * nothing. The registry stays EMPTY.
     *
     * This is not a vacuous "nothing happened, so of course it is empty"
     * assertion. It fails in exactly the shape a genuinely-too-late listener
     * produces in production (an empty registry, and NO error), which is
     * what proves the eviction step above is load-bearing and that a RED
     * here is reachable.
     */
    public function test_without_fresh_model_registration_the_registry_stays_empty(): void
    {
        $this->switchToTheJosworldTheme();
        $this->requireTheRealThemeFunctionsFile();

        // NO eviction — deliberately. do_action() re-runs josworld-core's
        // real after_setup_theme:5 closure, but ntdst_get() hands back the
        // already-constructed singletons, so nothing re-registers.
        do_action('after_setup_theme');

        foreach (self::EXPECTED_TYPE_NAMES as $typeName) {
            $this->assertNull(
                YOOthemeSourcesService::registryEntry($typeName),
                'MUTATION PROOF: without evicting the cached singletons first, no model '
                . 're-registers, so collect() observes nothing and the registry stays empty — '
                . 'proving the eviction step in the contract tests above is what makes them '
                . 'discriminating, and that an empty registry (the silent production failure '
                . 'mode) is genuinely reachable by these assertions',
            );
        }
    }

    /**
     * Reconstruct boot step 3 + step 4 faithfully: real theme active, models
     * evicted so they genuinely re-register, REAL `functions.php` executed,
     * then the REAL `after_setup_theme` re-fired.
     */
    private function bootTheRealThemeWithModelsFreshlyRegistered(): void
    {
        $this->switchToTheJosworldTheme();

        // Evict BEFORE functions.php runs, so the listener that file
        // attaches is in place before any re-registration fires — the
        // ordering the production boot guarantees (theme include at
        // wp-settings.php:739, after_setup_theme at :749).
        foreach (self::CONTENT_SERVICE_CLASSES as $class) {
            $this->originalServiceInstances[$class] = \ntdst_get($class);
            \ntdst_container()->forget($class);
        }

        // EXPECTED, and a pure artifact of this tier — NOT a defect being
        // papered over. `theme-config.php` declares `support.title-tag`
        // (:28-29) and `NTDST_Theme` registers it via add_theme_support()
        // while functions.php runs. In PRODUCTION that is correct and
        // silent: WordPress includes the theme at wp-settings.php:739, long
        // before `wp_loaded`. Here, wp-phpunit's bootstrap has already run
        // the full boot (including `wp_loaded`) before any test method
        // starts, so replaying the theme include now necessarily lands after
        // it and WordPress rightly emits `_doing_it_wrong`. Declaring it is
        // the harness's supported way to say "this specific notice is a
        // known consequence of the replay"; any OTHER incorrect-usage notice
        // still fails the test.
        $this->setExpectedIncorrectUsage('add_theme_support( \'title-tag\' )');

        $this->requireTheRealThemeFunctionsFile();

        do_action('after_setup_theme');
    }

    /**
     * Make `josworld` the genuinely active theme so
     * `get_stylesheet_directory()` — which `functions.php` builds
     * `JOSWORLD_DIR` and all of its `require_once` paths from — resolves to
     * the real theme rather than wp-phpunit's `default` fixture.
     */
    private function switchToTheJosworldTheme(): void
    {
        if ($this->originalStylesheet === null) {
            $this->originalStylesheet = get_stylesheet();
        }

        register_theme_directory(dirname(__DIR__, 2) . '/app/content/themes');
        wp_clean_themes_cache();

        $theme = wp_get_theme('josworld');
        $this->assertTrue(
            $theme->exists(),
            'the josworld theme must be discoverable for this test to exercise its real boot',
        );

        switch_theme('josworld');

        $this->assertSame(
            'josworld',
            get_stylesheet(),
            'the josworld theme must be genuinely active, or functions.php would resolve its '
            . 'requires against the wrong theme directory',
        );
    }

    /**
     * Execute the REAL, UNMODIFIED theme `functions.php` — the file that
     * carries the wiring under test.
     *
     * `require_once`: the file `define()`s JOSWORLD_VERSION et al, so a
     * second execution in one process would emit "already defined" warnings
     * that this suite escalates to failures. Only the first test method to
     * run actually executes it; every method that follows observes the
     * listeners that first execution attached, which is exactly the
     * production lifetime of those listeners (attached once per request).
     */
    private function requireTheRealThemeFunctionsFile(): void
    {
        $functions = get_stylesheet_directory() . '/functions.php';

        $this->assertFileExists(
            $functions,
            'the real theme functions.php must exist for this test to exercise it unmodified',
        );

        require_once $functions;
    }
}
