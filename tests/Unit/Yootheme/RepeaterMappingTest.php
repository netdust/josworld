<?php

/**
 * Contract for repeater mapping: a repeater yields
 * `['type' => ['listOf' => '<Type><Field>Item']]` plus a sub-object type
 * definition emitted by a SECOND static method (toSubObjectTypes()), so
 * toObjectType()'s flat return and the class's purity stay untouched.
 * Names are UpperCamelCase from parent type + field + 'Item' — the parent
 * scope and the Item suffix keep same-named fields and CPTs from
 * colliding. Sub-fields map by the same scalar omit-don't-default rules;
 * a repeater without usable sub_fields is omitted entirely (a listOf of
 * an empty type is invisibly-broken, omission is visibly-absent); nested
 * repeaters are explicitly unsupported; `condition` is presentation-only
 * and must not affect mapping.
 *
 * Value-shape edges (zero rows, non-array stored value) belong to the
 * value-reading unit — FieldResolverTest — not this pure type mapper.
 * Fixtures are copied from the real Case/Tool schemas.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use NtdstTests\Unit\TestCase;
use josworld\services\yootheme\SchemaMapper;

// The theme has no PSR-4 autoloader — require the unit under test
// directly. ABSPATH is defined by tests/bootstrap-unit.php.
require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';

final class RepeaterMappingTest extends TestCase
{
    /** The meta prefix every josworld model uses. */
    private const PREFIX = '_jw_';

    /** The parent object type name derived from the `case` post type. */
    private const CASE_TYPE = 'Case';

    /**
     * CaseService's real `impact` repeater — two text sub-fields, no condition.
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

    // The core contract. The negative half matters most: a repeater
    // flattened to 'String' renders the literal text "Array" in the
    // builder and LOOKS like working output.
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

        // The scalar sibling is untouched by repeater support.
        $this->assertSame('String', $mapped['client']['type']);
    }

    // The label carries through at metadata.label — the only nesting
    // YOOtheme reads; a top-level label never renders.
    public function test_the_repeater_label_carries_through_to_the_output_metadata(): void
    {
        $mapped = SchemaMapper::toObjectType(
            ['impact' => $this->impactRepeater()],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertSame('Impact', $mapped['impact']['metadata']['label']);
    }

    // A listOf reference is useless unless something registers the type:
    // the definition is keyed by type name, in the ['fields' => ...] shape
    // Source::objectType() consumes.
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

    // Sub-fields map by the same scalar rules as top-level fields — this
    // is what lets the builder's Grid bind per-row values.
    public function test_sub_fields_map_by_the_same_scalar_rules_as_top_level_fields(): void
    {
        $schema = [
            // Real takeaways sub_fields plus a boolean to pin the
            // non-String scalar rule too.
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

        // Labels are read at metadata.label only. Sub-fields deliberately
        // carry no metadata.group — pinned in FieldMetadataTest.
        $this->assertSame('Titel', $fields['title']['metadata']['label'], 'sub-field labels carry through');
        $this->assertSame('Tekst', $fields['body']['metadata']['label']);
    }

    // Omit-don't-default, one level down: a relation sub-field defaulting
    // to String would stringify a post-ID array into the builder, and a
    // future upstream field type would auto-expose the same way.
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

        // One bad sub-field must not poison the row type.
        $this->assertArrayHasKey('figure', $fields, 'a sub-field BEFORE the bad ones must still map');
        $this->assertArrayHasKey('description', $fields, 'a sub-field AFTER the bad ones must still map');
        $this->assertSame('String', $fields['description']['type']);
    }

    // Nested repeaters are explicitly omitted and emit no second-level
    // type — pinned so the behaviour is defined rather than left to
    // whoever writes the recursion.
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

    // A repeater without usable sub_fields is omitted entirely, without
    // throwing — a listOf of an empty type would give the editor a
    // bindable field whose every row renders nothing.
    public function test_a_repeater_without_usable_sub_fields_is_omitted_without_throwing(): void
    {
        $schema = [
            'client' => ['type' => 'text', 'label' => 'Klant'],
            'no_sub_fields' => ['type' => 'repeater', 'label' => 'Geen subvelden'],
            'empty_sub_fields' => ['type' => 'repeater', 'label' => 'Leeg', 'sub_fields' => []],
            'string_sub_fields' => ['type' => 'repeater', 'label' => 'Kapot', 'sub_fields' => 'nope'],
            // All-unmapped sub_fields collapse to the same empty case.
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

        // The bad repeaters poison neither the good repeater nor scalars.
        $this->assertSame(['listOf' => 'CaseImpactItem'], $mapped['impact']['type']);
        $this->assertSame('String', $mapped['client']['type']);
    }

    // condition is presentation-only and never gates storage, so a
    // conditional repeater maps identically — "it has a condition, so
    // skip it" would silently hide two of the four live repeaters.
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

    // Two naming properties: separators are word boundaries (an
    // underscore in a GraphQL type name is illegal), and different parent
    // types must not collide on the same field name.
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

        $toolTypes = SchemaMapper::toSubObjectTypes(['benefits' => $benefits], self::PREFIX, 'Tool');
        $this->assertSame(['ToolBenefitsItem'], array_keys($toolTypes));

        // Same field name, different parent type: MUST NOT collide.
        $caseTypes = SchemaMapper::toSubObjectTypes(['benefits' => $benefits], self::PREFIX, self::CASE_TYPE);
        $this->assertSame(
            ['CaseBenefitsItem'],
            array_keys($caseTypes),
            'the same field name under a different parent type must yield a DIFFERENT sub-type name',
        );

        // Underscores are word boundaries, not literal characters.
        $snake = SchemaMapper::toSubObjectTypes(['key_figures' => $keyFigures], self::PREFIX, self::CASE_TYPE);
        $this->assertSame(
            ['CaseKeyFiguresItem'],
            array_keys($snake),
            'a snake_case field name must camel-case into the type name',
        );
    }

    // An empty array — not null, not a stray entry — keeps repeater-less
    // CPTs from registering a phantom type.
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
