<?php

/**
 * T07 CONTRACT TEST — authored by the independent test-author BEFORE any
 * implementation exists, and IMMUTABLE to the implementer: green it without
 * weakening it. A dispute is an escalation, not an edit.
 *
 * WHAT THIS PINS (derived from tasks.md T07's acceptance criteria, FR-1/FR-7,
 * threat 4 and threat 2, and acceptance flow 4 — NOT from any implementation,
 * which did not exist when this was written):
 *
 *   1. FR-7 / FLOW 4 — INERT WITHOUT YOOtheme. With `YOOtheme\app` absent,
 *      `register()` attaches NO `source.init` listener, returns normally, and
 *      raises no error/warning/notice. This is the DENIAL PATH and it is the
 *      reason this task is Tier A.
 *   2. THREAT 4 — ONLY josworld's MODELS. A model whose `meta_prefix` is not
 *      `_jw_` is REFUSED at collection; a `_jw_` one is accepted. A future
 *      third-party ntdst model must not become builder-bindable.
 *   3. FR-1 — REGISTRATION SHAPE. The listener is attached to the literal
 *      event name `source.init` at priority `-10` (YOOtheme `krsort()`s
 *      listeners, so a LOWER number runs LATER — the source must run AFTER
 *      the base types; ground-truthed at
 *      `yootheme/packages/utils/src/EventDispatcher.php::addListener()`).
 *   4. TYPE NAMING. The registered type name derives from the post type:
 *      `case` -> `Case`, `team` -> `Team`, and must agree with the
 *      `$typeName` SchemaMapper already scopes its `<Type><Field>Item`
 *      sub-types by (T04/T06's committed third parameter).
 *   5. THE WHITE-PAGE RULE — RESOLVER REFERENCES ARE LITERAL STRINGS. Every
 *      emitted `extensions.call.func` is a `string` naming an EXISTING
 *      function — never a Closure, never a `[$obj, 'method']` array, never a
 *      bare `__NAMESPACE__`-composed value that fails to resolve.
 *   6. MODEL COLLECTION VIA THE ACTION. A model registered AFTER the listener
 *      is attached is still collected (the ordering premise T03 proved).
 *   7. THREAT 2 — `ntdst/yootheme/exclude_fields`. Collection is the
 *      chokepoint: the filter defaults to excluding nothing, and when it
 *      returns a field name that field is absent from the emitted type.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * THE TRAP THIS FILE EXISTS TO MAKE UNSHIPPABLE — assertion 5.
 *
 * YOOtheme resolves a field's resolver through the `@call` directive, whose
 * `func` argument is declared `Type::string()` — a GraphQL String
 * (`yootheme/packages/graphql/src/Directive/CallDirective.php:17-27`). The
 * directive args are built by `ContainerPlugin::callDirective()`
 * (`.../Plugin/ContainerPlugin.php:120-133`) and travel into the builder's
 * serialised schema. A Closure or a `[$this, 'method']` pair cannot survive
 * that String round-trip, and the failure mode is not an exception — it is a
 * WHITE PAGE in the Customizer, i.e. the builder simply stops rendering, with
 * the theme's own golden path naming it the single most painful failure in
 * this integration (`ntdst-patterns/golden-paths/yootheme-integration.md:9`,
 * :110, :185). Writing a closure is the OBVIOUS way to write this code, which
 * is precisely why the contract pins the string form behaviourally rather
 * than trusting a code comment.
 *
 * `test_every_emitted_resolver_reference_is_a_literal_callable_function_name`
 * asserts this on what the service actually EMITS, not by reading its source:
 * `is_string()`, NOT a Closure, NOT an array, and `function_exists()` true —
 * so an implementation that emits `[self::class, 'resolve']` (a genuinely
 * callable value that PHP is perfectly happy with, and that a naive
 * `is_callable()` assertion would wave through) still fails here. That
 * distinction — callable-but-not-serialisable — is the whole bug.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * DESIGN DECISIONS THIS TEST PINS, AND WHY — recorded here because the task
 * brief explicitly delegated the shape to the test-author.
 *
 * (a) THE SERVICE IS SPLIT INTO COLLECT / BUILD / REGISTER, and the contract
 *     is written against all three:
 *
 *         public function collect(string $name, array $config): void
 *         public function buildTypes(): array
 *         public function register(): void
 *
 *     `collect()` is the `ntdst/model/registered` listener (threat 4 and
 *     threat 2 are decided here — collection is the chokepoint, and the
 *     plan's own note that "the mapper is pure and must not call
 *     apply_filters" is what forces the filter to live here rather than in
 *     SchemaMapper). `buildTypes()` is the PURE emission of
 *     `typeName => objectType config` — pure so the white-page rule
 *     (assertion 5), the type naming (4) and the field mapping can be
 *     asserted against real emitted data instead of a source grep.
 *     `register()` is the only YOOtheme-coupled member: the `init` callback
 *     carrying the `function_exists('YOOtheme\app')` guard and the
 *     `Event::on()` call.
 *
 *     This is not decomposition for its own sake. A single monolithic method
 *     that both guards and registers can only be tested by observing
 *     `Event::on`, and `Event` is a vendor class in a GITIGNORED theme
 *     (`.gitignore:138` ignores `app/content/themes/*`; only `josworld/` is
 *     re-included) — so a test that could only run when the licensed parent
 *     theme happens to be present is a test that does not run in CI. The
 *     split keeps the high-consequence contract (what gets emitted, and in
 *     what form) in code that is testable with no vendor dependency at all.
 *
 * (b) HOW `Event::on` IS ASSERTED HONESTLY — a test-owned RECORDING DOUBLE,
 *     not a fake that proves nothing. Verified at authoring time: neither
 *     `YOOtheme\Event` nor `YOOtheme\app()` is autoloadable at this tier
 *     (`php -r 'class_exists("YOOtheme\\Event")'` -> false), because the
 *     parent theme is gitignored and outside the composer autoloader. So
 *     this file DECLARES both symbols itself, at the bottom, in the real
 *     `YOOtheme` namespace:
 *
 *       - `YOOtheme\Event::on()` records `[$event, $listener, $priority]`
 *         into a public static array. This is the REAL API surface the
 *         production code calls — same class name, same method name, same
 *         three-parameter signature as
 *         `yootheme/packages/utils/src/Event.php`. The service under test is
 *         not modified, injected, or aware of the double; it calls
 *         `Event::on(...)` exactly as it will in production, and the double
 *         records what it was called with. That is a genuine seam
 *         observation, not a simulation.
 *       - `YOOtheme\app()` is declared ONLY inside the positive test, via a
 *         lazily-required global-namespace file — see (c). Its ABSENCE is
 *         the FR-7 denial path and needs no faking at all: at this tier the
 *         function genuinely does not exist, which is exactly the production
 *         condition flow 4 describes.
 *
 *     What this deliberately does NOT claim: it does not prove YOOtheme's
 *     dispatcher then does the right thing with priority -10. That is
 *     vendor behaviour, ground-truthed by reading `EventDispatcher::
 *     addListener()`'s `krsort()` and asserted here only as "the service
 *     passes -10". The remaining risk — that the listener runs at the wrong
 *     moment against the REAL dispatcher — is named in the deferral line and
 *     belongs to T08's integration gate and the acceptance drive (T11).
 *
 * (c) WHY `YOOtheme\app()` IS DECLARED IN ITS OWN LAZILY-REQUIRED FILE. Same
 *     constraint T05 hit and documented at
 *     `services/yootheme/ntdst-cached-meta-accessor.php`: a function whose
 *     defining file is loaded at test-DISCOVERY time cannot be controlled
 *     per-test. Here the need is sharper still — FR-7's whole contract is
 *     that the function is ABSENT, and a function declared at the top of
 *     this file would exist for every test in the class, silently deleting
 *     the denial path. Declaring it from inside ONE test, and ordering that
 *     test last (`zz_` prefix), keeps "absent" the default state that every
 *     other test observes. PHP cannot undeclare a function, so ordering is
 *     the only honest mechanism; the guard test asserts the precondition
 *     explicitly rather than assuming it.
 *
 * (d) THE FIXTURE SCHEMAS ARE REAL-SHAPED, taken from the four josworld
 *     content services' `fields` arrays (scalar + repeater + a relation that
 *     SchemaMapper omits), so the emitted types are asserted against the
 *     shape the bridge will really see, not a toy.
 *
 * A test file by nature declares symbols and executes side effects
 * (requires, the YOOtheme namespace block).
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
    /** The josworld boundary — JOSWORLD_CORE_META_PREFIX, josworld-core.php:32. */
    private const JW = '_jw_';

    /**
     * A real-shaped `case` model config, as `ntdst/model/registered` passes
     * it (Data.php:1555 fires with `$name, $config`; the config carries
     * `fields` and `meta_prefix` — see TeamService.php:217-218).
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
                'featured' => ['type' => 'boolean', 'label' => 'Uitgelicht'],
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

    // ─────────────────────────────────────────────────────────────────────
    // 1. FR-7 / FLOW 4 — THE DENIAL PATH: inert without YOOtheme.
    // ─────────────────────────────────────────────────────────────────────

    public function test_the_yootheme_app_function_is_genuinely_absent_at_this_tier(): void
    {
        // PRECONDITION GUARD for the two FR-7 tests below. Their whole
        // meaning depends on `YOOtheme\app` not existing; if some future
        // bootstrap declares it, those tests would silently start asserting
        // the OPPOSITE branch while still passing. This makes that
        // impossible to miss. (See design note (c): the positive test
        // declares it last, on purpose.)
        $this->assertFalse(
            function_exists('YOOtheme\app'),
            'FR-7 denial path is only meaningful while YOOtheme\app is absent — '
            . 'the parent theme is gitignored (.gitignore:138) and outside the '
            . 'composer autoloader, so this is the honest production-absent state',
        );
    }

    public function test_without_yootheme_no_source_init_listener_is_attached(): void
    {
        // FR-7 / flow 4 ("YOOtheme absent … bridge inert"): THE DENIAL PATH.
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
        // Flow 4's expected outcome is "No fatal, site functional". A guard
        // that returned early but emitted a notice/deprecation on the way
        // would still break flow 4's promise, so the absence of DIAGNOSTICS
        // is asserted separately from the absence of the listener above.
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

    // ─────────────────────────────────────────────────────────────────────
    // 2. THREAT 4 — only josworld's models (`_jw_`) are ever exposed.
    // ─────────────────────────────────────────────────────────────────────

    public function test_a_model_whose_meta_prefix_is_not_jw_is_refused(): void
    {
        // THREAT 4's denial path. A third-party plugin registering its own
        // ntdst model fires the SAME `ntdst/model/registered` action this
        // service listens to (Data.php:1555 is global, not josworld's), so
        // without this boundary the bridge would expose that plugin's data
        // through josworld's builder. `_jw_` is the same boundary
        // PostTypeRegistryConsistencyTest already uses to define "josworld's"
        // (plan.md:108-110).
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
        // Fail-CLOSED on the missing key, not fail-open. `meta_prefix` is
        // optional in NTDST_Data_Manager::register() (Data.php:1541 defaults
        // it to ''), so an absent prefix is a shape the framework really
        // produces — and '' is not '_jw_'.
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
        // The discriminating pair: accepted AND refused in one collection
        // pass, so an implementation that refuses everything (or accepts
        // everything) cannot satisfy both halves.
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

    // ─────────────────────────────────────────────────────────────────────
    // 3. FR-1 — registration shape: source.init at priority -10.
    // ─────────────────────────────────────────────────────────────────────

    public function test_with_yootheme_present_the_listener_is_attached_to_source_init_at_priority_minus_ten(): void
    {
        // FR-1. Both halves matter and both are pinned:
        //   - the literal event name `source.init` (the only event
        //     `builder-source/bootstrap.php:47` emits the Source on);
        //   - the priority `-10`. YOOtheme's dispatcher krsort()s listeners
        //     (EventDispatcher::addListener() -> `krsort($this->listeners
        //     [$event])`), so a HIGHER number runs FIRST and a source must
        //     therefore use a LOWER one to run AFTER the base types
        //     registered by builder-wordpress-source. Every first-party
        //     add-on source in the theme uses exactly -10
        //     (builder-wordpress-toolset/bootstrap.php:10,
        //     builder-wordpress-acf/bootstrap.php:10,
        //     builder-wordpress-woocommerce/bootstrap.php:15). Getting the
        //     SIGN wrong here is silent: the source registers, the builder
        //     loads, and the types are shadowed by the base ones.
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

        // Not `assertIsCallable` — the double's `on()` signature already
        // types the parameter `callable`, so PHP would have fataled before
        // reaching any such assertion, making it dead (PHPStan flags it at
        // level 6). The live property is that the listener ACCEPTS the
        // Source it will be handed: `Event::emit('source.init', $source)`
        // (builder-source/bootstrap.php:47) passes exactly one argument, and
        // a listener declaring zero or two required parameters is an
        // ArgumentCountError at builder-load time.
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
        // The listener is not merely attached — invoking it with a Source
        // must actually register the object types. Without this, an
        // implementation that attaches an empty closure passes the FR-1
        // shape test above while registering nothing at all.
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

    // ─────────────────────────────────────────────────────────────────────
    // 4. TYPE NAMING — derived from the post type, agreeing with SchemaMapper.
    // ─────────────────────────────────────────────────────────────────────

    public function test_each_registered_type_name_derives_from_the_post_type(): void
    {
        $service = new YOOthemeSourcesService();
        $service->collect('case', $this->caseConfig());
        $service->collect('team', $this->teamConfig());

        $this->assertSame(
            ['Case', 'Team'],
            array_keys($service->buildTypes()),
            'the registered type name derives from the post type: case -> Case, '
            . 'team -> Team',
        );
    }

    public function test_the_repeater_sub_type_is_scoped_by_the_same_derived_type_name(): void
    {
        // The naming is not free: SchemaMapper composes repeater sub-types
        // as `<TypeName><Field>Item` and takes the parent type name as its
        // THIRD parameter (SchemaMapper::toSubObjectTypes(schema, prefix,
        // typeName)). If this service derived a different name than it
        // passes to the mapper, the emitted `listOf` reference and the
        // emitted sub-type DEFINITION would be scoped differently — a
        // dangling type name in the builder. Asserting the sub-type name
        // proves the two agree, which asserting the parent name alone
        // cannot.
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
        // Composition proof: the service must delegate to SchemaMapper
        // rather than re-implement mapping. `relation` is deliberately
        // present in the fixture and deliberately absent here — T04 pinned
        // omit-don't-default, and this asserts the service did not undo it
        // by defaulting unmapped types back in on its way to the type.
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

    // ─────────────────────────────────────────────────────────────────────
    // 5. THE WHITE-PAGE RULE — literal string resolver references.
    // ─────────────────────────────────────────────────────────────────────

    public function test_every_emitted_resolver_reference_is_a_literal_callable_function_name(): void
    {
        // THE highest-consequence assertion in this file. See the file
        // docblock: YOOtheme's @call directive declares `func` as
        // Type::string() (CallDirective.php:17-27) and the value travels
        // into the builder's serialised schema — a Closure or a
        // [$obj, 'method'] pair does not survive that and the Customizer
        // renders a WHITE PAGE.
        //
        // Asserted on what the service EMITS, not by grepping its source,
        // and deliberately NOT via is_callable(): `[self::class, 'resolve']`
        // IS callable and would sail through such a check while being
        // exactly the non-serialisable form that crashes the builder. The
        // four assertions below are the ones that discriminate.
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

                // ORDER IS DELIBERATE. The two DISCRIMINATING checks run
                // FIRST, on the un-narrowed value, so each names the
                // specific defect an implementer would actually have
                // written. `assertIsString` afterwards subsumes both
                // logically — running it first would make these two dead
                // assertions (PHPStan says so at level 6) and collapse
                // three distinct diagnostics into one generic
                // "not a string".
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
        // The sub-type is a SEPARATE objectType config, built on a
        // different code path than the parent's fields — so it can
        // independently regress to a closure. Rows of a repeater grid are
        // exactly where a white page would surface for an editor.
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

    // ─────────────────────────────────────────────────────────────────────
    // 6. MODEL COLLECTION — via the action, including late registrations.
    // ─────────────────────────────────────────────────────────────────────

    public function test_a_model_registered_after_the_listener_is_attached_is_still_collected(): void
    {
        // The premise T03 proved at the integration tier, pinned here as a
        // property of THIS service: models arrive over time via
        // `ntdst/model/registered` (Data.php:1555), so the collection must
        // be an accumulating registry the action feeds — NOT a one-shot
        // enumeration taken at construction, and NOT reflection over
        // `NTDST_Data_Manager::$models` (protected static, Data.php:1511 —
        // the reference implementation's approach, ground-truthed FALSE for
        // josworld at plan.md:45-57).
        $service = new YOOthemeSourcesService();

        $this->assertSame(
            [],
            $service->buildTypes(),
            'a freshly constructed service has collected nothing — it must not '
            . 'enumerate models at construction time',
        );

        $service->collect('case', $this->caseConfig());
        $this->assertSame(['Case'], array_keys($service->buildTypes()));

        // The LATE registration — arriving after the first model was
        // already collected and after any type map might have been built.
        $service->collect('team', $this->teamConfig());

        $this->assertSame(
            ['Case', 'Team'],
            array_keys($service->buildTypes()),
            'a model registered LATER must still be collected — the service accumulates '
            . 'what the action gives it rather than snapshotting once',
        );
    }

    public function test_a_model_carrying_no_fields_registers_no_empty_type(): void
    {
        // Edge: `fields` is optional in register() (Data.php checks
        // `!empty($config['fields'])` before the metabox branch). An empty
        // object type is worse than no type — it appears in the builder's
        // picker and binds to nothing.
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

    // ─────────────────────────────────────────────────────────────────────
    // 7. THREAT 2 — the ntdst/yootheme/exclude_fields filter.
    // ─────────────────────────────────────────────────────────────────────

    public function test_the_exclude_fields_filter_defaults_to_excluding_nothing(): void
    {
        // Threat 2's mitigation as plan.md:98-102 states it: "an
        // `ntdst/yootheme/exclude_fields` filter (default: empty, since
        // FR-12 exposes everything today)". The DEFAULT is half the
        // contract — a filter that silently dropped fields when unset would
        // break FR-12. Brain Monkey's default `apply_filters` stub returns
        // the first argument unchanged, i.e. the real WordPress behaviour
        // with no filter attached.
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
        // The other half: the filter must genuinely REMOVE a field, and be
        // applied at COLLECTION (the chokepoint) — not in SchemaMapper,
        // which the plan requires to stay pure and free of apply_filters.
        // Without this assertion the filter could be declared and ignored,
        // which is how a documented mitigation becomes a comment.
        //
        // The filter is passed the model NAME as its second argument so an
        // exclusion can be scoped to one CPT rather than applying globally.
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
        // A sub-field named by the filter must also be gated. Repeater rows
        // are a second, independent emission path (see assertion 5's
        // sub-type test) — an exclusion honoured only at the parent level
        // would leave the excluded data rendering inside every grid row,
        // which is precisely the leak threat 2 describes.
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

    // ─────────────────────────────────────────────────────────────────────
    // Support — see design note (c) for why the app() stub is loaded LAST.
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Declare `YOOtheme\app()` for the duration of the process.
     *
     * PHP cannot undeclare a function, so this is deliberately called ONLY
     * from the two FR-1 positive tests, and the FR-7 denial tests are
     * protected by test_the_yootheme_app_function_is_genuinely_absent_at_
     * this_tier() rather than by ordering luck: PHPUnit runs methods in
     * declaration order, and every FR-7 test is declared above these.
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
 * A minimal stand-in for YOOtheme's `Source`/`SchemaBuilder`, recording what
 * the service registers on it.
 *
 * Mirrors the real signature `SchemaBuilder::objectType(string $name,
 * $config = []): void` (yootheme/packages/graphql/src/SchemaBuilder.php:220).
 * Deliberately NOT a Mockery mock: the contract here is about the STATE the
 * service produces (which types, with which configs), and asserting recorded
 * state beats asserting interactions.
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
 * Test-owned recording double for YOOtheme's `Event` façade.
 *
 * Verified at authoring time: `YOOtheme\Event` is NOT autoloadable at the
 * unit tier (the parent theme is gitignored — .gitignore:138 ignores
 * `app/content/themes/*` and re-includes only `josworld/` — and is outside
 * the composer autoloader), so declaring it here collides with nothing and
 * lets the unit tier run in CI without the licensed theme.
 *
 * The API surface mirrors the real class exactly — same namespace, same
 * class name, same `on(string $event, callable $listener, int $priority = 0)`
 * signature (yootheme/packages/utils/src/Event.php). The service under test
 * is NOT aware of this double, is not injected with it, and calls
 * `Event::on(...)` exactly as it will in production; the double simply
 * records the arguments. That is a genuine seam observation.
 *
 * What it deliberately does NOT model: the real dispatcher's krsort()
 * ordering behaviour. The priority is asserted as a VALUE here; that -10
 * actually orders correctly against the base types is vendor behaviour,
 * ground-truthed by reading EventDispatcher::addListener() and deferred to
 * the T08 integration gate and the T11 acceptance drive.
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
