<?php

/**
 * YOOthemeSourcesService — the REGISTRATION half of the YOOtheme bridge (T07).
 *
 * SIGNATURE SHELL ONLY. Authored by the independent test-author ahead of the
 * implementation so that `SourcesServiceTest` fails for a BEHAVIOURAL reason
 * ("expected the Case type, got nothing") rather than "class not found". Every
 * body below is a sentinel; the implementer replaces them with the real
 * collection/emission/registration logic and must not weaken the committed
 * contract test.
 *
 * THE THIRD SIBLING. `SchemaMapper` (T04/T06) is the TYPE half and is pure.
 * `FieldResolver` (T05) is the VALUE half and reads WordPress meta. This class
 * is the REGISTRATION half: it learns which models exist, composes the two
 * others into YOOtheme object-type configs, and hands them to YOOtheme. It is
 * the only one of the three that touches YOOtheme at all.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * WHY THREE MEMBERS AND NOT ONE — the shape the contract test pins.
 *
 *   collect(string $name, array $config): void
 *       The `ntdst/model/registered` listener (ntdst-core `Data.php:1555`
 *       fires `do_action('ntdst/model/registered', $name, $config)`). This is
 *       the CHOKEPOINT where both security decisions are made: threat 4's
 *       `_jw_` boundary and threat 2's `ntdst/yootheme/exclude_fields`
 *       filter. The filter lives here rather than in `SchemaMapper` because
 *       the mapper is deliberately pure and must not call `apply_filters()`.
 *
 *   buildTypes(): array
 *       PURE emission of `typeName => objectType config` for everything
 *       collected so far, including repeater sub-types. Pure so the
 *       white-page rule, the type naming and the field mapping are all
 *       assertable against real emitted data rather than a source grep.
 *
 *   register(): void
 *       The only YOOtheme-coupled member — the `init` callback carrying the
 *       mandatory `function_exists('YOOtheme\app')` guard and the
 *       `Event::on('source.init', …, -10)` call.
 *
 * The split is not decomposition for its own sake: `YOOtheme\Event` is a
 * vendor class inside a GITIGNORED theme (`.gitignore:138` ignores
 * `app/content/themes/*` and re-includes only `josworld/`), so a design whose
 * only observable surface is `Event::on` could only be tested where the
 * licensed parent theme happens to be installed. Keeping the
 * high-consequence contract — WHAT is emitted, and in what FORM — in
 * vendor-free code is what makes it testable in CI.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * THE WHITE-PAGE RULE — non-negotiable, and the reason resolvers are
 * standalone functions rather than methods of this class.
 *
 * YOOtheme resolves a field through its `@call` directive, whose `func`
 * argument is declared `Type::string()`
 * (`yootheme/packages/graphql/src/Directive/CallDirective.php:17-27`), and
 * that value is serialised into the builder's schema config. A `Closure`, a
 * `[$this, 'method']` pair, or a `__NAMESPACE__`-composed value that fails to
 * resolve at serialise time does NOT survive the round-trip, and the failure
 * mode is not an exception — it is a WHITE PAGE in the Customizer. The
 * framework's golden path names this the single most painful failure of the
 * integration (`ntdst-patterns/golden-paths/yootheme-integration.md:9`,
 * :110, :185).
 *
 * So every `'func' =>` this class emits MUST be a LITERAL fully-qualified
 * string naming a standalone function that genuinely exists — which is
 * exactly why `FieldResolver::resolve()` is static (see its docblock): such a
 * standalone function can call it with no instance to construct, no container
 * lookup, and no bootstrap ordering to get wrong.
 *
 * A theme service file by nature declares symbols and executes a side effect
 * (the ABSPATH guard) — same posture as SchemaMapper and FieldResolver.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\yootheme;

defined('ABSPATH') || exit;

final class YOOthemeSourcesService
{
    /**
     * The josworld meta-prefix boundary (threat 4). Literal, matching
     * `JOSWORLD_CORE_META_PREFIX` (`josworld-core.php:32`) — not read from
     * the constant because that constant is defined by the mu-plugin, which
     * this unit-tested class must not require just to compare a string.
     */
    private const JW_PREFIX = '_jw_';

    /**
     * Accepted models, keyed by the DERIVED type name (`case` -> `Case`).
     * Accumulating, not snapshotting — see collect()'s docblock.
     *
     * @var array<string, array{schema: array<string, mixed>, prefix: string}>
     */
    private array $models = [];

    /**
     * Collect one registered ntdst model.
     *
     * The `ntdst/model/registered` listener (`Data.php:1555`). Accumulating,
     * not snapshotting: models arrive over the life of the `init` sequence,
     * and T03 proved a listener attached at theme-`functions.php` top level
     * observes all four. Reflection over `NTDST_Data_Manager::$models` is NOT
     * an option here — it is `protected static` (`Data.php:1511`) and the
     * reference implementation's instance reflection was ground-truthed FALSE
     * for josworld (plan.md:45-57).
     *
     * BOTH security decisions are made HERE, at the chokepoint:
     *
     *   - THREAT 4: a model whose `meta_prefix` is not exactly `_jw_` is
     *     refused outright (fail-closed on a missing key too — an absent
     *     `meta_prefix` is NOT treated as josworld's).
     *   - THREAT 2: the `ntdst/yootheme/exclude_fields` filter is applied to
     *     the schema before it is stored, so every downstream emission path
     *     (scalar fields AND repeater sub-fields, both of which
     *     `SchemaMapper` walks from the SAME `$schema` array) honours the
     *     exclusion structurally rather than by remembering to check twice.
     *     `SchemaMapper` stays pure and never calls `apply_filters()`.
     *
     * @param  string  $name  The model / post-type slug (`case`, `team`).
     * @param  array<string, mixed>  $config  The registration config, carrying
     *                                        at least `fields` (the schema)
     *                                        and `meta_prefix`.
     */
    public function collect(string $name, array $config): void
    {
        $prefix = $config['meta_prefix'] ?? '';

        if (! is_string($prefix) || $prefix !== self::JW_PREFIX) {
            return;
        }

        $schema = $config['fields'] ?? [];

        if (! is_array($schema) || $schema === []) {
            return;
        }

        $excluded = apply_filters('ntdst/yootheme/exclude_fields', [], $name);

        $schema = self::excludeFields($schema, is_array($excluded) ? $excluded : []);

        $typeName = ucfirst($name);

        $this->models[$typeName] = [
            'schema' => $schema,
            'prefix' => $prefix,
        ];
    }

    /**
     * Remove excluded field names from a schema, including inside every
     * repeater's `sub_fields` — a second, independent emission path
     * (SchemaMapper::toSubObjectTypes()) that must honour the same
     * exclusion or the field leaks back in via every grid row.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<mixed>  $excluded  Whatever the `ntdst/yootheme/exclude_fields`
     *                                  filter returns — a caller-supplied
     *                                  `apply_filters()` result, so no key
     *                                  shape is assumed beyond being an array.
     * @return array<string, mixed>
     */
    private static function excludeFields(array $schema, array $excluded): array
    {
        if ($excluded === []) {
            return $schema;
        }

        $excludedNames = array_map('strval', $excluded);

        foreach ($excludedNames as $name) {
            unset($schema[$name]);
        }

        foreach ($schema as $field => $config) {
            if (! is_array($config) || ($config['type'] ?? null) !== 'repeater') {
                continue;
            }

            $subFields = $config['sub_fields'] ?? null;

            if (! is_array($subFields)) {
                continue;
            }

            foreach ($excludedNames as $name) {
                unset($subFields[$name]);
            }

            $config['sub_fields'] = $subFields;
            $schema[$field] = $config;
        }

        return $schema;
    }

    /**
     * Emit the YOOtheme object-type configs for everything collected, as ONE
     * FLAT `name => config` map — parent types AND repeater sub-types share
     * this single namespace.
     *
     * FLAT IS FORCED, NOT STYLISTIC (contract correction, design note (e)):
     * `SchemaBuilder::objectType(string $name, $config)` is the ONLY
     * registration primitive YOOtheme offers, and it must be called for
     * parent types and sub-object types alike — a field typed
     * `['listOf' => 'CaseImpactItem']` is a dangling reference unless
     * `CaseImpactItem` was itself registered through that same call. So this
     * is inherently the shape `register()` hands the `source.init` listener;
     * `buildTypes()` returns exactly that, unlike `SchemaMapper`'s pure
     * two-method split, which does NOT transfer here (SchemaMapper's two
     * outputs differ in shape; this class is the registrar and merging them
     * into the one thing `objectType()` consumes is its job).
     *
     * PURE otherwise: composes `SchemaMapper::toObjectType()` /
     * `::toSubObjectTypes()` (the TYPE half, T04/T06) with the literal
     * standalone resolver functions declared below (the VALUE half, via
     * T05's `FieldResolver::resolve()`) — see the class docblock's
     * white-page rule for why every `extensions.call.func` MUST be a
     * literal string and never a Closure or `[$this, 'method']` pair.
     *
     * @return array<string, array<string, mixed>> Type name => objectType
     *                                             config. Every `listOf`
     *                                             reference emitted anywhere
     *                                             in this map resolves to a
     *                                             definition within the SAME
     *                                             map — no dangling
     *                                             references (Cluster B1
     *                                             finding C-1).
     */
    public function buildTypes(): array
    {
        $types = [];

        foreach ($this->models as $typeName => $model) {
            $fields = SchemaMapper::toObjectType($model['schema'], $model['prefix'], $typeName);

            if ($fields !== []) {
                $types[$typeName] = [
                    'fields' => self::withResolvers($fields, $model['schema'], $model['prefix']),
                ];
            }
        }

        foreach ($this->models as $typeName => $model) {
            $schema = $model['schema'];
            $prefix = $model['prefix'];

            foreach (SchemaMapper::toSubObjectTypes($schema, $prefix, $typeName) as $subTypeName => $subConfig) {
                $subFields = is_array($subConfig['fields'] ?? null) ? $subConfig['fields'] : [];
                $subSchema = self::subSchemaFor($schema, $typeName, $subTypeName);

                $types[$subTypeName] = [
                    'fields' => self::withResolvers($subFields, $subSchema, $prefix),
                ];
            }
        }

        return $types;
    }

    /**
     * Attach the composed literal-string `extensions.call.func` resolver
     * reference to each of SchemaMapper's pure field definitions.
     *
     * A SEPARATE step from SchemaMapper on purpose: SchemaMapper is pure
     * TYPE mapping (T04/T06's committed contract) and knows nothing about
     * YOOtheme's `@call` directive or resolver wiring — that coupling
     * belongs entirely to this, the only YOOtheme-aware member besides
     * register().
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $schema
     * @return array<string, array<string, mixed>>
     */
    private static function withResolvers(array $fields, array $schema, string $prefix): array
    {
        foreach ($fields as $field => $definition) {
            $definition['extensions'] = [
                'call' => [
                    'func' => resolve_jw_field_reference(),
                    'args' => [
                        'field' => (string) $field,
                        'schema' => $schema,
                        'prefix' => $prefix,
                    ],
                ],
            ];

            $fields[$field] = $definition;
        }

        return $fields;
    }

    /**
     * Recover the sub_fields schema for a repeater's composed sub-type name,
     * so its resolvers can be composed with the SAME schema/prefix pair the
     * parent repeater declared — FieldResolver::resolve()'s allow-list
     * argument must be the sub-schema, not the parent's.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function subSchemaFor(array $schema, string $typeName, string $subTypeName): array
    {
        foreach ($schema as $field => $config) {
            if (! is_array($config) || ($config['type'] ?? null) !== 'repeater') {
                continue;
            }

            $subFields = $config['sub_fields'] ?? null;

            if (! is_array($subFields)) {
                continue;
            }

            // Mirrors SchemaMapper's own composition — the mapper is the
            // single source of truth for the name, but it exposes no public
            // lookup, so the candidate name is recomputed with the same
            // <TypeName><Field>Item rule and matched against what was
            // actually emitted for this schema.
            $candidate = $typeName . self::upperCamelCase((string) $field) . 'Item';

            if ($candidate === $subTypeName) {
                return $subFields;
            }
        }

        return [];
    }

    /**
     * UpperCamelCase a snake_case/kebab-case field name — mirrors
     * SchemaMapper::upperCamelCase() exactly (private there, so duplicated
     * rather than exposed solely to serve this lookup).
     */
    private static function upperCamelCase(string $value): string
    {
        $words = preg_split('/[_\-]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            $words = [];
        }

        return implode('', array_map('ucfirst', $words));
    }

    /**
     * Attach the `source.init` listener, if and only if YOOtheme is present.
     *
     * The mandatory `function_exists('YOOtheme\app')` guard (FR-7's denial
     * path, golden path `yootheme-integration.md:66, :186`) MUST return
     * before any `Event::on()` call — a site without the licensed parent
     * theme must load with no fatal, no notice, no listener attached.
     *
     * Priority `-10`: YOOtheme's `EventDispatcher::addListener()` `krsort()`s
     * listeners for an event (`packages/utils/src/EventDispatcher.php:66`),
     * so a HIGHER number runs FIRST. A source must run AFTER the base types
     * `builder-wordpress-source` registers, so it needs a LOWER number — the
     * same `-10` every first-party add-on source uses
     * (`builder-wordpress-toolset/bootstrap.php:10`,
     * `builder-wordpress-acf/bootstrap.php:10`,
     * `builder-wordpress-woocommerce/bootstrap.php:15`). The wrong sign fails
     * silently: the builder loads, the types are just shadowed.
     */
    public function register(): void
    {
        if (! function_exists('YOOtheme\app')) {
            return;
        }

        \YOOtheme\Event::on('source.init', function ($source): void {
            foreach ($this->buildTypes() as $typeName => $config) {
                $source->objectType($typeName, $config);
            }
        }, -10);
    }
}

/**
 * The literal fully-qualified name of the standalone field resolver, as a
 * string constant expression — kept in one place so `withResolvers()` never
 * risks the `__NAMESPACE__`-composition trap the class docblock warns about
 * (a value that looks right but fails to resolve at builder-serialise time).
 * Written out in full here, verified against the actual declared function
 * below, rather than composed from `__NAMESPACE__` at runtime.
 */
function resolve_jw_field_reference(): string
{
    return 'josworld\\services\\yootheme\\resolve_jw_field';
}

/**
 * THE standalone resolver YOOtheme's `@call` directive invokes for every
 * mapped josworld field — declared here, in the global-to-this-file
 * namespace scope, so it is a genuinely existing, literally-nameable
 * function rather than a class method. See the class docblock's white-page
 * rule for why this cannot be a Closure or `[$obj, 'method']`.
 *
 * YOOtheme's `@call` directive invokes the named function with the GraphQL
 * resolver signature `($root, array $args)`; `$args` here carries the
 * `field` / `schema` / `prefix` this service composed into
 * `extensions.call.args` in `withResolvers()` above — never a caller-
 * supplied raw meta key (T05's threat 3 stays true: the only inputs that
 * reach `FieldResolver::resolve()` are ones THIS service decided at
 * collection time).
 *
 * `$root` is whatever YOOtheme's Source resolved as the parent item for this
 * field — for josworld's WordPress-backed types that is a `WP_Post`-shaped
 * value; only its `ID` is used.
 *
 * @param  mixed  $root
 * @param  array<string, mixed>  $args
 */
function resolve_jw_field(mixed $root, array $args = []): mixed
{
    $postId = is_object($root) && isset($root->ID) ? (int) $root->ID : 0;

    if (is_array($root) && isset($root['ID'])) {
        $postId = (int) $root['ID'];
    }

    $field = (string) ($args['field'] ?? '');
    $schema = is_array($args['schema'] ?? null) ? $args['schema'] : [];
    $prefix = (string) ($args['prefix'] ?? '');

    return \josworld\services\yootheme\FieldResolver::resolve($postId, $field, $schema, $prefix);
}
