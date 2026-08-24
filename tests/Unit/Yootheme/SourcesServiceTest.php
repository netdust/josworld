<?php

/**
 * Contract for YOOthemeSourcesService (collect / buildTypes / register):
 * inert without YOOtheme (no listener, no diagnostics); only `_jw_`
 * models are collected; the listener attaches to `source.init` at
 * priority -10 (YOOtheme krsort()s listeners, so a LOWER number runs
 * LATER — a sign error registers silently and the types are shadowed);
 * every emitted resolver reference is a LITERAL STRING naming an existing
 * function (a Closure or [Class, 'method'] is callable but not
 * serialisable — the white-page bug); collection accumulates via the
 * `ntdst/model/registered` action; the exclude_fields filter gates at
 * collection, on every emission path. buildTypes() is FLAT: parent types
 * and repeater sub-types share one `name => config` map, and every listOf
 * reference must resolve within that same payload — YOOtheme merges or
 * drops a dangling reference silently rather than erroring.
 *
 * Proof technique: the parent theme is gitignored and not autoloadable,
 * so this file declares YOOtheme\Event as a recording double in the real
 * namespace, and YOOtheme\app() only via a lazily-required stub inside
 * the positive tests — PHP cannot undeclare a function, so the denial
 * tests run first and assert the absence precondition explicitly.
 *
 * phpcs:disable PSR1.Files.SideEffects
 * phpcs:disable PSR1.Classes.ClassFileName
 * phpcs:disable Squiz.Classes.ClassFileName
 */

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use josworld\services\yootheme\YOOthemeSourcesService;
use NtdstTests\Unit\TestCase;
use YOOtheme\Event;

require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';
require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/YOOthemeSourcesService.php';

final class SourcesServiceTest extends TestCase
{
    /** The josworld boundary — JOSWORLD_CORE_META_PREFIX. */
    private const JW = '_jw_';

    /**
     * A real-shaped `case` model config, as `ntdst/model/registered` passes it.
     *
     * @return array<string, mixed>
     */
    private function caseConfig(): array
    {
        return [
            'label' => 'Cases',
            'meta_prefix' => self::JW,
            'fields' => [
                'client' => ['type' => 'text', 'label' => 'Klant'],
                'intro' => ['type' => 'textarea', 'label' => 'Intro'],
                'featured' => ['type' => 'bool', 'label' => 'Uitgelicht'],
                'related_tool' => ['type' => 'relation', 'label' => 'Tool'],
                'impact' => [
                    'type' => 'repeater',
                    'label' => 'Impact',
                    'sub_fields' => [
                        'figure' => ['type' => 'text', 'label' => 'Cijfer'],
                        'description' => ['type' => 'textarea', 'label' => 'Omschrijving'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function teamConfig(): array
    {
        return [
            'label' => 'Team',
            'meta_prefix' => self::JW,
            'fields' => [
                'role' => ['type' => 'text', 'label' => 'Functie'],
                'email' => ['type' => 'email', 'label' => 'E-mail'],
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Event::$recorded = [];
    }

    // ── The denial path: inert without YOOtheme ──

    public function test_the_yootheme_app_function_is_genuinely_absent_at_this_tier(): void
    {
        // Precondition guard: the denial tests below are only meaningful
        // while YOOtheme\app is absent — a future bootstrap declaring it
        // would silently flip them to the opposite branch.
        $this->assertFalse(
            function_exists('YOOtheme\app'),
            'FR-7 denial path is only meaningful while YOOtheme\app is absent — '
            . 'the parent theme is gitignored (.gitignore:138) and outside the '
            . 'composer autoloader, so this is the honest production-absent state',
        );
    }

    public function test_without_yootheme_no_source_init_listener_is_attached(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $service->register();

        $this->assertSame(
            [],
            Event::$recorded,
            'FR-7: with YOOtheme\app absent the service must attach NO source.init '
            . 'listener at all — the mandatory function_exists(\'YOOtheme\\app\') guard '
            . '(golden path yootheme-integration.md:66, :186) must return BEFORE any '
            . 'Event::on() call, or a site without the parent theme fatals on a missing class',
        );
    }

    public function test_without_yootheme_registering_raises_no_error_and_does_not_fatal(): void
    {
        // A guard that returned early but emitted a notice would still
        // break "no fatal, site functional" — diagnostics asserted
        // separately from the listener's absence.
        $raised = [];
        set_error_handler(static function (int $no, string $str) use (&$raised): bool {
            $raised[] = "{$no}: {$str}";

            return true;
        });

        try {
            $service = new YOOthemeSourcesService();
            $service->collect('case', $this->caseConfig());
            $service->register();
        } finally {
            restore_error_handler();
        }

        $this->assertSame(
            [],
            $raised,
            'FR-7 / flow 4: registering without YOOtheme must be silent — no notice, '
            . 'no warning, no deprecation. A site whose parent theme is absent must '
            . 'load cleanly, not merely avoid a fatal',
        );
    }

    // ── Only josworld's models (_jw_) are ever exposed ──

    public function test_a_model_whose_meta_prefix_is_not_jw_is_refused(): void
    {
        // Any plugin's ntdst model fires the same global action; without
        // the _jw_ boundary the bridge would expose that plugin's data.
        $service = new YOOthemeSourcesService();
        $service->collect('vendor_thing', [
            'label' => 'Vendor Things',
            'meta_prefix' => '_acme_',
            'fields' => [
                'secret' => ['type' => 'text', 'label' => 'Secret'],
            ],
        ]);

        $this->assertSame(
            [],
            $service->buildTypes(),
            'threat 4: a model registered with a non-_jw_ meta prefix must NOT be '
            . 'exposed as a builder source type — the bridge exposes josworld\'s '
            . 'models, not every model that happens to use the framework',
        );
    }

    public function test_a_model_with_no_meta_prefix_at_all_is_refused(): void
    {
        // Fail-closed on the missing key: the framework defaults
        // meta_prefix to '', a shape it really produces.
        $service = new YOOthemeSourcesService();
        $service->collect('prefixless', [
            'label' => 'Prefixless',
            'fields' => ['thing' => ['type' => 'text']],
        ]);

        $this->assertSame(
            [],
            $service->buildTypes(),
            'threat 4: a model config carrying no meta_prefix must be refused, not '
            . 'defaulted in — the boundary is an explicit _jw_ match, and framework '
            . 'register() defaults meta_prefix to an empty string (Data.php:1541)',
        );
    }

    public function test_a_jw_model_is_accepted_while_a_foreign_one_registered_alongside_it_is_not(): void
    {
        // Discriminating pair: accepted AND refused in one pass, so
        // blanket-accept or blanket-refuse cannot satisfy both halves.
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $service->collect('vendor_thing', [
            'label' => 'Vendor Things',
            'meta_prefix' => '_acme_',
            'fields' => ['secret' => ['type' => 'text']],
        ]);

        $types = $service->buildTypes();

        $this->assertArrayHasKey(
            'Case',
            $types,
            'threat 4: josworld\'s own _jw_ model must still be exposed — the boundary '
            . 'must discriminate, not blanket-refuse',
        );

        foreach (array_keys($types) as $name) {
            $this->assertStringNotContainsString(
                'Vendor',
                (string) $name,
                'threat 4: no type derived from the foreign _acme_ model may be emitted '
                . 'alongside the accepted josworld one',
            );
        }
    }

    // ── Registration shape: source.init at priority -10 ──

    public function test_with_yootheme_present_the_listener_is_attached_to_source_init_at_priority_minus_ten(): void
    {
        // Both halves pinned: the literal event name, and priority -10 —
        // krsort() means a LOWER number runs LATER, and getting the sign
        // wrong is silent (the types are shadowed by the base ones).
        $this->requireYOOthemeAppStub();

        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $service->register();

        $this->assertCount(
            1,
            Event::$recorded,
            'FR-1: with YOOtheme present exactly one source.init listener must be attached',
        );

        [$event, $listener, $priority] = Event::$recorded[0];

        $this->assertSame(
            'source.init',
            $event,
            'FR-1: the listener must be attached to the literal event name "source.init" — '
            . 'that is the only event the Source instance is emitted on '
            . '(builder-source/bootstrap.php:47)',
        );

        $this->assertSame(
            -10,
            $priority,
            'FR-1: priority must be -10. YOOtheme krsort()s listeners, so a LOWER number '
            . 'runs LATER — the source must run AFTER the base types. A positive priority '
            . 'here fails silently: the builder still loads, the types are just wrong',
        );

        // Not assertIsCallable — the double's signature already types the
        // parameter callable, making that assertion dead. The live property:
        // the listener must accept the single Source argument it is handed.
        $reflected = new \ReflectionFunction(\Closure::fromCallable($listener));

        $this->assertLessThanOrEqual(
            1,
            $reflected->getNumberOfRequiredParameters(),
            'FR-1: the source.init listener is invoked with exactly one argument, the '
            . 'Source (builder-source/bootstrap.php:47) — requiring more is an '
            . 'ArgumentCountError the moment the builder loads',
        );
    }

    public function test_with_yootheme_present_the_listener_registers_every_collected_type_on_the_source(): void
    {
        // Invoking the listener must actually register types — an empty
        // closure would pass the shape test above.
        $this->requireYOOthemeAppStub();

        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $service->collect('team', $this->teamConfig());
        $service->register();

        [, $listener] = Event::$recorded[0];

        $source = new RecordingSource();
        $listener($source);

        $this->assertArrayHasKey(
            'Case',
            $source->objectTypes,
            'FR-1: invoking the source.init listener must register the Case object type '
            . 'on the passed Source (SchemaBuilder::objectType(string $name, $config))',
        );
        $this->assertArrayHasKey(
            'Team',
            $source->objectTypes,
            'FR-1: every collected josworld model must be registered, not just the first',
        );
    }

    // ── Type naming: derived from the post type ──

    public function test_each_registered_type_name_derives_from_the_post_type(): void
    {
        // Pins the DERIVATION (case -> Case), not an exact key match —
        // buildTypes() is the flat payload and also carries sub-types.
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $service->collect('team', $this->teamConfig());

        $types = $service->buildTypes();

        $this->assertArrayHasKey(
            'Case',
            $types,
            'the registered type name derives from the post type: case -> Case',
        );
        $this->assertArrayHasKey(
            'Team',
            $types,
            'the registered type name derives from the post type: team -> Team',
        );

        // A real transform, not a pass-through: YOOtheme merges same-named
        // types silently, so `case` alongside `Case` is two types.
        $this->assertArrayNotHasKey(
            'case',
            $types,
            'the raw post-type slug must not survive as a type name — the derivation '
            . 'is case -> Case, not a pass-through',
        );
        $this->assertArrayNotHasKey(
            'team',
            $types,
            'the raw post-type slug must not survive as a type name',
        );
    }

    public function test_every_listOf_reference_resolves_to_a_type_that_is_actually_registered(): void
    {
        // The relationship invariant that makes the flat payload safe:
        // every listOf reference must have a definition in the same
        // payload — YOOtheme merges/omits a dangling one silently.
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $service->collect('team', $this->teamConfig());

        $types = $service->buildTypes();
        $references = 0;

        foreach ($types as $typeName => $config) {
            foreach ($config['fields'] ?? [] as $fieldName => $definition) {
                $referenced = $definition['type']['listOf'] ?? null;

                if ($referenced === null) {
                    continue;
                }

                ++$references;

                $this->assertArrayHasKey(
                    $referenced,
                    $types,
                    "{$typeName}.{$fieldName} references listOf '{$referenced}', but no "
                    . 'such type is registered in the payload handed to YOOtheme — a '
                    . 'DANGLING TYPE REFERENCE. This is Cluster B1 finding C-1 one layer '
                    . 'up: YOOtheme merges/omits silently rather than erroring, so the '
                    . 'builder renders rows with no fields and nothing anywhere reports it',
                );
            }
        }

        $this->assertGreaterThan(
            0,
            $references,
            'the fixture declares a repeater, so at least one listOf reference must have '
            . 'been emitted — this invariant must not pass by vacuity',
        );
    }

    public function test_the_repeater_sub_type_is_scoped_by_the_same_derived_type_name(): void
    {
        // If the service derived a different name than it passes to the
        // mapper, the listOf reference and the sub-type definition would be
        // scoped differently — a dangling type name in the builder.
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $types = $service->buildTypes();

        $this->assertArrayHasKey(
            'CaseImpactItem',
            $types,
            'the repeater sub-type must be scoped by the SAME derived parent name — '
            . 'SchemaMapper composes <TypeName><Field>Item, so `case`.`impact` must '
            . 'yield CaseImpactItem, proving this service passes its derived name '
            . 'through as the mapper\'s $typeName argument',
        );

        $this->assertSame(
            ['listOf' => 'CaseImpactItem'],
            $types['Case']['fields']['impact']['type'] ?? null,
            'the parent field\'s listOf must reference the SAME name the sub-type is '
            . 'registered under — a mismatch is a dangling type reference the builder '
            . 'cannot resolve',
        );
    }

    public function test_scalar_fields_are_mapped_and_unmappable_ones_omitted(): void
    {
        // Composition proof: the service delegates to SchemaMapper and
        // must not default omitted types back in.
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $fields = $service->buildTypes()['Case']['fields'] ?? [];

        $this->assertSame(
            'String',
            $fields['client']['type'] ?? null,
            'a text field must map to String via SchemaMapper',
        );
        $this->assertSame(
            'Boolean',
            $fields['featured']['type'] ?? null,
            'a boolean field must map to Boolean via SchemaMapper',
        );
        $this->assertArrayNotHasKey(
            'related_tool',
            $fields,
            'a relation field must be OMITTED entirely (T04\'s committed contract) — '
            . 'the service must not default unmapped types back in',
        );
    }

    // ── The white-page rule: literal string resolver references ──

    public function test_every_emitted_resolver_reference_is_a_literal_callable_function_name(): void
    {
        // Asserted on what the service EMITS, and deliberately NOT via
        // is_callable(): [self::class, 'resolve'] IS callable and is
        // exactly the non-serialisable form that white-pages the builder.
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $service->collect('team', $this->teamConfig());

        $checked = 0;

        foreach ($service->buildTypes() as $typeName => $config) {
            foreach ($config['fields'] ?? [] as $fieldName => $definition) {
                $where = "{$typeName}.{$fieldName}";

                $this->assertArrayHasKey(
                    'extensions',
                    $definition,
                    "{$where}: every emitted field needs a resolver — without an "
                    . 'extensions.call.func it renders empty in the builder',
                );

                $func = $definition['extensions']['call']['func'] ?? null;

                // Order is deliberate: the two discriminating checks run
                // FIRST — assertIsString subsumes them and would make them
                // dead assertions if it ran before.
                $this->assertFalse(
                    $func instanceof \Closure,
                    "{$where}: a Closure resolver is the obvious way to write this and is "
                    . 'the exact white-page bug the golden path names as the single most '
                    . 'painful failure of this integration — YOOtheme serialises the value '
                    . 'and a Closure cannot survive that round-trip',
                );
                $this->assertFalse(
                    is_array($func),
                    "{$where}: a [\$obj, 'method'] / [Class::class, 'method'] pair is "
                    . 'CALLABLE but NOT serialisable — being callable is not the contract, '
                    . 'being a literal string is. This is the assertion a naive '
                    . 'is_callable() check would wave through',
                );
                $this->assertIsString(
                    $func,
                    "{$where}: the resolver reference MUST be a string. YOOtheme declares "
                    . '@call\'s `func` argument as Type::string() (CallDirective.php:17-27) '
                    . 'and serialises it into the builder config — anything else yields a '
                    . 'WHITE PAGE in the Customizer, not an exception',
                );
                $this->assertTrue(
                    function_exists($func),
                    "{$where}: the emitted resolver name '{$func}' must name a function "
                    . 'that actually EXISTS — a fully-qualified string that resolves to '
                    . 'nothing (the __NAMESPACE__ composition trap) fails at builder '
                    . 'render time, far from this code',
                );

                ++$checked;
            }
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'this test is only meaningful if the service emitted fields to check — '
            . 'a build that emits nothing must not pass by vacuity',
        );
    }

    public function test_repeater_sub_type_fields_also_carry_literal_string_resolvers(): void
    {
        // The sub-type is a separate emission path and can independently
        // regress to a closure.
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $subFields = $service->buildTypes()['CaseImpactItem']['fields'] ?? [];

        $this->assertNotSame(
            [],
            $subFields,
            'the repeater sub-type must expose its sub_fields',
        );

        foreach ($subFields as $fieldName => $definition) {
            $func = $definition['extensions']['call']['func'] ?? null;

            $this->assertIsString(
                $func,
                "CaseImpactItem.{$fieldName}: repeater ROW fields need literal string "
                . 'resolvers for the same white-page reason as parent fields',
            );
            $this->assertTrue(
                function_exists($func),
                "CaseImpactItem.{$fieldName}: the emitted resolver '{$func}' must exist",
            );
        }
    }

    // ── Model collection via the action, including late registrations ──

    public function test_a_model_registered_after_the_listener_is_attached_is_still_collected(): void
    {
        // Models arrive over time via the action, so collection must be an
        // accumulating registry — not a one-shot enumeration at
        // construction, not reflection over the manager's protected models.
        $service = new YOOthemeSourcesService();

        $this->assertSame(
            [],
            $service->buildTypes(),
            'a freshly constructed service has collected nothing — it must not '
            . 'enumerate models at construction time',
        );

        // Pins accumulation, including the negative half: Team absent
        // before it is collected.
        $service->collect('case', $this->caseConfig());

        $afterFirst = $service->buildTypes();
        $this->assertArrayHasKey(
            'Case',
            $afterFirst,
            'the first collected model must be present immediately',
        );
        $this->assertArrayNotHasKey(
            'Team',
            $afterFirst,
            'a model that has NOT been collected must be absent — the service reports '
            . 'what the action actually gave it, never a speculative or hardcoded set',
        );

        // The late registration.
        $service->collect('team', $this->teamConfig());

        $afterSecond = $service->buildTypes();
        $this->assertArrayHasKey(
            'Team',
            $afterSecond,
            'a model registered LATER must still be collected — the service accumulates '
            . 'what the action gives it rather than snapshotting once',
        );
        $this->assertArrayHasKey(
            'Case',
            $afterSecond,
            'collecting a second model must not evict the first — the registry '
            . 'accumulates rather than replaces',
        );
    }

    public function test_a_model_carrying_no_fields_registers_no_empty_type(): void
    {
        // An empty object type is worse than none — a picker entry that
        // binds to nothing.
        $service = new YOOthemeSourcesService();
        $service->collect('fieldless', [
            'label' => 'Fieldless',
            'meta_prefix' => self::JW,
        ]);

        $this->assertSame(
            [],
            $service->buildTypes(),
            'a josworld model with no mappable fields must yield NO object type — an '
            . 'empty type is a builder entry that binds to nothing',
        );
    }

    // ── The ntdst/yootheme/exclude_fields filter ──

    public function test_the_exclude_fields_filter_defaults_to_excluding_nothing(): void
    {
        // The default is half the contract: unset, nothing is excluded.
        // Brain Monkey's default apply_filters stub returns the first
        // argument unchanged — the real no-filter WordPress behaviour.
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $fields = $service->buildTypes()['Case']['fields'] ?? [];

        $this->assertArrayHasKey(
            'client',
            $fields,
            'threat 2: with no filter attached, nothing is excluded — FR-12 exposes '
            . 'everything the schema declares today',
        );
        $this->assertArrayHasKey(
            'impact',
            $fields,
            'threat 2: the default must not drop repeaters either',
        );
    }

    public function test_the_exclude_fields_filter_actually_gates_a_field(): void
    {
        // The filter must genuinely REMOVE a field, at collection (the
        // mapper stays pure) — else a documented mitigation becomes a
        // comment. The model name is the second arg, for per-CPT scoping.
        \Brain\Monkey\Filters\expectApplied('ntdst/yootheme/exclude_fields')
            ->atLeast()
            ->once()
            ->andReturn(['client']);

        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $fields = $service->buildTypes()['Case']['fields'] ?? [];

        $this->assertArrayNotHasKey(
            'client',
            $fields,
            'threat 2: a field named by the ntdst/yootheme/exclude_fields filter must be '
            . 'ABSENT from the emitted type — the mitigation the security review flagged '
            . 'as a release blocker must actually gate, not merely exist',
        );
        $this->assertArrayHasKey(
            'intro',
            $fields,
            'threat 2: excluding one field must not drop the others',
        );
    }

    public function test_an_excluded_field_is_excluded_from_the_repeater_sub_type_too(): void
    {
        // Repeater rows are a second, independent emission path — an
        // exclusion honoured only at the parent level would leave the
        // excluded data rendering inside every grid row.
        \Brain\Monkey\Filters\expectApplied('ntdst/yootheme/exclude_fields')
            ->atLeast()
            ->once()
            ->andReturn(['figure']);

        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());

        $subFields = $service->buildTypes()['CaseImpactItem']['fields'] ?? [];

        $this->assertArrayNotHasKey(
            'figure',
            $subFields,
            'threat 2: an excluded sub-field must not render inside repeater rows — '
            . 'the exclusion applies to every emission path, not only the parent type',
        );
        $this->assertArrayHasKey(
            'description',
            $subFields,
            'threat 2: excluding one sub-field must not drop the others',
        );
    }

    /**
     * Declare `YOOtheme\app()` for the duration of the process. PHP cannot
     * undeclare a function, so this is called ONLY from the positive tests;
     * the denial tests are declared above and guarded by the explicit
     * absence precondition, not ordering luck.
     */
    private function requireYOOthemeAppStub(): void
    {
        require_once __DIR__ . '/../../Support/yootheme-app-stub.php';

        $this->assertTrue(
            function_exists('YOOtheme\app'),
            'the positive FR-1 path requires YOOtheme\app to be present',
        );
    }
}

/**
 * Minimal recording stand-in for YOOtheme's Source/SchemaBuilder,
 * mirroring the real objectType() signature. Deliberately not a Mockery
 * mock: the contract is recorded STATE, not interactions.
 */
final class RecordingSource
{
    /** @var array<string, array<string, mixed>> */
    public array $objectTypes = [];

    /** @var array<int, array<string, mixed>> */
    public array $queryTypes = [];

    /**
     * @param  array<string, mixed>|callable  $config
     */
    public function objectType(string $name, $config = []): void
    {
        $this->objectTypes[$name] = is_array($config) ? $config : [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function queryType(array $config = []): void
    {
        $this->queryTypes[] = $config;
    }
}

namespace YOOtheme;

/**
 * Test-owned recording double for YOOtheme's `Event` façade, in the real
 * namespace with the real on() signature — the parent theme is gitignored
 * and not autoloadable, so this collides with nothing and the service
 * calls it exactly as in production. It does NOT model the dispatcher's
 * krsort() ordering; the priority is asserted as a value only.
 */
abstract class Event
{
    /** @var array<int, array{0: string, 1: callable, 2: int}> */
    public static array $recorded = [];

    public static function on(string $event, callable $listener, int $priority = 0): void
    {
        self::$recorded[] = [$event, $listener, $priority];
    }
}
