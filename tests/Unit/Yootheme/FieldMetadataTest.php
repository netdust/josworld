<?php

/**
 * Field `label`/`group` must be emitted under `metadata` — the only
 * nesting YOOtheme reads (FieldConfig in builder-source Source.php has no
 * top-level `label`; one there is silently ignored and the picker shows
 * raw meta keys). Grouping rules pinned here: a field claimed by a field
 * group takes that group's BARE title; an unclaimed field falls back to
 * the bare parent type name; repeater sub-fields get a label but never a
 * group — rows render in their own context, mirroring the ACF bridge
 * (FieldsType.php:41). `metadata` is additive and must not disturb
 * `type`/`extensions`.
 */

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use NtdstTests\Unit\TestCase;
use josworld\services\yootheme\SchemaMapper;

final class FieldMetadataTest extends TestCase
{
    private const PREFIX = '_jw_';

    private const CASE_TYPE = 'Case';

    // The core bug: the label must land under metadata, where YOOtheme
    // actually looks.
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

    // FieldConfig declares no top-level label; deleting it makes
    // metadata.label the single source of truth.
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

    // The ungrouped-fallback case (no field_groups passed). Two different
    // type names so a hardcoded constant cannot pass.
    public function test_an_ungrouped_field_falls_back_to_its_parent_type_name(): void
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
            'a field claimed by no field group still needs a heading; it falls back to its own type',
        );
        $this->assertSame(
            'Tool',
            $toolMapped['subtitle']['metadata']['group'] ?? null,
            "the fallback is the field's OWN type, not a shared bucket",
        );
    }

    // The repeater branch builds its definition separately and had its own
    // copy of the nesting bug — pinned so fixing one path can't miss the other.
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
            'a repeater groups exactly like a scalar field — here with no field_groups '
                . 'passed, so both take the parent-type fallback',
        );
        $this->assertArrayNotHasKey('label', $mapped['impact']);
    }

    // Denial half of the group rule: a parent-type heading inside a row's
    // own field context is noise.
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

    // Bare-string schema entries ('title' => 'text') are framework-legal
    // and carry no label: emit no label key, never null. The group still
    // applies — it comes from the parent type, not the field.
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

    // The two-argument path toSubObjectTypes() uses internally: omit the
    // group key entirely, never emit '' — the no-group-in-rows rule falls
    // out of this structurally.
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

    // metadata is additive: a future rebuild of the definition array that
    // drops the type or listOf fails here.
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

    // An unmapped type stays omitted, not defaulted — metadata never
    // resurrects it.
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

    // ── Grouping by the model's field_groups: the heading is the group's
    // title VERBATIM and BARE — never the group key, never type-prefixed. ──

    // Three fields, two groups: neither a hardcoded string nor "always the
    // first group" can pass; the group key and the type name are refused.
    public function test_a_grouped_field_is_grouped_under_its_field_group_title(): void
    {
        $mapped = SchemaMapper::toObjectType(
            [
                'client' => ['type' => 'text', 'label' => 'Klant'],
                'tagline' => ['type' => 'text', 'label' => 'Tagline'],
                'testimonial_quote' => ['type' => 'textarea', 'label' => 'Quote'],
            ],
            self::PREFIX,
            self::CASE_TYPE,
            [
                'intro' => [
                    'title' => 'Klant & intro',
                    'fields' => ['client', 'tagline'],
                ],
                'testimonial' => [
                    'title' => 'Testimonial',
                    'fields' => ['testimonial_quote'],
                ],
            ],
        );

        $this->assertSame(
            'Klant & intro',
            $mapped['client']['metadata']['group'] ?? null,
            "the heading is the group's TITLE verbatim — the editor-facing string, "
                . 'never the internal group key',
        );
        $this->assertSame(
            'Klant & intro',
            $mapped['tagline']['metadata']['group'] ?? null,
            'a second field in the same group lands under the same heading',
        );
        $this->assertSame(
            'Testimonial',
            $mapped['testimonial_quote']['metadata']['group'] ?? null,
            'a field in a DIFFERENT group gets that group title, not the first one',
        );

        $this->assertNotSame(
            'intro',
            $mapped['client']['metadata']['group'] ?? null,
            'the group KEY is an internal identifier and must never reach the picker',
        );
        $this->assertNotSame(
            self::CASE_TYPE,
            $mapped['client']['metadata']['group'] ?? null,
            'the type name is superseded for grouped fields (T08 decision (a) amended by T09)',
        );
    }

    // Explicit user decision, pinned separately because a well-meaning
    // edit is most likely to "improve" it with a type prefix.
    public function test_the_group_title_is_bare_and_never_prefixed_with_the_type_name(): void
    {
        $mapped = SchemaMapper::toObjectType(
            ['client' => ['type' => 'text', 'label' => 'Klant']],
            self::PREFIX,
            self::CASE_TYPE,
            ['intro' => ['title' => 'Klant & intro', 'fields' => ['client']]],
        );

        $group = $mapped['client']['metadata']['group'] ?? null;

        $this->assertSame('Klant & intro', $group);
        $this->assertStringNotContainsString(
            self::CASE_TYPE,
            (string) $group,
            'the type name must not be decorated onto the group title in any form',
        );
    }

    // Fallback asserted in the SAME call as grouped siblings. Fallback
    // rather than omission: an omitted group orphans the field above the
    // picker's first subheader.
    public function test_a_field_claimed_by_no_group_falls_back_to_the_type_name(): void
    {
        $mapped = SchemaMapper::toObjectType(
            [
                'client' => ['type' => 'text', 'label' => 'Klant'],
                'orphan' => ['type' => 'text', 'label' => 'Wees'],
            ],
            self::PREFIX,
            self::CASE_TYPE,
            ['intro' => ['title' => 'Klant & intro', 'fields' => ['client']]],
        );

        $this->assertSame('Klant & intro', $mapped['client']['metadata']['group'] ?? null);
        $this->assertSame(
            self::CASE_TYPE,
            $mapped['orphan']['metadata']['group'] ?? null,
            'a field no group claims still needs a heading — it must not be left group-less',
        );
    }

    // field_groups is optional AND filter-controlled
    // (`ntdst/{$name}/field_groups`), so malformed variants matter: any
    // plugin can rewrite it to anything.
    public function test_a_model_with_no_field_groups_still_groups_under_the_type_name(): void
    {
        foreach ([[], ['intro' => []], ['intro' => ['fields' => ['client']]]] as $i => $groups) {
            $mapped = SchemaMapper::toObjectType(
                ['client' => ['type' => 'text', 'label' => 'Klant']],
                self::PREFIX,
                self::CASE_TYPE,
                $groups,
            );

            $this->assertSame(
                self::CASE_TYPE,
                $mapped['client']['metadata']['group'] ?? null,
                "variant {$i}: an absent, empty or title-less field group must fall back "
                    . 'cleanly to the type name, never to null or an empty heading',
            );
        }
    }

    // A filter can make title an array/null/blank; an unusable value falls
    // back to the type name, it never propagates into the schema config.
    public function test_an_unusable_group_title_falls_back_to_the_type_name(): void
    {
        foreach ([['x'], null, '', '   ', 123, true] as $i => $title) {
            $mapped = SchemaMapper::toObjectType(
                ['client' => ['type' => 'text', 'label' => 'Klant']],
                self::PREFIX,
                self::CASE_TYPE,
                ['intro' => ['title' => $title, 'fields' => ['client']]],
            );

            $group = $mapped['client']['metadata']['group'] ?? null;

            $this->assertSame(
                self::CASE_TYPE,
                $group,
                "variant {$i}: a non-string/blank group title must not reach the picker",
            );
            $this->assertIsString($group, "variant {$i}: the emitted group is always a string");
        }
    }

    // First declaration wins — pinned so a naive overwriting loop cannot
    // silently make it "last wins" or array-order-dependent.
    public function test_a_field_in_two_groups_deterministically_takes_the_first(): void
    {
        $groups = [
            'intro' => ['title' => 'Klant & intro', 'fields' => ['client']],
            'impact' => ['title' => 'Impact', 'fields' => ['client']],
        ];

        for ($run = 0; $run < 3; $run++) {
            $mapped = SchemaMapper::toObjectType(
                ['client' => ['type' => 'text', 'label' => 'Klant']],
                self::PREFIX,
                self::CASE_TYPE,
                $groups,
            );

            $this->assertSame(
                'Klant & intro',
                $mapped['client']['metadata']['group'] ?? null,
                "run {$run}: first declaration wins, stably across calls",
            );
        }
    }

    // A naive lookup keyed only by field name would match a row sub-field
    // called `title` against a group listing `title` — the fixture
    // triggers exactly that.
    public function test_repeater_sub_fields_carry_no_group_even_when_a_group_claims_the_name(): void
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
            ['takeaways' => ['title' => 'Take-aways', 'fields' => ['takeaways', 'title', 'body']]],
        );

        $fields = $types['CaseTakeawaysItem']['fields'];

        $this->assertSame('Titel', $fields['title']['metadata']['label'] ?? null);
        $this->assertArrayNotHasKey(
            'group',
            $fields['title']['metadata'],
            'a row sub-field named like a grouped top-level field must NOT inherit that '
                . "group — a row's fields render in the row's own context",
        );
        $this->assertArrayNotHasKey('group', $fields['body']['metadata']);
    }
}
