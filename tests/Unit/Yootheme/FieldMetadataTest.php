<?php

/**
 * T08 CONTRACT TEST — field `label`/`group` must be emitted under `metadata`,
 * which is the ONLY nesting YOOtheme reads.
 *
 * WHY THIS TEST EXISTS. T04's rule 5 ("the label carries through") and T06's
 * rule (d) ("sub-field labels carry through") were both correct about the
 * REQUIREMENT and wrong about the SHAPE: they pinned a TOP-LEVEL `label` key
 * on the field definition. YOOtheme never reads that key, so labels have
 * never rendered in the builder's dynamic-content picker — the editor sees
 * the raw meta key (`what_we_did`, `testimonial_role`) instead of the Dutch
 * label the service already defines.
 *
 * GROUND TRUTH — the installed parent theme, not documentation:
 *
 *   1. `packages/builder-source/src/Source.php:14-25` — the `FieldConfig`
 *      phpstan type is `{name?, type, args?, metadata?, extensions?}`. There
 *      is NO top-level `label` member. The old emission was not merely
 *      ignored at runtime, it was off-contract for static analysis too.
 *
 *   2. `packages/builder-wordpress-source/src/Type/SiteQueryType.php:20-33` —
 *      the canonical minimal first-party field:
 *          'site' => [
 *              'type' => 'Site',
 *              'metadata' => ['label' => trans('Site'), 'group' => trans('Global')],
 *              'extensions' => ['call' => __CLASS__ . '::resolve'],
 *          ]
 *      `metadata` sits ALONGSIDE `extensions`; it does not replace it.
 *
 *   3. `packages/builder-wordpress-acf/src/Type/FieldsType.php:36-48` — the
 *      ACF bridge, the closest analogue to this one, emits
 *      `'metadata' => ['label' => $field['label'] ?: $field['name'], 'group' => …]`
 *      for exactly the same reason: third-party custom fields surfacing in
 *      the same picker as first-party ones.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * DESIGN DECISIONS THIS TEST PINS, AND WHY.
 *
 * (a) `group` IS THE BARE PARENT TYPE NAME (`Case`, `Tool`) — not a decorated
 *     string like "Case velden". Per-type grouping is the explicit product
 *     decision: each model's custom fields collapse under their OWN heading
 *     in the picker rather than into one undifferentiated bucket. The BARE
 *     name is chosen because every first-party group in the same dropdown is
 *     a bare noun (`Site`, `Page`, `Global`, `Custom`, `Single Post`,
 *     `Archive`) and ACF passes `$field['group']['title']` through verbatim.
 *     A decorated value would read as a foreign vocabulary in a list whose
 *     other entries are all bare. `toObjectType()` already receives
 *     `$typeName` for repeater sub-type composition; this reuses it.
 *
 * (b) SUB-OBJECT (REPEATER ROW) TYPES GET NO `group` AT ALL — but DO get a
 *     `label`. A row's fields are resolved inside a `listOf` sub-type and
 *     render in the row's own context, where a heading repeating the parent
 *     type name is pure noise. Ground truth agrees: `FieldsType.php:41` emits
 *     `'group' => $isType ? $field['group']['title'] : null` — YOOtheme's own
 *     ACF bridge omits the group precisely when the fields are NOT rendering
 *     at type level. The existing signature already encodes the distinction:
 *     `toSubObjectTypes()` calls `toObjectType($subFields, $prefix)` with NO
 *     `$typeName`, so "no type name" == "not at type level" == "no group".
 *     The key is OMITTED rather than set to `''`/null, consistent with this
 *     class's omit-don't-emit-empty doctrine.
 *
 * (c) `metadata` MUST NOT DISTURB `extensions`. The resolver wiring
 *     (`withResolvers()` in YOOthemeSourcesService, hardened in d03fe88
 *     finding C-1 so only `type` + `field` cross the wire) is a separate
 *     concern that must keep working byte-for-byte. Pinned explicitly below.
 *
 * A test file by nature declares a symbol; no side effects beyond that.
 */

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use NtdstTests\Unit\TestCase;
use josworld\services\yootheme\SchemaMapper;

final class FieldMetadataTest extends TestCase
{
    private const PREFIX = '_jw_';

    private const CASE_TYPE = 'Case';

    /**
     * 1. A SCALAR FIELD'S LABEL LANDS UNDER `metadata`, NOT AT TOP LEVEL.
     *
     * This is the whole bug. The assertion is written against the parent
     * theme's `FieldConfig` shape (Source.php:14-25), so it fails
     * BEHAVIOURALLY against the old emission — the definition exists and
     * carries the right `type`, but the label is in a place YOOtheme does
     * not look.
     */
    public function test_a_scalar_field_label_is_emitted_under_metadata(): void
    {
        $mapped = SchemaMapper::toObjectType(
            ['what_we_did' => ['type' => 'textarea', 'label' => 'Wat we hebben gedaan']],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertSame(
            'Wat we hebben gedaan',
            $mapped['what_we_did']['metadata']['label'] ?? null,
            'YOOtheme reads the label from metadata.label (Source.php FieldConfig); '
                . 'a top-level label is silently ignored and the picker falls back to the raw key',
        );
    }

    /**
     * 2. THE TOP-LEVEL `label` KEY IS GONE.
     *
     * Not cosmetic: `FieldConfig` declares no top-level `label` member, so
     * leaving it behind would keep emitting an off-contract key that reads
     * as "the label is handled" to anyone inspecting the array, while the
     * picker still shows the raw meta key. Deleting it is what makes
     * metadata.label the single source of truth.
     */
    public function test_the_ignored_top_level_label_key_is_no_longer_emitted(): void
    {
        $mapped = SchemaMapper::toObjectType(
            ['what_we_did' => ['type' => 'textarea', 'label' => 'Wat we hebben gedaan']],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertArrayNotHasKey(
            'label',
            $mapped['what_we_did'],
            'a top-level label is not part of YOOtheme FieldConfig; metadata.label is the only one read',
        );
    }

    /**
     * 3. THE FIELD IS GROUPED UNDER ITS OWN PARENT TYPE NAME (decision (a)).
     *
     * Per-type grouping is the product decision: `Case` fields group under
     * `Case`, `Tool` under `Tool`. Asserted for TWO different type names so
     * a hardcoded constant cannot pass.
     */
    public function test_a_scalar_field_is_grouped_under_its_parent_type_name(): void
    {
        $caseMapped = SchemaMapper::toObjectType(
            ['client' => ['type' => 'text', 'label' => 'Klant']],
            self::PREFIX,
            'Case',
        );

        $toolMapped = SchemaMapper::toObjectType(
            ['subtitle' => ['type' => 'text', 'label' => 'Subtitel']],
            self::PREFIX,
            'Tool',
        );

        $this->assertSame(
            'Case',
            $caseMapped['client']['metadata']['group'] ?? null,
            "Case's fields group under the Case heading in the picker",
        );
        $this->assertSame(
            'Tool',
            $toolMapped['subtitle']['metadata']['group'] ?? null,
            "Tool's fields group under their OWN heading, not a shared bucket",
        );
    }

    /**
     * 4. A REPEATER FIELD CARRIES THE SAME metadata SHAPE.
     *
     * The repeater path builds its definition in a separate branch
     * (`toRepeaterFieldDefinition()`), which is exactly why it had its own
     * duplicate copy of the wrong-nesting bug. Pinned separately so fixing
     * one path cannot leave the other broken.
     */
    public function test_a_repeater_field_carries_label_and_group_under_metadata(): void
    {
        $mapped = SchemaMapper::toObjectType(
            [
                'impact' => [
                    'type' => 'repeater',
                    'label' => 'Impact',
                    'sub_fields' => [
                        'figure' => ['type' => 'text', 'label' => 'Cijfer'],
                    ],
                ],
            ],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertSame(
            ['listOf' => 'CaseImpactItem'],
            $mapped['impact']['type'],
            'the listOf reference is unchanged by the metadata fix',
        );
        $this->assertSame(
            'Impact',
            $mapped['impact']['metadata']['label'] ?? null,
            'the repeater branch needs the same metadata nesting as the scalar branch',
        );
        $this->assertSame(
            'Case',
            $mapped['impact']['metadata']['group'] ?? null,
            'a repeater groups under its parent type just like a scalar field',
        );
        $this->assertArrayNotHasKey('label', $mapped['impact']);
    }

    /**
     * 5. A REPEATER SUB-FIELD GETS A metadata LABEL BUT NO GROUP (decision (b)).
     *
     * The DENIAL half of the group rule, and the reason it is a decision
     * rather than a copy-paste: emitting a group here would put a redundant
     * `Case` heading inside every row's own field context. Mirrors
     * `FieldsType.php:41`'s `$isType ? … : null`.
     */
    public function test_a_repeater_sub_field_gets_a_metadata_label_but_no_group(): void
    {
        $types = SchemaMapper::toSubObjectTypes(
            [
                'takeaways' => [
                    'type' => 'repeater',
                    'label' => 'Take-aways',
                    'sub_fields' => [
                        'title' => ['type' => 'text', 'label' => 'Titel'],
                        'body' => ['type' => 'textarea', 'label' => 'Tekst'],
                    ],
                ],
            ],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $fields = $types['CaseTakeawaysItem']['fields'];

        $this->assertSame(
            'Titel',
            $fields['title']['metadata']['label'] ?? null,
            'sub-field labels must reach metadata.label or a row renders raw keys',
        );
        $this->assertSame('Tekst', $fields['body']['metadata']['label'] ?? null);

        $this->assertArrayNotHasKey(
            'group',
            $fields['title']['metadata'],
            "a row's fields render in the row's own context; a parent-type heading there is noise "
                . '(ground truth: FieldsType.php:41 omits the group when not at type level)',
        );
        $this->assertArrayNotHasKey('label', $fields['title']);
    }

    /**
     * 6. A FIELD WITH NO LABEL EMITS NO EMPTY metadata.label — BUT STILL GROUPS.
     *
     * Bare-string schema entries (`'title' => 'text'`, framework-legal per
     * setupSanitizers()) carry no label. Emitting `'label' => null` would
     * make the picker show an empty entry, which is worse than the raw-key
     * fallback it would replace. The group still applies: it comes from the
     * parent type, not from the field.
     */
    public function test_a_field_without_a_label_omits_the_label_key_but_keeps_the_group(): void
    {
        $mapped = SchemaMapper::toObjectType(
            ['title' => 'text'],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertSame('String', $mapped['title']['type']);
        $this->assertArrayNotHasKey(
            'label',
            $mapped['title']['metadata'],
            'no label in the schema means no label key — never an empty/null one',
        );
        $this->assertSame('Case', $mapped['title']['metadata']['group'] ?? null);
    }

    /**
     * 7. NO PARENT TYPE NAME MEANS NO `metadata.group` KEY AT ALL.
     *
     * The two-argument call path (T04's retained contract, and the call
     * `toSubObjectTypes()` makes internally). Omitting the key rather than
     * emitting `'group' => ''` keeps this class's omit-don't-emit-empty
     * doctrine intact, and is what makes decision (b) fall out structurally
     * instead of needing a second flag.
     */
    public function test_without_a_parent_type_name_no_group_key_is_emitted(): void
    {
        $mapped = SchemaMapper::toObjectType(
            ['client' => ['type' => 'text', 'label' => 'Klant']],
            self::PREFIX,
        );

        $this->assertSame('Klant', $mapped['client']['metadata']['label'] ?? null);
        $this->assertArrayNotHasKey(
            'group',
            $mapped['client']['metadata'],
            "an empty type name must omit the group key, never emit ''",
        );
    }

    /**
     * 8. metadata SITS ALONGSIDE THE TYPE — IT REPLACES NOTHING.
     *
     * Guards the d03fe88 hardening (finding C-1). `metadata` is a sibling of
     * `type`/`extensions` in YOOtheme's FieldConfig, so adding it must leave
     * every pre-existing key of the definition untouched. If a future edit
     * ever rebuilds the definition array around metadata and drops the
     * listOf or the type, this fails.
     */
    public function test_adding_metadata_leaves_the_rest_of_the_definition_untouched(): void
    {
        $mapped = SchemaMapper::toObjectType(
            [
                'client' => ['type' => 'text', 'label' => 'Klant'],
                'impact' => [
                    'type' => 'repeater',
                    'label' => 'Impact',
                    'sub_fields' => ['figure' => ['type' => 'text', 'label' => 'Cijfer']],
                ],
            ],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertSame(
            ['type', 'metadata'],
            array_keys($mapped['client']),
            'a scalar definition is exactly type + metadata — metadata is additive, not a replacement',
        );
        $this->assertSame(
            ['type', 'metadata'],
            array_keys($mapped['impact']),
            'the repeater definition keeps its listOf type and gains metadata beside it',
        );
    }

    /**
     * 9. THE DENIAL PATH IS UNCHANGED BY THE metadata FIX.
     *
     * An unmapped type is still OMITTED, not defaulted (threat 2). A
     * refactor of the definition-building block is exactly where an
     * omit-don't-default rule gets accidentally relaxed, so it is re-pinned
     * here at the point of change rather than relying on the T04 file.
     */
    public function test_unmapped_types_are_still_omitted_entirely(): void
    {
        $mapped = SchemaMapper::toObjectType(
            [
                'linked' => ['type' => 'relation', 'label' => 'Gelinkt'],
                'wormhole' => ['type' => 'wormhole', 'label' => 'Wormgat'],
                'client' => ['type' => 'text', 'label' => 'Klant'],
            ],
            self::PREFIX,
            self::CASE_TYPE,
        );

        $this->assertSame(
            ['client'],
            array_keys($mapped),
            'an unmapped type is omitted, label or no label — metadata never resurrects it',
        );
    }
}
