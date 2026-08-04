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
     * 3. AN UNGROUPED FIELD FALLS BACK TO THE PARENT TYPE NAME (decision (a),
     *    as amended in T09).
     *
     * CONTRACT CORRECTION, not a weakening. This test previously asserted
     * that EVERY field groups under its bare parent type name, because that
     * was the whole grouping rule when it was written (T08). T09 supersedes
     * that rule: a field listed in the model's `field_groups` now groups
     * under its GROUP TITLE (see the field-group tests below), and the parent
     * type name survives only as the FALLBACK for a field no group claims.
     *
     * What is asserted here is therefore the still-true half of the original
     * contract — the fallback — and it is still asserted for TWO different
     * type names so a hardcoded constant cannot pass. The superseded half is
     * re-pinned, in its corrected form, by
     * `test_a_grouped_field_is_grouped_under_its_field_group_title()`.
     *
     * No `field_groups` argument is passed at all here, which is exactly the
     * ungrouped case: nothing claims `client`/`subtitle`, so each falls back.
     */
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
            'a repeater groups exactly like a scalar field — here with no field_groups '
                . 'passed, so both take the parent-type fallback',
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

    /**
     * ═════════════════════════════════════════════════════════════════════
     * T09 — GROUP BY THE MODEL'S FIELD GROUPS, NOT BY POST TYPE.
     *
     * The product decision T09 supersedes T08's decision (a) with: the picker
     * groups by the model's own `field_groups` (the wp-admin metabox tabs) so
     * its structure matches how editors already think about the content.
     *
     * The heading is the group's `title` VERBATIM and BARE (`Klant & intro`) —
     * NOT the group KEY (`intro`), and NOT prefixed with the type name
     * (`Case — Klant & intro`). Explicit user decision. Ground truth agrees
     * that a bare title is the house style: `builder-wordpress-acf/src/Type/
     * FieldsType.php:41` emits `$field['group']['title']` verbatim.
     * ═════════════════════════════════════════════════════════════════════
     */

    /**
     * 10. A GROUPED FIELD USES ITS GROUP'S TITLE — NOT THE KEY, NOT THE TYPE.
     *
     * The core T09 contract. Asserted across THREE fields in TWO different
     * groups so that neither a hardcoded string nor "always the first group"
     * can pass. The negative assertions are the point: `intro` (the group KEY)
     * and `Case` (the superseded type-name rule) are both explicitly refused,
     * so an implementation that inverts the map wrongly — or one that simply
     * never changed — fails behaviourally rather than subtly.
     */
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

    /**
     * 11. THE TITLE IS BARE — NEVER PREFIXED WITH THE TYPE NAME.
     *
     * The user's explicit choice, pinned separately from test 10 because it
     * is the decision most likely to be "improved" by a later well-meaning
     * edit ("Case — Klant & intro" reads more informative in isolation). It
     * is refused: every first-party group in the same dropdown is a bare noun,
     * and ACF passes its group title through verbatim.
     */
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

    /**
     * 12. A FIELD NO GROUP CLAIMS FALLS BACK TO THE TYPE NAME — MIXED MODEL.
     *
     * The fallback asserted in the SAME call as grouped siblings, which test 3
     * cannot do (it passes no groups at all). This is the shape a real model
     * takes the moment a field is added to `getFields()` without being added
     * to `getFieldGroups()` — verified as the ONLY way this arises today: all
     * four josworld models currently group 100% of their fields (Case 17/17,
     * Team 3/3, Vacature 1/1, Tool 4/4).
     *
     * Fallback rather than omission: an omitted group renders the field with
     * NO heading at all, orphaned above the first subheader in the picker.
     */
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

    /**
     * 13. A MODEL WITH NO field_groups STILL EMITS A VALID GROUP.
     *
     * The backwards-compatibility denial path: `field_groups` is OPTIONAL, and
     * a model registered without it (or with an empty/garbage value) must keep
     * working exactly as it did before T09 — every field under the type name.
     * No crash, no null, no empty string, and never a missing key that would
     * orphan the field.
     *
     * The malformed variants matter because `field_groups` reaches this class
     * from `apply_filters("ntdst/{$name}/field_groups", …)` (ntdst-core
     * `Data.php`), so ANY plugin can rewrite it to anything at all.
     */
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

    /**
     * 14. A GROUP TITLE THAT IS NOT A USABLE STRING IS REFUSED.
     *
     * `field_groups` is filter-controlled third-party input (see test 13), so
     * a `title` may be an array, an object, null, or an empty string. Emitting
     * any of those into the builder's schema config is the failure this
     * refuses: an array reaches YOOtheme's serialiser as a non-string group,
     * and an empty string renders as a blank heading — strictly worse than the
     * type-name fallback it would replace.
     *
     * Same doctrine as this class's omit-don't-default type handling, one
     * level down: an unusable value falls back, it never propagates.
     */
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

    /**
     * 15. A FIELD IN MULTIPLE GROUPS TAKES THE FIRST DECLARED — DETERMINISTIC.
     *
     * `field_groups` does not forbid a field appearing twice, and a filter can
     * introduce it. The rule is FIRST DECLARATION WINS: declaration order is
     * the only ordering the data carries, it matches the metabox tab a field
     * renders in first, and it is stable under repeated calls. Pinned so the
     * behaviour cannot silently become "last wins" (the natural result of a
     * naive overwriting loop) or, worse, array-order-dependent.
     */
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

    /**
     * 16. REPEATER SUB-TYPE FIELDS STILL CARRY NO GROUP (decision (b) HOLDS).
     *
     * The T08 rule T09 must not regress, re-pinned WITH field_groups in play
     * because that is the new way to break it: a naive lookup keyed only by
     * field name would match a row sub-field called `title` against a group
     * listing `title`, and start emitting headings inside repeater rows.
     *
     * The fixture is built to trigger exactly that — a group claiming `title`
     * and `body`, which are also the sub-field names. Ground truth:
     * `FieldsType.php:41` omits the group when not at type level.
     */
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
