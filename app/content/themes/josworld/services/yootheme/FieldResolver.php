<?php

/**
 * FieldResolver — the VALUE half of the YOOtheme bridge (T05).
 *
 * SIGNATURE SHELL ONLY. Authored by the independent test-author ahead of the
 * implementation so that `FieldResolverTest` fails for a BEHAVIOURAL reason
 * ("expected the stored value for _jw_client, got not-implemented") rather
 * than "class not found". The bodies below are sentinels: the implementer
 * replaces them with the real resolution and must not weaken the committed
 * contract test.
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
     * Resolve ONE declared field of ONE post to its stored value.
     *
     * THE SIGNATURE IS THE CONTRACT. `$schema` and `$prefix` are supplied by
     * the REGISTRATION side (T07, from the model's own `getSchema()` /
     * `getMetaPrefix()`), never by the builder-side caller — which passes only
     * `$field`. That is what makes threat 3 ("resolution never accepts a
     * caller-supplied raw meta key") structurally true rather than merely
     * conventional: there is no parameter through which a raw meta key can be
     * injected, because the only caller-controlled string is checked against
     * `$schema` before any key is composed, and the key is then composed here
     * from `$prefix`.
     *
     * @param  int  $postId  The post whose meta is read.
     * @param  string  $field  The DECLARED field name (`client`), never a meta
     *                         key. A value not present in `$schema` is refused.
     * @param  array<string, mixed>  $schema  The model's schema, verbatim from
     *                                        `NTDST_Data_Model::getSchema()`.
     * @param  string  $prefix  The model's meta prefix, verbatim from
     *                          `NTDST_Data_Model::getMetaPrefix()` (`_jw_`).
     * @return mixed The stored value: `string` for scalars (empty string when
     *               absent — never null), `array` for repeaters (empty array
     *               when absent or malformed — never null), `''` for a field
     *               the schema does not declare.
     */
    public static function resolve(int $postId, string $field, array $schema, string $prefix): mixed
    {
        unset($postId, $field, $schema, $prefix);

        throw new \RuntimeException('FieldResolver::resolve() is not implemented');
    }
}
