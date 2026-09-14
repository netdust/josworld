<?php

/**
 * Josworld Core — business-logic plugin. Business logic lives here, not in
 * the theme.
 *
 * LOAD ORDER: included by josworld-coreloader.php, which sorts
 * alphabetically BEFORE ntdst-coreloader.php — the framework is NOT yet
 * defined at include time, so all framework-dependent wiring defers to
 * `plugins_loaded` (every mu-plugin is included by then). ntdst-core is
 * shared with other sites and stays untouched.
 *
 * @package josworld
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

defined('ABSPATH') || exit;

// Shared meta-key prefix (no meta key may lack it) and textdomain; guarded
// against double inclusion.
defined('JOSWORLD_CORE_META_PREFIX') || define('JOSWORLD_CORE_META_PREFIX', '_jw_');
defined('JOSWORLD_CORE_TEXTDOMAIN') || define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');

// The ntdst-baseline yootheme module publishes every model to the builder's
// Dynamic Content picker; polylang owns the multilingual layer (shared slugs,
// layout copy on a "+" translation, the `wp ntdst polylang` commands).
// Assignment, not union: both keys already exist (off).
add_filter('ntdst/baseline/modules', static function (array $modules): array {
    $modules['yootheme'] = true;
    $modules['polylang'] = true;

    return $modules;
});

add_action('plugins_loaded', function (): void {
    // Fail quiet if ntdst-core didn't load (framework missing/disabled) —
    // no fatal, no partial registration.
    if (!function_exists('ntdst_get')) {
        return;
    }

    /**
     * Boot josworld-core's services. No PSR-4 autoloader in a project-core:
     * each service file is required explicitly, then resolved through the
     * DI container.
     *
     * ORDERING CONTRACT — every ntdst_get() call below MUST have a matching
     * require_once above it: ntdst_get() only resolves a class PHP already
     * loaded, so a missing require_once fatals on a real request while the
     * unit suite stays green (unit tests require their own service files
     * directly and can never observe a missing line here). A structural
     * unit test guards this.
     *
     * Boot order: content services first, then admin-only (inside the
     * is_admin() branch only — never booted on a front-end request).
     */
    $dir = __DIR__ . '/services';

    // Content services:
    require_once $dir . '/content/CaseService.php';
    require_once $dir . '/content/TeamService.php';
    require_once $dir . '/content/VacatureService.php';
    require_once $dir . '/content/ToolService.php';

    // Admin-only service:
    require_once $dir . '/admin/AdminUIService.php';

    // Form service:
    require_once $dir . '/forms/CrmContactLanguageService.php';

    // Forms: the tool download confirmation.
    require_once $dir . '/forms/DownloadFormService.php';

    unset($dir); // no longer needed after the require_once list above

    add_action('after_setup_theme', function (): void {
        // Content services — resolved unconditionally (front + admin):
        ntdst_get(\josworld\services\content\CaseService::class);
        ntdst_get(\josworld\services\content\TeamService::class);
        ntdst_get(\josworld\services\content\VacatureService::class);
        ntdst_get(\josworld\services\content\ToolService::class);

        // Admin-only services — only in wp-admin. Never move a service out
        // of this branch without also moving its require_once with it.
        if (is_admin()) {
            ntdst_get(\josworld\services\admin\AdminUIService::class);
        }

        // Form service — resolved unconditionally: the FluentCRM/Polylang
        // absence guards live INSIDE the service, as below.
        ntdst_get(\josworld\services\forms\CrmContactLanguageService::class);

        // The download form's confirmation runs on a front-end submission.
        ntdst_get(\josworld\services\forms\DownloadFormService::class);
    }, 5);
}, 5);
