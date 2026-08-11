<?php

/**
 * Contract for SchemaMapper::toObjectType(): scalars map to GraphQL
 * String/Boolean; relation/group/unknown/malformed types are OMITTED
 * entirely — never null, never a String default, which would auto-expose
 * future upstream field types through the builder and render repeaters as
 * the literal text "Array". Labels carry through as metadata.label, the
 * only nesting YOOtheme reads. Bare-string entries (`'title' => 'text'`)
 * are framework-legal (ntdst-core setupSanitizers() accepts a raw string)
 * and must not fatal. Fixtures are copied from the real josworld schemas.
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

final class SchemaMapperTest extends TestCase
{
    /** The meta prefix every josworld model uses. */
    private const PREFIX = '_jw_';

    // text/email/textarea/select are real fields from the Team, Case and
    // Vacature services.
    public function test_string_ish_scalars_map_to_the_graphql_string_type(): void
    {
        $schema = [
            'role' => ['type' => 'text', 'label' => 'Functie'],
            'uitdaging_body' => ['type' => 'textarea', 'label' => 'Uitdaging'],
            'email' => ['type' => 'email', 'label' => 'E-mailadres'],
            'employment_type' => ['type' => 'select', 'label' => 'Type tewerkstelling'],
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        foreach (['role', 'uitdaging_body', 'email', 'employment_type'] as $field) {
            $this->assertArrayHasKey($field, $mapped, "`{$field}` must be mapped, not skipped");
            $this->assertSame(
                'String',
                $mapped[$field]['type'],
                "`{$field}` must map to the GraphQL String type",
            );
        }
    }

    // boolean must NOT map to String — that would render "1"/"" in the
    // builder instead of a usable true/false.
    public function test_boolean_maps_to_the_graphql_boolean_type(): void
    {
        $schema = [
            'show_testimonial' => ['type' => 'boolean', 'label' => 'Toon testimonial'],
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        $this->assertArrayHasKey('show_testimonial', $mapped);
        $this->assertSame('Boolean', $mapped['show_testimonial']['type']);
    }

    // assertArrayNotHasKey deliberately: a null-valued key still reaches
    // YOOtheme's objectType builder and surfaces as a broken picker field.
    public function test_relation_and_group_fields_are_omitted_entirely(): void
    {
        $schema = [
            'subtitle' => ['type' => 'text', 'label' => 'Subtitel'],
            'file' => ['type' => 'relation', 'label' => 'Bestand', 'post_type' => 'attachment'],
            'related_tools' => ['type' => 'relation', 'label' => 'Gerelateerde tools'],
            'team' => ['type' => 'relation', 'label' => 'Teamleden'],
            'address' => ['type' => 'group', 'label' => 'Adres'],
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        foreach (['file', 'related_tools', 'team', 'address'] as $unmapped) {
            $this->assertArrayNotHasKey(
                $unmapped,
                $mapped,
                "`{$unmapped}` must be OMITTED from the map entirely, not mapped to null or String",
            );
        }

        // Omission must skip the field, not abort the model.
        $this->assertArrayHasKey('subtitle', $mapped);
        $this->assertSame('String', $mapped['subtitle']['type']);
    }

    // Whitelist, not blacklist: an unknown type must not default to
    // String, or a future upstream field type auto-exposes through the
    // builder as a stringified array.
    public function test_unknown_and_malformed_types_are_omitted_without_throwing(): void
    {
        $schema = [
            'role' => ['type' => 'text', 'label' => 'Functie'],
            'wormhole_field' => ['type' => 'wormhole', 'label' => 'Wormgat'],
            'empty_type_field' => ['type' => '', 'label' => 'Leeg'],
            'no_type_field' => ['label' => 'Geen type'],
            'null_type_field' => ['type' => null, 'label' => 'Null type'],
            'phone' => ['type' => 'text', 'label' => 'Telefoonnummer'],
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        foreach (['wormhole_field', 'empty_type_field', 'no_type_field', 'null_type_field'] as $bad) {
            $this->assertArrayNotHasKey(
                $bad,
                $mapped,
                "`{$bad}` has no mapping and must be omitted, never defaulted to String",
            );
        }

        // One bad field must not poison the map — good fields on BOTH
        // sides of the garbage still register.
        $this->assertArrayHasKey('role', $mapped, 'a field BEFORE the bad ones must still map');
        $this->assertArrayHasKey('phone', $mapped, 'a field AFTER the bad ones must still map');
        $this->assertSame('String', $mapped['phone']['type']);
    }

    // Labels live at metadata.label — a top-level label never renders.
    // Subset assertion (label value only) so the implementer may add
    // metadata keys. Full contract: FieldMetadataTest.
    public function test_field_labels_carry_through_to_the_output_metadata(): void
    {
        $schema = [
            'role' => ['type' => 'text', 'label' => 'Functie'],
            'show_testimonial' => ['type' => 'boolean', 'label' => 'Toon testimonial'],
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        $this->assertSame('Functie', $mapped['role']['metadata']['label']);
        $this->assertSame('Toon testimonial', $mapped['show_testimonial']['metadata']['label']);
    }

    // The mapper receives getSchema() verbatim and inherits the
    // framework's bare-string tolerance; an unguarded $config['type'] on
    // a string is a PHP 8 TypeError — a white page in the builder.
    public function test_a_bare_string_schema_entry_maps_by_the_same_rules(): void
    {
        $schema = [
            'subtitle' => 'text',
            'is_featured' => 'boolean',
            'linked' => 'relation',
            'mystery' => 'wormhole',
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        $this->assertSame('String', $mapped['subtitle']['type']);
        $this->assertSame('Boolean', $mapped['is_featured']['type']);
        $this->assertArrayNotHasKey('linked', $mapped, 'a bare-string relation is still omitted');
        $this->assertArrayNotHasKey('mystery', $mapped, 'a bare-string unknown type is still omitted');
    }

    // Denial path of the optional $typeName parameter: a two-arg call has
    // no name to compose <Type><Field>Item from, so a repeater is OMITTED —
    // an unscoped ImpactItem would collide across CPTs (RepeaterMappingTest).
    public function test_a_repeater_is_omitted_when_no_parent_type_name_is_supplied(): void
    {
        $schema = [
            'subtitle' => ['type' => 'text', 'label' => 'Subtitel'],
            'takeaways' => [
                'type' => 'repeater',
                'label' => 'Takeaways',
                'sub_fields' => [
                    'figure' => ['type' => 'text', 'label' => 'Cijfer'],
                    'body' => ['type' => 'textarea', 'label' => 'Toelichting'],
                ],
            ],
        ];

        // Two-arg call: no parent type name, so no sub-type name can be composed.
        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        $this->assertArrayNotHasKey(
            'takeaways',
            $mapped,
            'without a parent type name a repeater must be OMITTED — never String, never an unscoped listOf',
        );
        $this->assertArrayHasKey('subtitle', $mapped);

        // ...and nothing is emitted to register either.
        $this->assertSame([], SchemaMapper::toSubObjectTypes($schema, self::PREFIX));
    }

    // A model with no custom fields must map to an empty type, not crash
    // the source.init listener registering the CPTs.
    public function test_an_empty_schema_yields_an_empty_map(): void
    {
        $this->assertSame([], SchemaMapper::toObjectType([], self::PREFIX));
    }
}
