<?php

/**
 * SchemaMapper — pure ntdst schema -> YOOtheme/GraphQL field mapping (T04).
 *
 * SIGNATURE SHELL ONLY. Authored by the independent test-author ahead of the
 * implementation so that `SchemaMapperTest` fails for a BEHAVIOURAL reason
 * ("expected String, got not-implemented") rather than "class not found".
 * The body below is a sentinel: the implementer replaces it with the real
 * mapping and must not weaken the committed contract test.
 *
 * DELIBERATELY PURE: no WordPress, no YOOtheme, no container, no state. That
 * is what makes the type mapping unit-testable with no bootstrap (plan:
 * "a small pure mapper class so the type mapping is unit-testable without
 * WordPress or YOOtheme"). Do not introduce a WP call here — the resolution
 * half that needs WordPress is T05's `FieldResolver`, and the registration
 * half is T07's `YOOthemeSourcesService`.
 *
 * STATIC, not an instance: the class holds no state and needs no DI, and the
 * plan's call site is a bare `SchemaMapper::toObjectType(schema, prefix)`
 * inside the `source.init` closure (plan.md:39).
 *
 * `$prefix` is part of the signature because the plan names it and T05/T07
 * build against it. T04 does not pin what the mapper does with it — meta-key
 * composition is T05's contract (threat 3) — but it is accepted here so the
 * signature is stable for T06's repeater extension and T07's registration.
 *
 * A theme service file by nature declares a symbol and executes a side effect
 * (the ABSPATH guard) — same posture as the josworld-core services.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\yootheme;

defined('ABSPATH') || exit;

final class SchemaMapper
{
    /**
     * ntdst scalar type => GraphQL scalar type. Deliberately a WHITELIST, not
     * a blacklist with a fallback: an unrecognised type must be OMITTED, not
     * defaulted to 'String' (threat 2 — the bridge maps whatever the schema
     * declares; defaulting would auto-expose any future ntdst-core field type
     * through the builder as a stringified array).
     *
     * `relation`, `group`, and `repeater` are intentionally absent — they are
     * either v2 (relation/group) or handed to T06 (repeater), and their
     * absence from this map is exactly what makes them fall through to the
     * omit path below.
     *
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'text' => 'String',
        'textarea' => 'String',
        'email' => 'String',
        'select' => 'String',
        'boolean' => 'Boolean',
    ];

    /**
     * Map an ntdst model schema to YOOtheme/GraphQL field definitions.
     *
     * @param  array<string, mixed>  $schema  The model schema as ntdst stores it.
     *                                        Entries are USUALLY `['type' => ..., 'label' => ...]`
     *                                        but the framework also tolerates a bare
     *                                        string (`'title' => 'text'`) — see
     *                                        ntdst-core/api/Data.php::setupSanitizers().
     * @param  string  $prefix  The model's meta prefix (e.g. `_jw_`).
     * @param  string  $typeName  The parent object type name T07 derives from the
     *                            post type (e.g. `Case`), used to compose repeater
     *                            sub-type names as `<Type><Field>Item`. OPTIONAL
     *                            WITH A DEFAULT on purpose: T04's committed
     *                            contract calls this method with two arguments and
     *                            all eight of its tests must stay green, so the
     *                            parameter is strictly additive. Two-arg calls
     *                            (no type name) omit any repeater entirely — see
     *                            `toSubObjectTypes()`'s denial path.
     * @return array<string, array<string, mixed>> Field name => definition. Fields
     *                                             whose type has no mapping are
     *                                             OMITTED from this array entirely.
     */
    public static function toObjectType(array $schema, string $prefix, string $typeName = ''): array
    {
        $mapped = [];

        foreach ($schema as $field => $config) {
            // Bare-string entries are framework-legal (setupSanitizers()'s
            // `else` branch) — normalise to the same shape a config array
            // would have, with no label to carry through.
            if (is_string($config)) {
                $config = ['type' => $config];
            }

            if (! is_array($config)) {
                // Not a shape the framework itself produces, but stay on the
                // omit-don't-throw side rather than fatal on something
                // stranger than documented.
                continue;
            }

            $type = $config['type'] ?? null;

            if ($type === 'repeater') {
                $definition = self::toRepeaterFieldDefinition(
                    $config,
                    $prefix,
                    $typeName,
                    (string) $field,
                    $schema,
                );

                if ($definition !== null) {
                    $mapped[$field] = $definition;
                }

                continue;
            }

            if (! is_string($type) || $type === '' || ! isset(self::TYPE_MAP[$type])) {
                // Unknown, empty, missing, or non-string type: omit the key
                // entirely rather than map it to null or default it to
                // 'String'. A null-valued key still reaches YOOtheme's
                // objectType builder — omission is the only safe denial.
                continue;
            }

            $definition = [
                'type' => self::TYPE_MAP[$type],
            ];

            if (isset($config['label'])) {
                $definition['label'] = $config['label'];
            }

            $mapped[$field] = $definition;
        }

        return $mapped;
    }

    /**
     * Emit the YOOtheme object-type DEFINITIONS for a schema's repeater fields.
     *
     * A SECOND METHOD rather than a richer return from `toObjectType()`: that
     * method returns a flat `field => definition` map which T04's eight
     * committed tests index directly, so enriching its return would break them.
     * Two pure functions over the same input keeps the class stateless and
     * T04's contract intact.
     *
     * The returned config uses YOOtheme's `['fields' => …]` objectType shape,
     * ground-truthed against `builder-wordpress-toolset/src/Type/GroupType.php`
     * — T07 feeds each entry to `$source->objectType($name, $config)`.
     *
     * @param  array<string, mixed>  $schema  The model schema as ntdst stores it.
     * @param  string  $prefix  The model's meta prefix (e.g. `_jw_`).
     * @param  string  $typeName  The parent object type name (e.g. `Case`).
     * @return array<string, array<string, mixed>> Sub-type name => objectType
     *                                             config, keyed by the SAME name
     *                                             the parent field's `listOf`
     *                                             references. Empty when the
     *                                             schema declares no usable
     *                                             repeater.
     */
    public static function toSubObjectTypes(array $schema, string $prefix, string $typeName = ''): array
    {
        if ($typeName === '') {
            // No parent type name means no composable sub-type name — the
            // denial path of the optional third parameter (T04's retained
            // two-arg test). Emitting an unscoped name would let two CPTs'
            // same-named repeater fields silently collide.
            return [];
        }

        $types = [];

        foreach ($schema as $field => $config) {
            if (! is_array($config) || ($config['type'] ?? null) !== 'repeater') {
                continue;
            }

            $subFields = $config['sub_fields'] ?? null;

            if (! is_array($subFields) || $subFields === []) {
                continue;
            }

            $fields = self::toObjectType($subFields, $prefix);

            if ($fields === []) {
                // Every sub-field was unmapped — a listOf of an empty
                // sub-object type is worse than omission: it registers a
                // bindable field whose every row renders nothing. Same
                // argument as omit-don't-default, one level down.
                continue;
            }

            $typeNameForField = self::composeSubTypeName($typeName, (string) $field);

            if (! self::isEligibleSubTypeName($typeNameForField, $schema, $typeName)) {
                continue;
            }

            $types[$typeNameForField] = ['fields' => $fields];
        }

        return $types;
    }

    /**
     * Is this composed sub-type name safe to emit?
     *
     * TWO denial rules, both fail-closed, and both applied from ONE place so
     * `toObjectType()` (which emits the `listOf` REFERENCE) and
     * `toSubObjectTypes()` (which emits the DEFINITION) can never disagree
     * about a given field. That desync is the failure this helper exists to
     * make structurally impossible — see the class docblock.
     *
     * 1. ILLEGAL GRAPHQL NAME. A GraphQL type name must start with a letter
     *    or underscore and continue with letters, digits or underscores.
     *    A name that cannot be a legal identifier is omitted rather than
     *    handed to YOOtheme's schema builder to fail on later.
     *
     * 2. COLLISION. `composeSubTypeName()` is NOT injective: it splits on
     *    `[_-]+` and discards the separators, so `key_figures`, `key-figures`
     *    and `keyFigures` all compose to the same name. Emitting either one
     *    is unsafe, because YOOtheme MERGES duplicate object types silently
     *    rather than erroring — `SchemaBuilder::objectType()` appends to
     *    `$this->configs[$name][]` and `loadType()` applies every queued
     *    config through `extendType()` (packages/graphql/src/SchemaBuilder
     *    .php:220-239, :265-269). So a collision would give one repeater's
     *    rows the other repeater's fields, builder-bindable, with no error
     *    anywhere. Both partners are omitted: visibly-absent beats
     *    invisibly-merged, the same doctrine as omit-don't-default.
     *
     * @param array<string, mixed> $schema
     */
    private static function isEligibleSubTypeName(
        string $composed,
        array $schema,
        string $typeName,
    ): bool {
        if (preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/', $composed) !== 1) {
            return false;
        }

        $timesComposed = 0;

        foreach (array_keys($schema) as $candidate) {
            if (self::composeSubTypeName($typeName, (string) $candidate) === $composed) {
                ++$timesComposed;
            }
        }

        return $timesComposed === 1;
    }

    /**
     * Build the `listOf` field definition for a single repeater entry, or
     * null if it must be omitted (no parent type name, no usable sub_fields,
     * or every sub_field unmapped). Mirrors `toSubObjectTypes()`'s denial
     * rules exactly — both methods must agree on which repeaters are usable,
     * since a `listOf => 'X'` reference with no `X` definition emitted would
     * be a dangling type name T07 tries to register.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $schema  The FULL sibling schema, needed
     *                                        only so the collision half of
     *                                        isEligibleSubTypeName() can see
     *                                        every field that competes for
     *                                        the same composed type name.
     * @return array<string, mixed>|null
     */
    private static function toRepeaterFieldDefinition(
        array $config,
        string $prefix,
        string $typeName,
        string $field,
        array $schema,
    ): ?array {
        if ($typeName === '') {
            return null;
        }

        $subFields = $config['sub_fields'] ?? null;

        if (! is_array($subFields) || $subFields === []) {
            return null;
        }

        if (self::toObjectType($subFields, $prefix) === []) {
            return null;
        }

        $composed = self::composeSubTypeName($typeName, $field);

        // The SAME eligibility rule toSubObjectTypes() applies before
        // emitting a definition. Both sides ask one helper, so a reference
        // can never be emitted for a name whose definition was denied (or
        // vice versa) — see isEligibleSubTypeName() for why that desync is
        // the highest-consequence bug available in this class.
        if (! self::isEligibleSubTypeName($composed, $schema, $typeName)) {
            return null;
        }

        $definition = [
            'type' => ['listOf' => $composed],
        ];

        if (isset($config['label'])) {
            $definition['label'] = $config['label'];
        }

        return $definition;
    }

    /**
     * Compose a repeater sub-object type name: `<TypeName><Field>Item`,
     * UpperCamelCase. `_`/`-` are treated as word boundaries, matching
     * YOOtheme's own `Str::camelCase()` convention (packages/utils/src/
     * Str.php:182) WITHOUT importing it — this class stays pure so it is
     * unit-testable without WordPress or YOOtheme (see class docblock).
     *
     * Parent-type scoping is deliberate: without it, `Tool.benefits` and
     * `Case.benefits` would silently collapse into one shared sub-type name
     * and give one CPT the other's row shape.
     */
    private static function composeSubTypeName(string $typeName, string $field): string
    {
        return $typeName . self::upperCamelCase($field) . 'Item';
    }

    /**
     * UpperCamelCase a snake_case/kebab-case field name: `key_figures` ->
     * `KeyFigures`. `_` and `-` are word boundaries, not literal characters —
     * left as either would produce an illegal GraphQL type name.
     */
    private static function upperCamelCase(string $value): string
    {
        $words = preg_split('/[_\-]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            $words = [];
        }

        return implode('', array_map('ucfirst', $words));
    }
}
