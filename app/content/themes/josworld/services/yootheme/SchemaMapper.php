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

use LogicException;

defined('ABSPATH') || exit;

final class SchemaMapper
{
    /**
     * Map an ntdst model schema to YOOtheme/GraphQL field definitions.
     *
     * @param array<string, mixed> $schema The model schema as ntdst stores it.
     *                                     Entries are USUALLY `['type' => ..., 'label' => ...]`
     *                                     but the framework also tolerates a bare
     *                                     string (`'title' => 'text'`) — see
     *                                     ntdst-core/api/Data.php::setupSanitizers().
     * @param string               $prefix The model's meta prefix (e.g. `_jw_`).
     *
     * @return array<string, array<string, mixed>> Field name => definition. Fields
     *                                             whose type has no mapping are
     *                                             OMITTED from this array entirely.
     */
    public static function toObjectType(array $schema, string $prefix): array
    {
        throw new LogicException('SchemaMapper::toObjectType() is not implemented');
    }
}
