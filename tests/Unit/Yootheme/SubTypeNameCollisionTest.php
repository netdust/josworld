<?php

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use josworld\services\yootheme\SchemaMapper;
use NtdstTests\Unit\TestCase;

require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';

/**
 * `upperCamelCase()` discards `[_-]+` separators, so field name -> type
 * name is NOT injective: `key_figures` and `keyFigures` compose to the
 * same sub-type name. YOOtheme's SchemaBuilder MERGES duplicate type names
 * silently (objectType() appends configs — no error), so one CPT's
 * repeater rows would gain another CPT's builder-bindable fields.
 *
 * Not reachable from the four live CPTs today, but the schema is
 * filter-influenced (`ntdst/{$name}/fields`), so field names are not
 * strictly developer-authored literals.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */
final class SubTypeNameCollisionTest extends TestCase
{
    private const PREFIX = '_jw_';

    private const PARENT = 'Case';

    /**
     * The invariant: every `listOf` type name that `toObjectType()`
     * references must have exactly one definition emitted by
     * `toSubObjectTypes()` — no dangling reference, no orphan definition.
     *
     * @dataProvider collidingSchemaProvider
     *
     * @param array<string, mixed> $schema
     */
    public function test_every_listof_reference_has_exactly_one_emitted_sub_type(
        array $schema,
        string $why,
    ): void {
        $refs = SchemaMapper::toObjectType($schema, self::PREFIX, self::PARENT);
        $defs = SchemaMapper::toSubObjectTypes($schema, self::PREFIX, self::PARENT);

        $referenced = [];
        foreach ($refs as $definition) {
            if (isset($definition['type']['listOf'])) {
                $referenced[] = $definition['type']['listOf'];
            }
        }

        sort($referenced);
        $defined = array_keys($defs);
        sort($defined);

        $this->assertSame(
            $defined,
            $referenced,
            "every listOf reference must have exactly one emitted sub-type definition ({$why}). "
            . 'A dangling reference means YOOtheme gets a field pointing at a type nobody registered; '
            . 'an orphan definition means a registered type nothing can bind. Both desync the two methods.',
        );
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public function collidingSchemaProvider(): array
    {
        $rowA = ['sub_fields' => ['figure' => ['type' => 'text']], 'type' => 'repeater'];
        $rowB = ['sub_fields' => ['quote' => ['type' => 'text']], 'type' => 'repeater'];

        return [
            'underscore vs camelCase' => [
                ['key_figures' => $rowA, 'keyFigures' => $rowB],
                'key_figures and keyFigures both camel-case to KeyFigures',
            ],
            'hyphen vs underscore' => [
                ['key-figures' => $rowA, 'key_figures' => $rowB],
                'the separator is discarded, so both yield KeyFigures',
            ],
            'repeated separators' => [
                ['key__figures' => $rowA, 'key_figures' => $rowB],
                'a run of separators collapses to one word boundary',
            ],
            'no collision — control' => [
                ['impact' => $rowA, 'takeaways' => $rowB],
                'two genuinely distinct names must BOTH survive; this control '
                . 'proves the fix omits only real collisions, not everything',
            ],
        ];
    }

    // Fail-closed: when two names compose to one type name, omit BOTH —
    // visibly-absent beats invisibly-merged.
    public function test_colliding_repeaters_are_both_omitted_not_silently_merged(): void
    {
        $schema = [
            'key_figures' => ['type' => 'repeater', 'sub_fields' => ['figure' => ['type' => 'text']]],
            'keyFigures' => ['type' => 'repeater', 'sub_fields' => ['quote' => ['type' => 'text']]],
        ];

        $refs = SchemaMapper::toObjectType($schema, self::PREFIX, self::PARENT);
        $defs = SchemaMapper::toSubObjectTypes($schema, self::PREFIX, self::PARENT);

        $this->assertArrayNotHasKey('key_figures', $refs, 'a colliding repeater must be omitted');
        $this->assertArrayNotHasKey('keyFigures', $refs, 'its collision partner must be omitted too');
        $this->assertSame([], $defs, 'no sub-type definition may be emitted for a collided name');
    }

    // Control: a non-colliding repeater in the same schema must still
    // map — otherwise "omit both" could be satisfied by omitting everything.
    public function test_a_non_colliding_repeater_survives_alongside_a_collision(): void
    {
        $schema = [
            'key_figures' => ['type' => 'repeater', 'sub_fields' => ['figure' => ['type' => 'text']]],
            'keyFigures' => ['type' => 'repeater', 'sub_fields' => ['quote' => ['type' => 'text']]],
            'impact' => ['type' => 'repeater', 'sub_fields' => ['figure' => ['type' => 'text']]],
        ];

        $refs = SchemaMapper::toObjectType($schema, self::PREFIX, self::PARENT);
        $defs = SchemaMapper::toSubObjectTypes($schema, self::PREFIX, self::PARENT);

        $this->assertArrayHasKey('impact', $refs, 'an unaffected repeater must still map');
        $this->assertSame(['CaseImpactItem'], array_keys($defs));
    }

    /**
     * A composed name that is not a legal GraphQL identifier must be
     * omitted. Pinned at the COMPOSED name, where it is decidable — a
     * parent prefix can legalize `2fast`, an empty parent cannot.
     *
     * @dataProvider illegalNameProvider
     */
    public function test_a_field_yielding_an_illegal_graphql_type_name_is_omitted(
        string $fieldName,
        string $why,
    ): void {
        $schema = [
            $fieldName => ['type' => 'repeater', 'sub_fields' => ['figure' => ['type' => 'text']]],
        ];

        $defs = SchemaMapper::toSubObjectTypes($schema, self::PREFIX, '');

        $this->assertSame(
            [],
            $defs,
            "a field composing to an illegal GraphQL type name must be omitted ({$why})",
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function illegalNameProvider(): array
    {
        return [
            'leading digit with no parent' => ['2fast', '2fastItem starts with a digit'],
            'empty field name' => ['', 'composes to bare Item, indistinguishable from a real type'],
            'separators only' => ['___', 'every character is discarded by the split'],
        ];
    }
}
