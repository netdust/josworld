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
     * @return array<string, array<string, mixed>> Field name => definition. Fields
     *                                             whose type has no mapping are
     *                                             OMITTED from this array entirely.
     */
    public static function toObjectType(array $schema, string $prefix): array
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
}
