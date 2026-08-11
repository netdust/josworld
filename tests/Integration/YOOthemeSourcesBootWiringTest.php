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
 * Proves YOOthemeSourcesService is WIRED into the real theme boot, not
 * merely implemented — the service once sat fully unit-tested yet never
 * constructed, and that failure is silent: collect() attached after
 * josworld-core's after_setup_theme:5 model registrations means an empty
 * registry, an empty picker, and no error anywhere. The only race-free
 * attachment point is functions.php top level; that is what this pins.
 *
 * Proof technique: the harness boots wp-phpunit's fixture theme, so the
 * josworld functions.php never runs in the bootstrap. The test switches to
 * the real theme, evicts the four content services (cached singletons
 * would never re-register — load-bearing, see the mutation proof),
 * require_once's the REAL unmodified functions.php, re-fires
 * after_setup_theme, and asserts on buildTypes()' emitted data.
 * require_once keeps it order-independent: the file define()s constants
 * and failOnWarning escalates a re-run, so only the first method executes
 * it. tearDown restores the evicted instances BY REFERENCE (re-warming
 * builds listeners that _restore_hooks() wipes — the defect
 * ZZBaselineHookLeakGuardTest guards), switches the theme back, and
 * flushes the STATIC registry on both sides.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class YOOthemeSourcesBootWiringTest extends WP_UnitTestCase
{
    /**
     * The four YOOtheme object types the bridge must emit — ucfirst() of
     * the model slug, collect()'s own derivation.
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
     * The original evicted instances, restored by reference in tearDown —
     * never re-warmed.
     *
     * @var array<class-string, object>
     */
    private array $originalServiceInstances = [];

    private ?string $originalStylesheet = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Load the class independently of the wiring so RED is behavioural
        // ("registry empty"), not structural ("class not found") — a
        // structural RED would go green on a mere require without the
        // listener ever being subscribed.
        require_once dirname(__DIR__, 2)
            . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';
        require_once dirname(__DIR__, 2)
            . '/app/content/themes/josworld/services/yootheme/YOOthemeSourcesService.php';

        // Static registry: flush BEFORE so this cannot pass on residue
        // from another file in the same process.
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
     * After the real boot the registry holds the four types AND
     * buildTypes() emits them. Both halves share ONE method deliberately:
     * functions.php executes once per process, so a second method would
     * observe a no-op require against a freshly flushed registry.
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

        // Assert on the real emitted payload, not registry internals —
        // what proves the picker would actually be populated.
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

        // Repeater sub-types must land in the SAME flat map — a `listOf`
        // reference to an unregistered type dangles in the builder schema.
        $this->assertArrayHasKey(
            'CaseImpactItem',
            $types,
            'the flat payload must also carry repeater sub-types — a `listOf` reference to '
            . 'an unregistered type is a dangling reference in the builder schema',
        );
    }

    /**
     * Mutation proof: the same re-fire WITHOUT eviction leaves the registry
     * empty — cached singletons never re-register — proving the eviction is
     * load-bearing and the contract test's RED is reachable.
     */
    public function test_without_fresh_model_registration_the_registry_stays_empty(): void
    {
        $this->switchToTheJosworldTheme();
        $this->requireTheRealThemeFunctionsFile();

        // NO eviction — deliberately: the singletons come back cached and
        // nothing re-registers.
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

    private function bootTheRealThemeWithModelsFreshlyRegistered(): void
    {
        $this->switchToTheJosworldTheme();

        // Evict BEFORE functions.php runs, so the listener it attaches is
        // in place before any re-registration fires — production's ordering.
        foreach (self::CONTENT_SERVICE_CLASSES as $class) {
            $this->originalServiceInstances[$class] = \ntdst_get($class);
            \ntdst_container()->forget($class);
        }

        // A replay artifact, not a defect: the bootstrap already passed
        // wp_loaded, so re-including the theme makes its title-tag
        // add_theme_support() emit _doing_it_wrong. Declaring it keeps
        // every OTHER incorrect-usage notice fatal.
        $this->setExpectedIncorrectUsage('add_theme_support( \'title-tag\' )');

        $this->requireTheRealThemeFunctionsFile();

        do_action('after_setup_theme');
    }

    /**
     * Make `josworld` genuinely active: functions.php builds its require
     * paths from get_stylesheet_directory(), which otherwise resolves to
     * wp-phpunit's fixture theme.
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
     * Execute the real, unmodified functions.php. require_once: the file
     * define()s constants, and failOnWarning escalates a second execution —
     * only the first test method actually runs it.
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
