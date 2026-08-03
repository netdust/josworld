<?php

/**
 * Global-namespace shim simulating "Polylang is active" for
 * MultilingualServiceTest. Declared in a separate, unnamespaced file
 * (rather than inline in the namespaced test class) because a `function`
 * declared inside a `namespace NtdstTests\Integration;` file is NOT the
 * global `pll_languages_list()` PHP itself calls via `\function_exists()` —
 * it would be `NtdstTests\Integration\pll_languages_list()`, a different
 * symbol entirely, and the include-once guard in
 * MultilingualServiceTest::simulate_polylang_being_active() would never
 * trip. This file carries no `namespace` statement so these functions land
 * in the SAME global namespace Polylang itself installs them into
 * (ground-truthed: include/api.php in a real Polylang install).
 */

declare(strict_types=1);

// No side-effect state here on purpose (PSR1 side-effects) — the caller,
// MultilingualServiceTest::simulate_polylang_being_active(), resets
// $GLOBALS['__pll_languages'] itself immediately after the include-once
// require below, so this file only ever DECLARES symbols.

/**
 * @param array<string, mixed> $args
 * @return list<string>
 */
function pll_languages_list(array $args = []): array
{
    return $GLOBALS['__pll_languages'];
}

function pll_default_language(string $field = 'slug'): ?string
{
    return $GLOBALS['__pll_default_language'] ?? null;
}

/**
 * Records a configured language exactly the way a real Polylang
 * "Add new language" admin action would — this is the seam
 * MultilingualService is expected to call through (there is no other
 * public Polylang API for configuring languages short of the admin UI or
 * its internal Language::create(), neither reachable from an integration
 * test at this tier).
 */
function pll_add_language(string $slug, bool $isDefault = false): void
{
    $GLOBALS['__pll_languages'][] = $slug;
    if ($isDefault) {
        $GLOBALS['__pll_default_language'] = $slug;
    }
}
