<?php

/**
 * Multilingual Service — declares the content post types, the `dienst`
 * taxonomy, and the NL/EN/FR languages to Polylang.
 *
 * Polylang is WP-CLI-installed, not Composer-wired, and git-untracked —
 * "Polylang absent" is the DEFAULT state of a fresh clone/deploy, not an
 * edge case. EVERY call this class makes is guarded by function_exists(),
 * including add_filter(): the absence contract test
 * (MultilingualServiceAbsenceTest) runs it in a bare PHP subprocess with
 * no WordPress loaded, and the site must load with no fatal.
 *
 * Languages are also DB state (a `polylang` option + `language` terms) and
 * Polylang ships no `wp pll` CLI — a fresh environment may still need
 * language setup via the admin UI. This class only DECLARES the
 * translatable surface; it is idempotent and safe on every request.
 *
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
     * Single source both filters below read from — a fifth post type later
     * needs one line here, not a hunt through two call sites.
     *
     * PUBLIC so PostTypeRegistryConsistencyTest can assert it against the
     * live registry: the isRegistered() filter below means a stale entry is
     * silently EXCLUDED (never phantom-included), so a post type could
     * quietly stop being translatable with no test failure — visible only
     * as a missing language switcher.
     *
     * @var list<string>
     */
    public const TRANSLATABLE_POST_TYPES = ['case', 'team', 'vacature', 'tool'];

    private const TRANSLATABLE_TAXONOMY = 'dienst';

    /**
     * NL is the default; EN/FR order is not meaningful.
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
     * The isRegistered() filter means a slug renamed at the registration
     * site but not here is simply not declared translatable — Polylang is
     * never told about a post type that does not exist.
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
