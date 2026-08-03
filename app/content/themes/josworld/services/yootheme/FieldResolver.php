<?php

/**
 * FieldResolver — the VALUE half of the YOOtheme bridge (T05).
 *
 * SIBLING TO `SchemaMapper`, NOT A REPLACEMENT. SchemaMapper is the TYPE half
 * (schema -> GraphQL field definitions) and is deliberately pure. This class
 * is the VALUE half (post + field name -> stored value) and therefore CANNOT
 * be pure: it must read WordPress meta. It reads it through ntdst-core's
 * cached accessors and through nothing else — see `resolve()`.
 *
 * STATIC, like SchemaMapper, for a reason that is specific to YOOtheme and not
 * merely stylistic: T07 must reference every resolver as a LITERAL fully-
 * qualified string (`'func' => 'josworld\\services\\yootheme\\resolve_field'`)
 * because YOOtheme serialises resolver references into the builder's JSON
 * config. A closure, a `[$this, 'method']` pair, or `__NAMESPACE__ . '\\fn'`
 * does not survive that round-trip and yields a WHITE PAGE in the Customizer
 * (golden path `yootheme-integration.md`, "Why a standalone function and not a
 * method"). A static method is callable from inside such a standalone function
 * with no container lookup, no instance to serialise, and no bootstrap order
 * to get wrong. An instance resolver would push a `new` (or a container
 * `get()`) into the hot path of every field of every grid item.
 *
 * WHY EVERY VALUE READ IS PREFIXED, EXPLICITLY: `NTDST_Data_Model::formatMeta()`
 * (ntdst-core/api/Data.php:1481) composes `$metaKey = $this->meta_prefix .
 * $field` when it WRITES/formats meta for a model — josworld's models all pass
 * `_jw_` as that prefix (JOSWORLD_CORE_META_PREFIX, josworld-core.php:32). A
 * resolver that reads the bare field name (the working reference
 * implementation's shape, ludoluykx `YOOthemeDynamicContentService.php:507`)
 * silently resolves every value to empty in production, because the bare key
 * never exists — only `_jw_<field>` does. `resolve()` must therefore always
 * compose `$prefix . $field` and never accept a caller-supplied raw meta key
 * (threat 3); `$schema` is the allow-list that makes the second half
 * structural (threat 2).
 *
 * THE CACHED-META SEAM (`ntdst_get_post_meta_from_cache()`) LIVES IN ITS OWN
 * FILE (`ntdst-cached-meta-accessor.php`, global namespace) AND IS REQUIRED
 * LAZILY FROM `resolve()`, not eagerly here — see that file's docblock for
 * why: Brain Monkey/Patchwork can only intercept a function whose defining
 * file was included AFTER Patchwork bootstraps, and `FieldResolverTest`
 * requires this file at test-suite discovery time, before any test's
 * `setUp()` (and therefore before Patchwork) has run. Declaring the seam
 * function directly in THIS file — even behind a `function_exists()` guard —
 * fails with `Patchwork\Exceptions\DefinedTooEarly`; this was verified
 * empirically while making this contract green, not assumed.
 *
 * A theme service file by nature declares a symbol and executes a side effect
 * (the ABSPATH guard) — same posture as SchemaMapper and the josworld-core
 * services.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\yootheme;

defined('ABSPATH') || exit;

final class FieldResolver
{
    /**
     * Per-request memo of resolved post meta maps, keyed by post ID.
     *
     * Exists so resolving N fields of the SAME post costs at most one call
     * into the cached accessor (FR-8/SC-2) — YOOtheme calls the resolver
     * once per bound field per grid item, so without a memo, N fields means
     * N accessor calls. A bare `static` here would otherwise leak across
     * requests in a long-lived process, and — found by the test-author
     * while confirming this contract is satisfiable — across PHPUnit tests
     * in the same process, since every test in this suite resolves the same
     * post ID 42. `flush()` below is the invalidation the test-author
     * authorised adding; the unit test base class
     * (`tests/Unit/TestCase.php`) calls it in `tearDown()` so each test
     * starts with a clean memo.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $metaMemo = [];

    /**
     * Clear the per-request meta memo.
     *
     * Not part of the read contract itself — added so the memo has an
     * explicit invalidation point rather than only relying on process
     * lifetime. A long-lived request that mutates a post's meta
     * mid-request (e.g. via `update_post_meta()` followed by a re-render)
     * would also need it.
     */
    public static function flush(): void
    {
        self::$metaMemo = [];
    }

    /**
     * Resolve ONE declared field of ONE post to its stored value.
     *
     * THE SIGNATURE IS THE CONTRACT. `$schema` and `$prefix` are supplied by
     * the REGISTRATION side (T07, from the model's own `getSchema()` /
     * `getMetaPrefix()`), never by the builder-side caller — which passes
     * only `$field`. That is what makes threat 3 ("resolution never accepts
     * a caller-supplied raw meta key") structurally true rather than merely
     * conventional: there is no parameter through which a raw meta key can
     * be injected, because the only caller-controlled string is checked
     * against `$schema` before any key is composed, and the key is then
     * composed here from `$prefix`.
     *
     * @param  int  $postId  The post whose meta is read.
     * @param  string  $field  The DECLARED field name (`client`), never a
     *                         meta key. A value not present in `$schema` is
     *                         refused.
     * @param  array<string, mixed>  $schema  The model's schema, verbatim
     *                                        from
     *                                        `NTDST_Data_Model::getSchema()`.
     * @param  string  $prefix  The model's meta prefix, verbatim from
     *                          `NTDST_Data_Model::getMetaPrefix()`
     *                          (`_jw_`).
     * @return mixed The stored value: `string` for scalars (empty string
     *               when absent — never null), `array` for repeaters
     *               (empty array when absent or malformed — never null),
     *               `''` for a field the schema does not declare.
     */
    public static function resolve(int $postId, string $field, array $schema, string $prefix): mixed
    {
        // Threat 2: the schema is the allow-list. A field the model does
        // not declare is refused BEFORE any key is composed or any meta is
        // touched — no lookup happens for an undeclared field at all.
        if (! array_key_exists($field, $schema)) {
            return '';
        }

        $config = $schema[$field];

        if (is_string($config)) {
            $config = ['type' => $config];
        }

        if (! is_array($config)) {
            return '';
        }

        if (! isset(self::$metaMemo[$postId])) {
            // Lazy require — see the class docblock and
            // ntdst-cached-meta-accessor.php for why this cannot be an
            // eager top-of-file require.
            require_once __DIR__ . '/ntdst-cached-meta-accessor.php';

            self::$metaMemo[$postId] = \ntdst_get_post_meta_from_cache($postId);
        }

        $meta = self::$metaMemo[$postId];

        // Threat 3: the ONLY key ever looked up is the composed
        // `$prefix . $field` — never `$field` itself (the reference
        // implementation's bug) and never a caller-supplied raw meta key,
        // because `$field` was already checked against `$schema` above and
        // `$prefix` comes from the registration side, not the caller.
        $metaKey = $prefix . $field;
        $value = $meta[$metaKey] ?? null;

        if (($config['type'] ?? null) === 'repeater') {
            return self::resolveRepeater($value);
        }

        return $value === null ? '' : (string) $value;
    }

    /**
     * Normalise a stored repeater value to a list of associative rows.
     *
     * Mirrors `NTDST_Data_Model::formatRepeaterField()` (Data.php:259-287)
     * on the read side: absent/empty -> empty list; a stored value that is
     * not an array (corrupt overwrite, un-decoded serialized string,
     * scalar) -> empty list, no fatal; and — one level down — any row
     * within an array value that is itself not an array is dropped rather
     * than returned, since a non-array row reaching YOOtheme's `listOf`
     * machinery is a foreach-over-scalar fatal in the builder.
     *
     * @return list<array<string, mixed>>
     */
    private static function resolveRepeater(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
