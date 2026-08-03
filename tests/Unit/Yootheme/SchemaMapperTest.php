<?php

/**
 * T04 CONTRACT TEST — authored by the independent test-author BEFORE any
 * implementation exists, and IMMUTABLE to the implementer: green it without
 * weakening it. A dispute is an escalation, not an edit.
 *
 * WHAT THIS PINS (from tasks.md T04's acceptance criteria, FR-4/FR-6 — NOT
 * from any implementation, which did not exist when this was written):
 *   1. text/textarea/email/select -> 'String'
 *   2. boolean -> 'Boolean'
 *   3. relation and group -> OMITTED entirely (assertArrayNotHasKey, not null)
 *   4. an unknown/garbage type -> OMITTED, no throw, no notice, and the rest
 *      of the model still maps (FR-6: one bad field must not poison the map)
 *   5. field `label` carries through into the output metadata
 *   6. a BARE-STRING schema entry is handled without fatal
 *
 * ON (6) — GROUND-TRUTHED, not invented. ntdst-core's
 * `NTDST_Data_Model::setupSanitizers()` (api/Data.php, the
 * `foreach ($this->schema as $field => $type)` loop) branches on
 * `is_array($type)` and has an explicit `else` calling
 * `getDefaultSanitizer($type)` with the RAW string. So `['title' => 'text']`
 * is a shape the framework genuinely supports, and the mapper is fed the
 * schema verbatim via `getSchema()`. All four josworld services happen to use
 * the array form today, so this is the shape that is supported-but-unused —
 * exactly where an unguarded `$field['type']` on a string would surface as a
 * PHP 8 "Cannot access offset of type string on string" TypeError in the
 * builder. The test pins the framework's contract, not today's accident.
 *
 * ON `group` — CORRECTION TO THE TASK LINE, recorded deliberately rather than
 * silently: `'type' => 'group'` exists NOWHERE in ntdst-core (zero hits;
 * CaseService's own docblock states this is why its title/body pairs are two
 * flat fields). So `group` is not a "supported type deferred to v2" — it is,
 * today, indistinguishable from any other unrecognised type. The task line
 * still names it, and asserting it is cheap and forward-useful: if a `group`
 * type is ever added upstream, this test is what stops it silently mapping to
 * 'String' and rendering as "Array" in the builder. The assertion is right;
 * only the reason differs from what the task line implies.
 *
 * ON REPEATERS — T04 does not implement them (T06 does), but leaving their
 * behaviour undefined would hand T06 an ambiguous starting point, so this
 * test PINS THE INTERIM CONTRACT: a repeater is OMITTED at T04, exactly like
 * any other unmapped type. This is the safe direction — omission is what T06
 * must then turn into a `listOf`, and a missing field in the builder is a
 * visibly absent feature, whereas a repeater wrongly flattened to 'String'
 * would render as the literal text "Array" and look like working output.
 * T06 WILL flip these two assertions; that is a deliberate, contract-level
 * change made by T06's own test-author, not a weakening of this one.
 *
 * The fixtures below are COPIED FROM THE REAL SCHEMAS, not invented:
 * TeamService (role/email/phone), VacatureService (employment_type select),
 * CaseService (textarea, boolean, repeater, relation), ToolService (relation).
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use NtdstTests\Unit\TestCase;
use josworld\services\yootheme\SchemaMapper;

// The theme has no PSR-4 autoloader — require the unit under test directly,
// the same pattern BaselineInputHandlingTest uses for mu-plugin support
// classes. ABSPATH is already defined by tests/bootstrap-unit.php, so the
// file's `defined('ABSPATH') || exit;` guard is a no-op here.
require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';

final class SchemaMapperTest extends TestCase
{
    /**
     * The meta prefix every josworld model uses (FR-12/SC-5). Passed as the
     * mapper's second argument; T04 asserts nothing about how it is used —
     * meta-key composition is T05's contract (threat 3).
     */
    private const PREFIX = '_jw_';

    /**
     * 1. The four string-ish scalar types all collapse to GraphQL 'String'.
     *
     * `text`/`email` are TeamService's real fields, `textarea` is
     * CaseService's, `select` is VacatureService's `employment_type`.
     */
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

    /**
     * 2. `boolean` maps to GraphQL 'Boolean' — NOT to 'String'.
     *
     * CaseService has two real boolean fields. Mapping a boolean to 'String'
     * is the plausible-looking bug this asserts against: it would render
     * "1"/"" in the builder instead of a usable true/false.
     */
    public function test_boolean_maps_to_the_graphql_boolean_type(): void
    {
        $schema = [
            'show_testimonial' => ['type' => 'boolean', 'label' => 'Toon testimonial'],
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        $this->assertArrayHasKey('show_testimonial', $mapped);
        $this->assertSame('Boolean', $mapped['show_testimonial']['type']);
    }

    /**
     * 3. THE OMISSION CONTRACT (the negative path). `relation` and `group` are
     * v2 and must be ABSENT from the output — not null, not 'String'.
     *
     * `assertArrayNotHasKey`, deliberately: a null-valued key still reaches
     * YOOtheme's objectType builder and surfaces as a broken field in the
     * builder's picker, which is the failure mode this task exists to avoid.
     *
     * `file` and `related_tools` are ToolService's real relation fields;
     * `team` is CaseService's.
     */
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

        // ...and the surviving scalar still maps: omission must skip the field,
        // not abort the model.
        $this->assertArrayHasKey('subtitle', $mapped);
        $this->assertSame('String', $mapped['subtitle']['type']);
    }

    /**
     * 4. THE DENIAL PATH FOR UNTRUSTED SHAPES. An unrecognised type is omitted
     * rather than thrown on, and — FR-6 — the rest of the model still maps.
     *
     * This is the whitelist-vs-blacklist decision this project authored: an
     * unknown type must NOT fall through to a 'String' default. A future field
     * type added upstream in ntdst-core would otherwise auto-expose through
     * the builder as a stringified array (threat 2: "the bridge maps whatever
     * the schema declares").
     *
     * Four garbage shapes, all realistic: an invented type, an empty type, a
     * schema entry with NO `type` key at all, and a null type.
     */
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

        // FR-6: one bad field must not poison the whole map — the good fields
        // on BOTH sides of the garbage still register.
        $this->assertArrayHasKey('role', $mapped, 'a field BEFORE the bad ones must still map');
        $this->assertArrayHasKey('phone', $mapped, 'a field AFTER the bad ones must still map');
        $this->assertSame('String', $mapped['phone']['type']);
    }

    /**
     * 5. The editor-facing label carries through into the output metadata —
     * this is what YOOtheme shows in the builder's field picker. A mapper that
     * produced correct types but dropped every label would leave the editor
     * picking between raw meta keys.
     *
     * Asserted as a SUBSET (`assertSame` on the label value, not on the whole
     * definition array) so the implementer stays free to add metadata keys
     * this test does not know about.
     */
    public function test_field_labels_carry_through_to_the_output_metadata(): void
    {
        $schema = [
            'role' => ['type' => 'text', 'label' => 'Functie'],
            'show_testimonial' => ['type' => 'boolean', 'label' => 'Toon testimonial'],
        ];

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        $this->assertSame('Functie', $mapped['role']['label']);
        $this->assertSame('Toon testimonial', $mapped['show_testimonial']['label']);
    }

    /**
     * 6. A BARE-STRING schema entry maps by the same rules and does not fatal.
     *
     * Ground-truthed against ntdst-core's `setupSanitizers()`, which handles
     * `is_array($type) === false` by passing the raw string to
     * `getDefaultSanitizer()`. The mapper receives `getSchema()` verbatim, so
     * it inherits that tolerance. An unguarded `$config['type']` against a
     * string is a PHP 8 TypeError, which in the builder is a white screen.
     *
     * Label metadata is not asserted here: a bare-string entry carries no
     * label to carry through.
     */
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

    /**
     * 7. INTERIM CONTRACT FOR T06. A repeater is OMITTED at T04.
     *
     * T04's scope is scalars; T06 adds `listOf` sub-object mapping and WILL
     * change this assertion. Pinning it here means T06 starts from a DEFINED
     * behaviour rather than an accident, and — until T06 lands — guarantees a
     * repeater never leaks through as 'String' (which would render the literal
     * text "Array" in the builder and look like working output).
     *
     * The fixture is CaseService's real `takeaways` repeater shape.
     */
    public function test_a_repeater_is_omitted_until_t06_adds_listof_support(): void
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

        $mapped = SchemaMapper::toObjectType($schema, self::PREFIX);

        $this->assertArrayNotHasKey(
            'takeaways',
            $mapped,
            'T04 omits repeaters; T06 turns this into a listOf. It must never be String.',
        );
        $this->assertArrayHasKey('subtitle', $mapped);
    }

    /**
     * 8. An empty schema yields an empty array, not a notice or a null.
     *
     * The bridge iterates every registered model; a model with no custom
     * fields at all must map to an empty type, not crash the `source.init`
     * listener that is registering all four CPTs.
     */
    public function test_an_empty_schema_yields_an_empty_map(): void
    {
        $this->assertSame([], SchemaMapper::toObjectType([], self::PREFIX));
    }
}
