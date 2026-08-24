<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Ordering ground-truth for the YOOtheme bridge: a listener attached at
 * functions.php top level — which wp-settings.php runs BEFORE firing
 * after_setup_theme — observes all four `ntdst/model/registered` events
 * from josworld-core's after_setup_theme:5 closure. Proof re-fires the
 * REAL registered closure after evicting the cached service singletons
 * (a one-arg set() rebind), so register() genuinely runs again; the no-eviction sibling
 * test proves the eviction is load-bearing, not incidental.
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
     * Original instances evicted via a one-arg set() rebind, restored verbatim in
     * tearDown — re-warming would wire listeners that _restore_hooks()
     * then wipes (see BaselineBootTest's tearDown).
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
        // Anchor: the real boot already registered everything once.
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
        // Attaching now ≡ attaching from functions.php top level (included
        // before after_setup_theme fires). Eviction first — otherwise
        // ntdst_get() returns cached singletons and register() never
        // re-fires (proven by the no-eviction test below).
        foreach (self::CONTENT_SERVICE_CLASSES as $class) {
            $this->originalServiceInstances[$class] = ntdst_get($class);
            // One-arg rebind = eviction; contract pinned in
            // ContainerTest::test_rebinding_clears_the_resolved_cache.
            \ntdst_container()->set($class);
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
        // Same sequence WITHOUT eviction: cached singletons mean zero
        // events — the failure a genuinely-too-late listener produces,
        // proving the eviction above is load-bearing.
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
