<?php

declare(strict_types=1);

namespace NtdstTests\Unit\Yootheme;

use josworld\services\yootheme\SchemaMapper;
use NtdstTests\Unit\TestCase;

require_once dirname(__DIR__, 3)
    . '/app/content/themes/josworld/services/yootheme/SchemaMapper.php';

/**
 * Unit tier: the CLOSING CHECKS for Cluster B1 review findings C-1 and I-1.
 *
 * WHY THIS FILE EXISTS. Two independent reviewers, working separately,
 * converged on the same defect and the controller reproduced it by
 * execution before accepting it:
 *
 *     key_figures -> CaseKeyFiguresItem
 *     keyFigures  -> CaseKeyFiguresItem     <- same name
 *
 * `upperCamelCase()` splits on `[_-]+` and discards the separators, so the
 * mapping from field name to type name is NOT injective. `toSubObjectTypes()`
 * then wrote into `$types[$name]` with no occupancy check, so the second
 * repeater silently overwrote the first. Measured before the fix:
 * `refs=2 defs=1` — two `listOf` references, one emitted definition, and the
 * first repeater's sub-fields simply gone.
 *
 * WHY IT IS WORSE THAN A NAME CLASH — verified in YOOtheme's own source, not
 * assumed. `SchemaBuilder::objectType()` (packages/graphql/src/SchemaBuilder
 * .php:220-239) APPENDS to `$this->configs[$name][]`, and `loadType()`
 * (:265-269) applies every queued config through `extendType()`. A duplicate
 * type name is therefore MERGED SILENTLY — no InvariantViolation, no warning,
 * no last-write-wins error. One CPT's repeater rows gain another CPT's
 * fields, and per the plan's threat 2 those merged fields become
 * builder-bindable. That is "one CPT rendering another's data", reached
 * through a path every existing guard missed.
 *
 * WHY 18 GREEN TESTS MISSED IT (finding I-1, and the more important half):
 * every existing test asserted ONE method's output against a literal. None
 * asserted the RELATIONSHIP between the two methods. The invariant test
 * below states it directly — every `listOf` reference must have exactly one
 * emitted definition — which subsumes this specific collision and any future
 * divergence between the two methods.
 *
 * NOT reachable from josworld's four live CPTs today (no separator ambiguity,
 * no prefix overlap among Case/Team/Vacature/Tool). It is a latent trap that
 * fires the day someone adds a repeater — and the security review raised its
 * priority by establishing the schema is filter-influenced
 * (`apply_filters("ntdst/{$name}/fields", ...)` at ntdst-core Data.php:1526),
 * so field names are not strictly a developer-authored literal.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class SubTypeNameCollisionTest extends TestCase
{
    private const PREFIX = '_jw_';

    private const PARENT = 'Case';

    /**
     * THE INVARIANT (finding I-1). This is the assertion whose absence let
     * the collision through: for ANY schema, every `listOf` type name that
     * `toObjectType()` references must have exactly one definition emitted
     * by `toSubObjectTypes()` — no dangling reference, no orphan definition.
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

    /**
     * The collision must be resolved FAIL-CLOSED. When two field names
     * compose to one type name, the safe denial is the one this class
     * already uses everywhere else: omit BOTH, so the fields are
     * visibly-absent rather than invisibly-merged. Silently keeping one is
     * the failure mode that renders the wrong CPT's data.
     */
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

    /**
     * A control alongside the above: a NON-colliding repeater in the same
     * schema as a colliding pair must still map. Without this, "omit both"
     * could be satisfied by omitting everything.
     */
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
     * Finding C-1's second half: a composed name that is not a legal GraphQL
     * identifier must be omitted. A GraphQL name must start with a letter or
     * underscore and continue with letters, digits or underscores. The
     * helper's own docblock claims to prevent illegal names, but `2fast`
     * produced `Case2fastItem` (legal, as it happens, because the parent
     * prefixes it) while an EMPTY parent name would not. Pin the rule at the
     * composed name, where it is decidable.
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
