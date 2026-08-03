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
     * @param  string  $name  The model / post-type slug (`case`, `team`).
     * @param  array<string, mixed>  $config  The registration config, carrying
     *                                        at least `fields` (the schema)
     *                                        and `meta_prefix`.
     */
    public function collect(string $name, array $config): void
    {
        throw new \RuntimeException('not implemented');
    }

    /**
     * Emit the YOOtheme object-type configs for everything collected.
     *
     * @return array<string, array<string, mixed>> Type name => objectType
     *                                             config, including repeater
     *                                             sub-types.
     */
    public function buildTypes(): array
    {
        throw new \RuntimeException('not implemented');
    }

    /**
     * Attach the `source.init` listener, if and only if YOOtheme is present.
     */
    public function register(): void
    {
        throw new \RuntimeException('not implemented');
    }
}
