<?php

/**
 * T06 CONTRACT TEST — authored by the independent test-author BEFORE any
 * repeater implementation exists, and IMMUTABLE to the implementer: green it
 * without weakening it. A dispute is an escalation, not an edit.
 *
 * WHAT THIS PINS (from tasks.md T06's acceptance criteria + FR-5 + plan.md:41
 * `repeater -> listOf(<Type><Field>Item)` — NOT from any implementation, which
 * did not exist when this was written):
 *   1. A repeater yields `['type' => ['listOf' => '<Type><Field>Item']]` — a
 *      SUB-OBJECT type reference, never 'String', never a bare listOf String.
 *   2. The sub-object type DEFINITION is emitted alongside, via a second
 *      static method, in YOOtheme's `['fields' => …]` objectType shape.
 *   3. `sub_fields` map by the SAME scalar rules T04 established, including
 *      the omit-don't-default denial path (threat 2).
 *   4. Naming is derived from parent type + field + 'Item', UpperCamelCase,
 *      so T07 can register a stable, collision-free type name.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * DESIGN DECISIONS THIS TEST PINS, AND WHY — recorded here because the task
 * brief explicitly delegated them to the test-author.
 *
 * (a) HOW THE SUB-TYPE DEFINITIONS ARE RETURNED. `toObjectType()` returns a
 *     FLAT `field => definition` map and T04's eight committed tests index it
 *     directly (`$mapped['role']['type']`). Enriching that return (e.g.
 *     `['fields' => …, 'types' => …]`) would break all eight. An out-parameter
 *     would break the purity the class docblock insists on. So: a SECOND
 *     STATIC METHOD, `SchemaMapper::toSubObjectTypes()`. Two pure functions
 *     over the same input, each with one job — the mapper stays stateless and
 *     T04's contract is untouched.
 *
 * (b) WHERE THE TYPE NAME COMES FROM. The plan pins `<Type><Field>Item`, but
 *     T04's fixed signature carries no `<Type>`: `$prefix` is `_jw_` (a meta
 *     prefix), which cannot yield `Case`. The parent type name is computed by
 *     T07 from the post type. Resolution: a THIRD, OPTIONAL parameter on
 *     `toObjectType(array $schema, string $prefix, string $typeName = '')`.
 *     Additive with a default, so every T04 call site (two-arg) is unchanged
 *     and all eight T04 tests stay green — verified by running the whole file.
 *
 * (c) NAMING SCHEME — `Case` + `impact` + `Item` => `CaseImpactItem`.
 *     UpperCamelCase, matching YOOtheme's own `Str::camelCase($x, true)`
 *     convention (packages/utils/src/Str.php:182, which also maps `_`/`-` to
 *     word boundaries). GROUND-TRUTHED against YOOtheme's closest analogue,
 *     Toolset's `loadRfgField()` (builder-wordpress-toolset/src/Type/
 *     FieldsType.php:139-145), which does exactly this three-step dance:
 *     camelCase a name from parts, `$source->objectType($type, …)`, then
 *     return `['type' => ['listOf' => $type]] + $config`. The `Item` suffix is
 *     this project's choice (Toolset uses `group`); it is what makes the
 *     sub-type name collision-free against a same-named CPT — a model called
 *     `impact` would otherwise collide with `case`'s `impact` sub-type.
 *     The mapper must NOT import YOOtheme's `Str`: purity is the whole reason
 *     this class is unit-testable (T04 docblock). Re-implement the casing.
 *
 * (d) SUB-FIELD `label` CARRIES THROUGH, same as T04's rule 5 — the editor
 *     picks `figure`/`description` by label in the builder's grid mapping.
 *
 * (e) A REPEATER WITH NO / EMPTY `sub_fields` IS OMITTED ENTIRELY — the same
 *     denial as an unknown type, and for the same reason (threat 2). A
 *     `listOf` of an EMPTY sub-object type is not a safe middle ground: it
 *     registers a field the editor can bind, whose every row renders nothing,
 *     which looks like broken data rather than an absent feature. Omission is
 *     visibly-absent; an empty type is invisibly-broken. Same argument T04
 *     made for omit-don't-default, applied one level down.
 *
 * (f) NESTED REPEATERS ARE EXPLICITLY NOT SUPPORTED — a `repeater` inside
 *     `sub_fields` is OMITTED from the sub-type, exactly like any other
 *     unmapped sub-field type. It occurs nowhere in josworld today (verified:
 *     all four repeaters have scalar-only sub_fields), and ntdst-core's
 *     MetaboxGenerator does not render one either. Pinned so the behaviour is
 *     DEFINED rather than accidental: without this test, a recursive
 *     implementation and an omitting one are both "correct" and the choice
 *     gets made by whoever writes the loop.
 *
 * (g) `condition` IS IRRELEVANT TO MAPPING. `takeaways`/`interview` carry
 *     `'condition' => ['has_takeaways' => true]`. CaseService's own docblock
 *     records that condition is PRESENTATION-ONLY (T11 acceptance criteria) —
 *     an editor-screen visibility toggle that never gates storage. A
 *     conditional repeater must therefore map IDENTICALLY to an unconditional
 *     one: the field exists in the schema, so it exists as a builder source.
 *     Skipping it would hide a field whose rows are genuinely stored. Asserted
 *     rather than assumed, because "it has a condition, so skip it" is the
 *     plausible-looking wrong turn.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * WHAT THIS TEST DELIBERATELY DOES *NOT* COVER — and where it went instead.
 *
 * The task line names two VALUE-shape edges: "zero rows yields an empty list
 * not null" and "a stored value that is not an array yields an empty list
 * without fatal" (flow 2's `empty:` and `mid-flow failure:` edges). Neither
 * belongs to T06. This class is the pure TYPE mapper — it never sees a stored
 * value; it maps a SCHEMA. Zero rows and a malformed `_jw_impact` row are read
 * from post meta by T05's `FieldResolver`, which is the only unit that can
 * observe them.
 *
 * Testing them here would require inventing a value-handling method on the
 * type mapper purely to satisfy the task line — the anti-pattern of writing a
 * test for the wrong unit and calling the risk covered. So they are ESCALATED,
 * NOT DROPPED: T05's test file owes both, and this note is the handoff.
 *
 *   Risk this test does NOT cover: repeater VALUE resolution (zero rows ->
 *   empty list not null; non-array stored value -> empty list, no fatal) —
 *   deferred to T05 FieldResolverTest (unit), and to the /shakeout flow-2
 *   acceptance drive for the real builder render.
 *
 * What T06 CAN and DOES pin at the type tier is the structural half of that
 * same guarantee: the field is typed `listOf`, so an empty PHP array resolves
 * to an empty GraphQL list rather than a null scalar. A repeater typed
 * 'String' is what would make "no rows" render the literal text "Array".
 *
 * ─────────────────────────────────────────────────────────────────────────
 * The fixtures below are COPIED FROM THE REAL SCHEMAS, verbatim in structure:
 * CaseService `impact` (:148), `takeaways` (:207, conditional), `interview`
 * (:244, conditional), ToolService `benefits` (:100, single sub-field). Those
 * are all four repeaters in josworld — grep-confirmed across the CPTs.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use NtdstTests\Unit\TestCase;
use josworld\services\yootheme\SchemaMapper;

// The theme has no PSR-4 autoloader — require the unit under test directly,
// the same pattern SchemaMapperTest uses. ABSPATH is already defined by
// tests/bootstrap-unit.php, so the file's `defined('ABSPATH') || exit;` guard
// is a no-op here.
require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';

final class RepeaterMappingTest extends TestCase
{
    /** The meta prefix every josworld model uses (FR-12/SC-5). */
    private const PREFIX = '_jw_';

    /** The parent object type name T07 derives from the `case` post type. */
    private const CASE_TYPE = 'Case';

    /**
     * CaseService's real `impact` repeater (CaseService.php:148) — two text
     * sub-fields, no condition. The canonical shape flow 2 binds to a Grid.
     *
     * @return array<string, mixed>
     */
    private function impactRepeater(): array
    {
        return [
            'type' => 'repeater',
            'label' => 'Impact',
            'button_text' => 'Impactcijfer toevoegen',
            'sub_fields' => [
                'figure' => ['type' => 'text', 'label' => 'Cijfer'],
                'description' => ['type' => 'text', 'label' => 'Omschrijving'],
            ],
        ];
    }

    /**
     * 1. THE CORE CONTRACT (FR-5, plan.md:41). A repeater maps to a `listOf`
     * of a SUB-OBJECT TYPE — not to 'String', and not to a listOf of a scalar.
     *
     * `['type' => ['listOf' => 'CaseImpactItem']]` is YOOtheme's own wire
     * shape for a list field, ground-truthed against Toolset's
     * `loadRfgField()` and PostType.php's `children` field (:390).
     *
     * The negative half is the one that matters: a repeater flattened to
     * 'String' renders the literal text "Array" in the builder and LOOKS like
     * working output — the exact failure T04's interim omission existed to
     * prevent, which this task must now replace with a real type.
     */
    public function test_a_repeater_maps_to_a_listof_of_a_sub_object_type(): void
    {
        $schema = [
            'client' => ['type' => 'text', 'label' => 'Klant'],
            'impact' => $this->impactRepeater(),
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX, self::CASE_TYPE);

        $this->assertArrayHasKey(
            'impact',
            $mapped,
            'T06 replaces T04\'s interim omission: a repeater must now be MAPPED, not skipped',
        );
        $this->assertSame(
            ['listOf' => 'CaseImpactItem'],
            $mapped['impact']['type'],
            'a repeater must be a listOf of its own sub-object type, never String and never listOf String',
        );

        // The scalar sibling is untouched — adding repeater support must not
        // regress T04's mapping.
        $this->assertSame('String', $mapped['client']['type']);
    }

    /**
     * 2. The repeater's editor-facing label carries through, same as T04's
     * rule 5. Without it the builder's field picker shows the raw meta key.
     */
    public function test_the_repeater_label_carries_through_to_the_output_metadata(): void
    {
        $mapped = SchemaMapper::toObjectType(
            ['impact' => $this->impactRepeater()],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertSame('Impact', $mapped['impact']['label']);
    }

    /**
     * 3. THE SUB-TYPE DEFINITION IS EMITTED, in YOOtheme's objectType shape.
     *
     * A `listOf => 'CaseImpactItem'` reference is useless unless something
     * registers `CaseImpactItem`. T07 calls `$source->objectType($name, $cfg)`
     * for each entry this returns; `['fields' => …]` is the shape
     * `Source::objectType()` consumes (ground-truthed against Toolset's
     * `GroupType::config()`, which returns exactly `['fields' => [...]]`).
     *
     * Keyed BY TYPE NAME so T07 can iterate `$name => $config` directly.
     */
    public function test_the_sub_object_type_definition_is_emitted_in_yootheme_object_type_shape(): void
    {
        $types = SchemaMapper::toSubObjectTypes(
            ['impact' => $this->impactRepeater()],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertArrayHasKey(
            'CaseImpactItem',
            $types,
            'the sub-object type must be emitted under the SAME name the listOf references',
        );
        $this->assertArrayHasKey(
            'fields',
            $types['CaseImpactItem'],
            "YOOtheme's Source::objectType() consumes ['fields' => …]; the sub-type config must use it",
        );
    }

    /**
     * 4. SUB-FIELDS MAP BY THE SAME SCALAR RULES T04 ESTABLISHED.
     *
     * text -> String, textarea -> String, boolean -> Boolean, and the label
     * carries through. This is what makes the builder's Grid able to bind
     * `figure` and `description` per row (flow 2).
     */
    public function test_sub_fields_map_by_the_same_scalar_rules_as_top_level_fields(): void
    {
        $schema = [
            // `takeaways`' real sub_fields are text + textarea (CaseService:212),
            // plus a boolean added here to pin the non-String scalar rule too.
            'takeaways' => [
                'type' => 'repeater',
                'label' => 'Take-aways',
                'condition' => ['has_takeaways' => true],
                'sub_fields' => [
                    'title' => ['type' => 'text', 'label' => 'Titel'],
                    'body' => ['type' => 'textarea', 'label' => 'Tekst'],
                    'featured' => ['type' => 'boolean', 'label' => 'Uitgelicht'],
                ],
            ],
        ];

        $types = SchemaMapper::toSubObjectTypes($schema, self::PREFIX, self::CASE_TYPE);
        $fields = $types['CaseTakeawaysItem']['fields'];

        $this->assertSame('String', $fields['title']['type'], 'a text sub-field maps to String');
        $this->assertSame('String', $fields['body']['type'], 'a textarea sub-field maps to String');
        $this->assertSame(
            'Boolean',
            $fields['featured']['type'],
            'a boolean sub-field maps to Boolean, NOT String — same rule as top level',
        );

        $this->assertSame('Titel', $fields['title']['label'], 'sub-field labels carry through');
        $this->assertSame('Tekst', $fields['body']['label']);
    }

    /**
     * 5. THE DENIAL PATH, ONE LEVEL DOWN (threat 2). An unmapped sub-field
     * type is OMITTED from the sub-object type — never defaulted to 'String'.
     *
     * This is T04's omit-don't-default whitelist rule applied inside
     * `sub_fields`, and it is the assertion most likely to be missing from an
     * implementation that reuses the top-level loop carelessly: a `relation`
     * sub-field defaulting to String would stringify a post-ID array into the
     * builder as visible garbage, and — threat 2 — a future ntdst-core field
     * type would auto-expose the same way.
     *
     * Four garbage shapes, mirroring T04's rule 4: a relation, an invented
     * type, an entry with no `type` key, and an empty type. Plus FR-6's
     * poison rule: the good sub-fields on BOTH sides still map.
     */
    public function test_unmapped_sub_field_types_are_omitted_never_defaulted_to_string(): void
    {
        $schema = [
            'impact' => [
                'type' => 'repeater',
                'label' => 'Impact',
                'sub_fields' => [
                    'figure' => ['type' => 'text', 'label' => 'Cijfer'],
                    'linked_case' => ['type' => 'relation', 'label' => 'Gelinkte case'],
                    'wormhole' => ['type' => 'wormhole', 'label' => 'Wormgat'],
                    'no_type' => ['label' => 'Geen type'],
                    'empty_type' => ['type' => '', 'label' => 'Leeg'],
                    'description' => ['type' => 'text', 'label' => 'Omschrijving'],
                ],
            ],
        ];

        $types = SchemaMapper::toSubObjectTypes($schema, self::PREFIX, self::CASE_TYPE);
        $fields = $types['CaseImpactItem']['fields'];

        foreach (['linked_case', 'wormhole', 'no_type', 'empty_type'] as $bad) {
            $this->assertArrayNotHasKey(
                $bad,
                $fields,
                "sub-field `{$bad}` has no mapping and must be OMITTED, never defaulted to String",
            );
        }

        // FR-6 one level down: one bad sub-field must not poison the row type.
        $this->assertArrayHasKey('figure', $fields, 'a sub-field BEFORE the bad ones must still map');
        $this->assertArrayHasKey('description', $fields, 'a sub-field AFTER the bad ones must still map');
        $this->assertSame('String', $fields['description']['type']);
    }

    /**
     * 6. NESTED REPEATERS ARE EXPLICITLY OMITTED (decision (f)).
     *
     * A `repeater` inside `sub_fields` maps by NO rule — it falls through the
     * same omission path as any other unmapped sub-field type, and emits no
     * second-level sub-object type. Pinned so the behaviour is defined rather
     * than left to whoever writes the recursion, and so a future nested
     * repeater fails VISIBLY (an absent field) instead of registering a
     * half-built type name T07 would then try to register.
     */
    public function test_a_nested_repeater_sub_field_is_omitted_and_emits_no_second_level_type(): void
    {
        $schema = [
            'impact' => [
                'type' => 'repeater',
                'label' => 'Impact',
                'sub_fields' => [
                    'figure' => ['type' => 'text', 'label' => 'Cijfer'],
                    'nested' => [
                        'type' => 'repeater',
                        'label' => 'Genest',
                        'sub_fields' => [
                            'deep' => ['type' => 'text', 'label' => 'Diep'],
                        ],
                    ],
                ],
            ],
        ];

        $types = SchemaMapper::toSubObjectTypes($schema, self::PREFIX, self::CASE_TYPE);

        $this->assertArrayNotHasKey(
            'nested',
            $types['CaseImpactItem']['fields'],
            'a nested repeater sub-field is omitted — nesting is explicitly unsupported',
        );
        $this->assertArrayHasKey('figure', $types['CaseImpactItem']['fields']);

        $this->assertCount(
            1,
            $types,
            'exactly one sub-object type — a nested repeater must NOT emit a second-level type',
        );
    }

    /**
     * 7. THE DENIAL PATH FOR A MALFORMED REPEATER (decision (e)). A repeater
     * with NO `sub_fields`, an EMPTY `sub_fields`, or a non-array
     * `sub_fields` is OMITTED ENTIRELY — from the field map AND from the
     * emitted sub-types — without throwing.
     *
     * A `listOf` of an empty sub-object type is the tempting middle ground and
     * is worse: it gives the editor a bindable field whose every row renders
     * nothing, which reads as broken data rather than an absent feature. Same
     * argument T04 made for omit-don't-default.
     *
     * FR-6 again: the surviving repeater and the surviving scalar still map.
     */
    public function test_a_repeater_without_usable_sub_fields_is_omitted_without_throwing(): void
    {
        $schema = [
            'client' => ['type' => 'text', 'label' => 'Klant'],
            'no_sub_fields' => ['type' => 'repeater', 'label' => 'Geen subvelden'],
            'empty_sub_fields' => ['type' => 'repeater', 'label' => 'Leeg', 'sub_fields' => []],
            'string_sub_fields' => ['type' => 'repeater', 'label' => 'Kapot', 'sub_fields' => 'nope'],
            // A repeater whose sub_fields exist but are ALL unmapped collapses
            // to the same empty case and must be omitted for the same reason.
            'all_unmapped' => [
                'type' => 'repeater',
                'label' => 'Alles ongemapt',
                'sub_fields' => ['rel' => ['type' => 'relation', 'label' => 'Rel']],
            ],
            'impact' => $this->impactRepeater(),
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX, self::CASE_TYPE);
        $types = SchemaMapper::toSubObjectTypes($schema, self::PREFIX, self::CASE_TYPE);

        foreach (['no_sub_fields', 'empty_sub_fields', 'string_sub_fields', 'all_unmapped'] as $bad) {
            $this->assertArrayNotHasKey(
                $bad,
                $mapped,
                "repeater `{$bad}` has no usable sub_fields and must be OMITTED, not typed listOf of an empty type",
            );
        }

        $this->assertSame(
            ['CaseImpactItem'],
            array_keys($types),
            'only the one usable repeater may emit a sub-object type',
        );

        // FR-6: the bad repeaters poison neither the good repeater nor scalars.
        $this->assertSame(['listOf' => 'CaseImpactItem'], $mapped['impact']['type']);
        $this->assertSame('String', $mapped['client']['type']);
    }

    /**
     * 8. A CONDITIONAL REPEATER MAPS IDENTICALLY (decision (g)).
     *
     * `takeaways` and `interview` carry `'condition' => [...]`. CaseService's
     * docblock records that condition is PRESENTATION-ONLY — an editor-screen
     * visibility toggle that never gates storage, and rows entered while the
     * block was hidden still round-trip on save. So a conditional repeater's
     * rows are genuinely stored and MUST be bindable in the builder.
     *
     * Asserted rather than assumed because "it has a condition, so skip it" is
     * the plausible-looking wrong turn, and it would silently hide two of
     * josworld's four repeaters.
     */
    public function test_a_conditional_repeater_maps_exactly_like_an_unconditional_one(): void
    {
        $conditional = [
            'type' => 'repeater',
            'label' => 'Take-aways',
            'button_text' => 'Take-away toevoegen',
            'condition' => ['has_takeaways' => true],
            'sub_fields' => [
                'title' => ['type' => 'text', 'label' => 'Titel'],
                'body' => ['type' => 'textarea', 'label' => 'Tekst'],
            ],
        ];

        $mapped = SchemaMapper::toObjectType(['takeaways' => $conditional], self::PREFIX, self::CASE_TYPE);
        $types = SchemaMapper::toSubObjectTypes(['takeaways' => $conditional], self::PREFIX, self::CASE_TYPE);

        $this->assertSame(
            ['listOf' => 'CaseTakeawaysItem'],
            $mapped['takeaways']['type'],
            'a `condition` is an editor-screen concern and must not affect type mapping',
        );
        $this->assertArrayHasKey('CaseTakeawaysItem', $types);
        $this->assertSame('String', $types['CaseTakeawaysItem']['fields']['title']['type']);
    }

    /**
     * 9. THE NAMING SCHEME IS STABLE AND COLLISION-FREE (decision (c)).
     *
     * T07 registers these names with YOOtheme, so they must be derivable and
     * unique. Two properties are pinned:
     *
     *   - UpperCamelCase from parent type + field + 'Item', with `_`/`-`
     *     treated as word boundaries (YOOtheme's own Str::camelCase rule) —
     *     so a hypothetical `key_figures` field yields `CaseKeyFiguresItem`,
     *     NOT `Casekey_figuresItem`, which is not a legal GraphQL type name.
     *   - DIFFERENT PARENT TYPES DO NOT COLLIDE. `Tool`'s `benefits` and a
     *     `Case`'s `benefits` must be two distinct types; a scheme keyed on
     *     the field name alone would silently merge them and give one CPT the
     *     other's row shape.
     */
    public function test_sub_type_names_are_upper_camel_case_and_scoped_by_parent_type(): void
    {
        $benefits = [
            'type' => 'repeater',
            'label' => 'Voordelen',
            'sub_fields' => ['benefit' => ['type' => 'text', 'label' => 'Voordeel']],
        ];
        $keyFigures = [
            'type' => 'repeater',
            'label' => 'Kerncijfers',
            'sub_fields' => ['figure' => ['type' => 'text', 'label' => 'Cijfer']],
        ];

        // ToolService's real `benefits` repeater, under the `Tool` type.
        $toolTypes = SchemaMapper::toSubObjectTypes(['benefits' => $benefits], self::PREFIX, 'Tool');
        $this->assertSame(['ToolBenefitsItem'], array_keys($toolTypes));

        // Same field name, different parent type: MUST NOT collide.
        $caseTypes = SchemaMapper::toSubObjectTypes(['benefits' => $benefits], self::PREFIX, self::CASE_TYPE);
        $this->assertSame(
            ['CaseBenefitsItem'],
            array_keys($caseTypes),
            'the same field name under a different parent type must yield a DIFFERENT sub-type name',
        );

        // Underscores are word boundaries, not literal characters — an
        // underscore in a GraphQL type name here would be a silent naming drift.
        $snake = SchemaMapper::toSubObjectTypes(['key_figures' => $keyFigures], self::PREFIX, self::CASE_TYPE);
        $this->assertSame(
            ['CaseKeyFiguresItem'],
            array_keys($snake),
            'a snake_case field name must camel-case into the type name',
        );
    }

    /**
     * 10. A SCHEMA WITH NO REPEATERS EMITS NO SUB-TYPES.
     *
     * `team` and `vacature` have no repeaters at all. T07 iterates whatever
     * this returns and calls `objectType()` per entry, so an empty array — not
     * null, not a stray entry — is what keeps those two CPTs from registering
     * a phantom type.
     */
    public function test_a_schema_with_no_repeaters_emits_no_sub_object_types(): void
    {
        $schema = [
            'role' => ['type' => 'text', 'label' => 'Functie'],
            'email' => ['type' => 'email', 'label' => 'E-mailadres'],
            'team' => ['type' => 'relation', 'label' => 'Teamleden'],
        ];

        $this->assertSame([], SchemaMapper::toSubObjectTypes($schema, self::PREFIX, 'Team'));
        $this->assertSame([], SchemaMapper::toSubObjectTypes([], self::PREFIX, 'Team'));
    }
}
