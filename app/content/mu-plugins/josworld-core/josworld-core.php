<?php

/**
 * Josworld Core — business-logic plugin.
 *
 * Hosts Josworld's services OUTSIDE the theme, per the josworld convention
 * ("business logic belongs in a <project>-core mu-plugin, not the theme" —
 * see theme-config.php).
 *
 * LOAD ORDER: this file is included by josworld-coreloader.php, which sorts
 * alphabetically BEFORE ntdst-coreloader.php, so `ntdst_get()` and the rest
 * of the ntdst-core framework are NOT yet defined at include time. All
 * framework-dependent wiring is therefore deferred to `plugins_loaded`
 * (fires after every mu-plugin is included, so ntdst-core is guaranteed
 * present by then). ntdst-core itself is UNTOUCHED — it is shared with
 * other sites.
 *
 * @package josworld
 *
 * A plugin entrypoint by nature both declares symbols (the two constants
 * below) and executes side effects (add_action) — exempt from the PSR-1
 * side-effects rule, same posture as tests/bootstrap-unit.php.
 * phpcs:disable PSR1.Files.SideEffects
 */

defined('ABSPATH') || exit;

// Shared meta-key prefix (FR-12: no meta key may lack it) and textdomain,
// used by every model/service task (T04-T09). Guarded against redefinition
// because this file can theoretically be included more than once (defensive;
// mirrors the ABSPATH guard style above).
defined('JOSWORLD_CORE_META_PREFIX') || define('JOSWORLD_CORE_META_PREFIX', '_jw_');
defined('JOSWORLD_CORE_TEXTDOMAIN') || define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');

add_action('plugins_loaded', function (): void {
    // Fail quiet if ntdst-core didn't load (framework missing/disabled) —
    // no fatal, no partial registration.
    if (!function_exists('ntdst_get')) {
        return;
    }

    /**
     * Boot josworld-core's services.
     *
     * Mirrors DAAN's boot block deliberately (see plan.md "Boot model" +
     * daan-core.php:322-386): josworld is a single-purpose site, so it skips
     * ntdst-core's sector/auto-discovery/Bootstrap machinery and instead
     * requires each service file explicitly (no PSR-4 autoloader in a
     * project-core), then resolves it through the DI container.
     *
     * WHY the explicit require_once list: ntdst_get() only resolves a class
     * that PHP has already loaded. Without the matching require_once here,
     * the class is undefined at request time and the site FATALS with
     * "class not found" on a real request, while the unit suite stays green
     * (each unit test require_once's its own service file directly, so the
     * suite can never observe a missing line here). T02's unit test guards
     * this invariant structurally: every ntdst_get() call below must have a
     * matching require_once above it.
     *
     * Boot order: content services first, then admin-only, then
     * multilingual. Admin-only services are resolved only inside the
     * is_admin() branch (never in the unconditional list) so they are never
     * booted on a front-end request.
     */
    $dir = __DIR__ . '/services';

    // Content services (T05-T08) — add a require_once line here as each
    // lands:
    require_once $dir . '/content/CaseService.php';
    require_once $dir . '/content/TeamService.php';
    // require_once $dir . '/content/VacatureService.php';
    // require_once $dir . '/content/ToolService.php';

    // Admin-only service (T04)
    require_once $dir . '/admin/AdminUIService.php';

    // Multilingual service (T09) — none yet:
    // require_once $dir . '/multilingual/MultilingualService.php';

    unset($dir); // no longer needed after the require_once list above

    add_action('after_setup_theme', function (): void {
        // Content services — resolved unconditionally (front + admin). Add
        // ntdst_get() calls here as each service lands in T06-T08:
        ntdst_get(\josworld\services\content\CaseService::class);
        ntdst_get(\josworld\services\content\TeamService::class);
        // ntdst_get(\josworld\services\content\VacatureService::class);
        // ntdst_get(\josworld\services\content\ToolService::class);

        // Admin-only services — only in wp-admin. Never move a service out
        // of this branch without also moving its require_once with it.
        if (is_admin()) {
            ntdst_get(\josworld\services\admin\AdminUIService::class);
        }

        // Multilingual service — conditional on Polylang being active
        // (T09), not a hand-rolled function_exists() sprinkled elsewhere:
        // ntdst_get(\josworld\services\multilingual\MultilingualService::class);
    }, 5);
}, 5);
