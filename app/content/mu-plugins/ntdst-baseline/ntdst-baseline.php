<?php

/**
 * NTDST Baseline
 *
 * Shared WordPress-gaps baseline: security headers, head cleanup, SEO,
 * schema, maintenance mode, cache headers. No daan-specific values live in
 * this plugin directory — everything site-specific is injected via the
 * `ntdst/baseline/*` filters.
 *
 * All wiring is deferred until plugins_loaded:5 so that the ntdst-core
 * framework (and its DI container) is guaranteed to exist before we try to
 * resolve anything from it — plugins_loaded fires after every mu-plugin is
 * included. If the framework is absent, we fail quiet and never fatal
 * (FR-20/AC-9).
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */

namespace ntdst\baseline;

defined('ABSPATH') || exit;

/**
 * Boot every enabled baseline module.
 *
 * Extracted to a named, independently-callable function (rather than an
 * inline closure) so integration tests can invoke the boot decision
 * deterministically after registering their own `ntdst/baseline/modules`
 * filter — the plugin already boots once at real WP load with defaults
 * before any test filter can attach, so re-invoking this function is the
 * only way to observe a test's module selection. The `after_setup_theme`
 * hook below calls this exact same function; there are not two boot paths.
 */
function boot_modules(): void
{
    $modules = apply_filters('ntdst/baseline/modules', [
        'security'      => true,
        'head_cleanup'  => true,
        'seo'           => true,
        'schema'        => true,
        'maintenance'   => true,
        'cache_headers' => true,
    ]);
    $map = [
        'security'      => \ntdst\baseline\services\SecurityService::class,
        'head_cleanup'  => \ntdst\baseline\services\HeadCleanupService::class,
        'seo'           => \ntdst\baseline\services\SeoService::class,
        'schema'        => \ntdst\baseline\services\SchemaService::class,
        'maintenance'   => \ntdst\baseline\services\MaintenanceService::class,
        'cache_headers' => \ntdst\baseline\services\CacheHeadersService::class,
    ];
    foreach ($map as $key => $class) {
        // T1: stub files declare no classes yet, so guard with
        // class_exists() to keep boot fail-quiet on partial deployment.
        // T2+ keeps this guard once the classes are real.
        if (!empty($modules[$key]) && class_exists($class)) {
            \ntdst_get($class);
        }
    }
}

add_action('plugins_loaded', function (): void {
    if (!function_exists('ntdst_get')) {
        return; // framework absent — fail quiet, never fatal (FR-20/AC-9)
    }

    $dir = __DIR__;
    require_once $dir . '/support/Config.php';
    require_once $dir . '/support/Cidr.php';
    require_once $dir . '/support/ClientIp.php';
    require_once $dir . '/support/SecurityHeaderPolicy.php';
    require_once $dir . '/support/CacheHeaderPolicy.php';
    require_once $dir . '/support/JsonLd.php';
    require_once $dir . '/services/SecurityService.php';
    require_once $dir . '/services/HeadCleanupService.php';
    require_once $dir . '/services/SeoService.php';
    require_once $dir . '/services/SchemaService.php';
    require_once $dir . '/services/MaintenanceService.php';
    require_once $dir . '/services/CacheHeadersService.php';

    add_action('after_setup_theme', __NAMESPACE__ . '\\boot_modules', 5);
}, 5);
