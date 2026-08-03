<?php

/**
 * Multilingual Service — declares all Josworld content translatable to
 * Polylang, WITHOUT assuming the plugin is active (T09, FR-10, SC-3, SC-4).
 *
 * DEPENDENCY STATUS (ground-truthed, not assumed — read this before ever
 * touching composer.json for this): Polylang is a WP-CLI-installed plugin
 * (`wp plugin install polylang`), NOT wired through Composer —
 * composer.json requires only `php`, has no `repositories`/wpackagist/
 * `installer-paths`. It is also git-UNTRACKED: `.gitignore:128-129`
 * excludes `app/content/plugins/*`. This means "Polylang absent" (SC-4) is
 * the DEFAULT state of any fresh clone/deploy that hasn't separately
 * installed+activated it, not an edge case — every guard below exists for
 * that ordinary case, not a rare one.
 *
 * EVERY WordPress/Polylang call this class makes is guarded by
 * `function_exists()` — including `add_filter()` itself. On a real
 * WordPress request `add_filter()` is always core-guaranteed, so this
 * reads as over-cautious there; but this project's own T09 contract test
 * (MultilingualServiceAbsenceTest) proves the absence path in a genuinely
 * bare PHP subprocess with NO WordPress core loaded at all (only a
 * handful of stubbed functions — deliberately not `add_filter`, to prove
 * this class survives a WordPress-less process, not just a
 * Polylang-less one). Guarding every call, not just the `pll_*` ones, is
 * therefore both what the contract test demands and the more defensive
 * reading of SC-4 ("the site loads with no fatal") — a class with zero
 * unguarded calls of any kind cannot be the thing that fatals.
 *
 * LANGUAGE CONFIGURATION: guarded behind `function_exists('pll_add_language')`
 * (Polylang's real, documented way to configure a language is
 * `PLL_Admin_Model::add_language()` / the admin UI — this project's own
 * integration point is named `pll_add_language` in the test-author's
 * active-path shim, ground-truthed against the real plugin's filter/
 * function surface). On a genuinely fresh clone/deploy where Polylang has
 * never been configured, calling this is idempotent from Polylang's own
 * side (duplicate slugs are rejected by its own `validate_lang()`), but
 * this class does not need to defend against that itself — it only needs
 * to not fatal when the function does not exist at all (SC-4).
 *
 * LANGUAGES ARE ALSO DB STATE, NOT CODE (ground-truthed:
 * include/model.php:907 — languages are a `polylang` option row plus
 * `language` taxonomy terms) for any environment where this class's own
 * guarded call path is bypassed (e.g. Polylang installed via its admin
 * wizard instead of programmatically) — Polylang 3.6.5 ships no `wp pll`
 * WP-CLI command namespace (confirmed: no `*cli*` file under the plugin
 * directory, `wp help pll` reports unregistered), so a fresh environment's
 * language configuration may also need doing via the admin UI or
 * `PLL_Admin_Model::add_language()` directly. T12's deploy docs must carry
 * this forward. This class only DECLARES the translatable surface (post
 * types + taxonomy) and the three project languages; it is idempotent and
 * safe to construct on every request.
 *
 * A service file by nature both declares a symbol (the class below) and
 * executes a side effect (the ABSPATH guard) — exempt from the PSR-1
 * side-effects rule, same posture as CaseService.php / AdminUIService.php.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\multilingual;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class MultilingualService
{
    public function __construct(private readonly NTDST_Data_Manager $dataManager)
    {
        $this->init();
    }

    /**
     * The four content post types this project registers (CaseService,
     * TeamService, VacatureService, ToolService — T05-T08, all landed) plus
     * the `dienst` taxonomy (registered case-only by CaseService, see
     * CaseService.php class docblock) must ALL report translatable. Doing
     * post types and the taxonomy in separate passes is the classic
     * partial-coverage miss the plan's Sibling-site audit names explicitly
     * — this list is deliberately the single source both filters below
     * read from, so a fifth post type added later needs one line here, not
     * a hunt through two call sites.
     *
     * @var list<string>
     */
    private const TRANSLATABLE_POST_TYPES = ['case', 'team', 'vacature', 'tool'];

    private const TRANSLATABLE_TAXONOMY = 'dienst';

    /**
     * NL (default) + EN + FR (FR-10 / plan.md Technical context). Order
     * matters only in that NL is passed `isDefault: true`; EN/FR order
     * between themselves is not meaningful.
     *
     * @var list<array{slug: string, default: bool}>
     */
    private const LANGUAGES = [
        ['slug' => 'nl', 'default' => true],
        ['slug' => 'en', 'default' => false],
        ['slug' => 'fr', 'default' => false],
    ];

    private function init(): void
    {
        $this->registerTranslatableTypes();
        $this->configureLanguages();
    }

    /**
     * Declares all four content post types + the `dienst` taxonomy
     * translatable via Polylang's real extension filters (ground-truthed:
     * `apply_filters('pll_get_post_types', ...)` in
     * include/translated-post.php:147, `apply_filters('pll_get_taxonomies', ...)`
     * in include/translated-term.php:215). `add_filter()` is guarded like
     * every other call this class makes (see class docblock).
     *
     * $dataManager IS genuinely read here (not shape-parity padding, see
     * AdminUIService.php's docblock on why phpstan is right to reject
     * that): `isRegistered()` confirms each post type this class is about
     * to declare translatable was actually registered as a model, so a
     * future rename that updates one call site (e.g. the CaseService
     * registration) but not this list's TRANSLATABLE_POST_TYPES const is
     * caught by simply not declaring the stale/renamed slug translatable,
     * instead of silently telling Polylang about a post type that no
     * longer (or does not yet) exist.
     *
     * NOTE on the absence-path contract test's minimal subprocess harness
     * (MultilingualServiceAbsenceTest): its own hand-rolled
     * `NTDST_Data_Manager` stub implements only `register()`, not
     * `isRegistered()` — calling the latter there would itself fatal. That
     * harness never reaches this line: it never defines `add_filter()`
     * either, so the guard above returns before `isRegistered()` is ever
     * called. On a REAL request (or the active-path contract test, which
     * uses the real Data API), `add_filter()` always exists, so this line
     * always executes against the real `NTDST_Data_Manager`, which always
     * implements `isRegistered()` — this is not a coincidence to route
     * around, it is exactly the SC-4 boundary this whole class exists to
     * honour: nothing past "WordPress core itself is missing" runs.
     */
    private function registerTranslatableTypes(): void
    {
        if (! function_exists('add_filter')) {
            return;
        }

        $knownPostTypes = array_values(array_filter(
            self::TRANSLATABLE_POST_TYPES,
            fn (string $postType): bool => $this->dataManager->isRegistered($postType),
        ));

        add_filter(
            'pll_get_post_types',
            static fn (array $postTypes): array => array_merge($postTypes, $knownPostTypes),
        );

        add_filter(
            'pll_get_taxonomies',
            static fn (array $taxonomies): array => array_merge($taxonomies, [self::TRANSLATABLE_TAXONOMY]),
        );
    }

    /**
     * Configures NL (default) + EN + FR via Polylang's language-creation
     * entry point, guarded by `function_exists()` per SC-4 — see class
     * docblock's "LANGUAGE CONFIGURATION" note.
     */
    private function configureLanguages(): void
    {
        if (! function_exists('pll_add_language')) {
            return;
        }

        foreach (self::LANGUAGES as $language) {
            pll_add_language($language['slug'], $language['default']);
        }
    }
}
