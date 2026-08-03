<?php

declare(strict_types=1);

namespace ntdst\baseline\services;

use ntdst\baseline\support\Config;

defined('ABSPATH') || exit;

/**
 * MaintenanceService.
 *
 * Registers the 'maintenance' boot marker so the boot-orchestration layer (and
 * later T18's kill-switch sweep) can introspect which modules actually
 * booted via `apply_filters('ntdst/baseline/booted', [])`. This marker
 * registration is permanent — it stays even once this stub grows real
 * module logic.
 *
 * T9: port of ludoluykx's DatabaseMaintenanceService.php WITH the two
 * audited source bugs fixed (plan.md lines 618-637):
 *
 * BUG FIX 1 (cron never fires): the source registered the
 * `ntdst_performance_cleanup` cron callback INSIDE `scheduleCleanup()`,
 * itself only ever attached via an `admin_init`-gated `is_admin()` check
 * (:69-88). WP-Cron runs requests in a non-admin context where
 * `admin_init` never fires, so the scheduled event had zero listeners and
 * cleanup silently never ran. Fix: `performCleanup` is registered on
 * `ntdst_baseline_cleanup` UNCONDITIONALLY in the constructor; the
 * `wp_next_scheduled` scheduling guard moves to `init` (also
 * unconditional, weekly).
 *
 * BUG FIX 2 (2-hour autosave) — CORRECTED, fix round 1: the "bug" was
 * never live in the first place. WordPress does NOT filter autosave
 * cadence via `apply_filters('autosave_interval', ...)` — no such filter
 * exists anywhere in core (`grep -rn "apply_filters.*autosave_interval"
 * web/wp/` returns zero hits). Core reads the `AUTOSAVE_INTERVAL`
 * CONSTANT directly (wp-includes/script-loader.php:1948,
 * wp-admin/edit-form-blocks.php:271), guard-defined by
 * `wp_functionality_constants()` (wp-includes/default-constants.php:380-381,
 * `if (!defined('AUTOSAVE_INTERVAL')) define('AUTOSAVE_INTERVAL', MINUTE_IN_SECONDS)`)
 * — meaning the ludoluykx source's `add_filter('autosave_interval', ...)`
 * was ALREADY dead code; its `* 60` bug never fired in production either.
 * The T9 first pass ported the same dead filter (fixing the multiplier
 * but not the fact that nothing calls apply_filters() on this tag) —
 * caught by C3 review + independently reverified here.
 *
 * The honest constant-based fix is ALSO not reachable from this service:
 * `wp_functionality_constants()` runs at `wp-settings.php:625`, i.e.
 * immediately after `do_action('plugins_loaded')` (`:622`) — but this
 * module's own construction is deferred past that, to
 * `do_action('after_setup_theme')` (`:749`), where `boot_modules()`
 * actually resolves `MaintenanceService`. By the time our constructor
 * runs, `AUTOSAVE_INTERVAL` is already defined; a guarded
 * `define('AUTOSAVE_INTERVAL', ...)` here would silently no-op — the same
 * inert-knob problem, just relocated. There is no hook this plugin can
 * reach that fires before `wp_functionality_constants()`.
 *
 * Decision: DROPPED. No mechanism this module can reach can actually
 * change autosave cadence, so no knob is offered — an inert config key
 * that looks configurable is worse than no key. Site autosave cadence, if
 * ever needed, must be set via `Config::define('AUTOSAVE_INTERVAL', ...)`
 * in `config/application.php` (Bedrock's early config layer, which loads
 * before `wp-settings.php` and therefore beats
 * `wp_functionality_constants()`'s `!defined()` guard).
 *
 * T10: fills `performCleanup()`'s body — revisions / spam / transients,
 * each config-gated (`cleanup.revisions|spam|transients`) and bounded by
 * the shared `cleanup.age_days` / `cleanup.batch` limits. Revisions use
 * `wp_delete_post_revision()` (the WP API), NOT the source's would-be raw
 * `DELETE FROM wp_posts` shape — a raw delete orphans postmeta because it
 * skips WP's own associated-data cleanup; the WP-API call is the version
 * to keep going forward.
 *
 * Dead keys not ported: `enable_caching` / `cache_duration` (source
 * :42-43) — no caching logic ever consumed them in the source; the
 * baseline's cache behavior is CacheHeadersService's job (cluster C5).
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class MaintenanceService
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    private const DEFAULTS = [
        'limit_post_revisions' => 5,
        'heartbeat_interval' => 60,
        'cleanup' => [
            'revisions' => true,
            'spam' => true,
            'transients' => true,
            'age_days' => 30,
            'batch' => 100,
        ],
        'preconnect_domains' => [],
    ];

    public function __construct()
    {
        add_filter('ntdst/baseline/booted', static fn(array $booted): array => [...$booted, 'maintenance']);

        $this->config = Config::merge(self::DEFAULTS, (array) apply_filters('ntdst/baseline/maintenance/config', []));

        // BUG FIX 1: registered unconditionally so WP-Cron (a non-admin
        // request) always has a listener — never gated behind is_admin()
        // or admin_init the way the ported source's scheduleCleanup() was.
        add_action('ntdst_baseline_cleanup', [$this, 'performCleanup']);

        // Scheduling guard also unconditional, moved to init (was dead
        // inside the admin_init-gated scheduleCleanup() in the source).
        add_action('init', [$this, 'scheduleCleanup']);

        if ($this->config['limit_post_revisions']) {
            add_filter('wp_revisions_to_keep', fn(): int => (int) $this->config['limit_post_revisions']);
        }

        // autosave_interval: DELIBERATELY NOT WIRED — see the BUG FIX 2
        // class doc above. No filter exists in WP core to attach to, and
        // this module's boot (after_setup_theme) runs after core has
        // already defined the AUTOSAVE_INTERVAL constant, so no mechanism
        // reachable from here can affect autosave cadence. Set via
        // config/application.php if ever needed.

        if ($this->config['heartbeat_interval']) {
            add_filter('heartbeat_settings', function (array $settings): array {
                $settings['interval'] = (int) $this->config['heartbeat_interval'];

                return $settings;
            });
        }

        if (!empty($this->config['preconnect_domains'])) {
            add_action('wp_head', [$this, 'addPreconnectDomains'], 1);
        }
    }

    public function scheduleCleanup(): void
    {
        if (!wp_next_scheduled('ntdst_baseline_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'ntdst_baseline_cleanup');
        }
    }

    public function addPreconnectDomains(): void
    {
        foreach ($this->config['preconnect_domains'] as $domain) {
            echo '<link rel="preconnect" href="' . esc_url($domain) . '" crossorigin>' . "\n";
        }
    }

    /**
     * Registered on the `ntdst_baseline_cleanup` cron hook. Runs the three
     * config-gated cleanup routines (revisions / spam / transients), each
     * respecting the shared `cleanup.age_days` / `cleanup.batch` limits.
     *
     * Revisions use `wp_delete_post_revision()` (the WP API), never a raw
     * `DELETE FROM wp_posts` — a raw delete orphans postmeta rows since it
     * bypasses WP's own cleanup of associated meta/caches.
     */
    public function performCleanup(): void
    {
        $cleanup = $this->config['cleanup'];

        if (!empty($cleanup['revisions'])) {
            $this->cleanupPostRevisions((int) $cleanup['age_days'], (int) $cleanup['batch']);
        }

        if (!empty($cleanup['spam'])) {
            $this->cleanupSpamComments((int) $cleanup['age_days'], (int) $cleanup['batch']);
        }

        if (!empty($cleanup['transients'])) {
            $this->cleanupTransients();
        }
    }

    private function cleanupPostRevisions(int $ageDays, int $batch): void
    {
        $revisions = get_posts([
            'post_type' => 'revision',
            'post_status' => 'inherit',
            'date_query' => [
                [
                    'before' => $ageDays . ' days ago',
                ],
            ],
            'posts_per_page' => $batch,
            'fields' => 'ids',
            'orderby' => 'post_date',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        $count = 0;

        foreach ($revisions as $revisionId) {
            if (wp_delete_post_revision($revisionId)) {
                $count++;
            }
        }

        if ($count > 0 && function_exists('ntdst_log')) {
            ntdst_log('maintenance')->info('Cleaned up post revisions', ['count' => $count]);
        }
    }

    private function cleanupSpamComments(int $ageDays, int $batch): void
    {
        $comments = get_comments([
            'status' => 'spam',
            'date_query' => [
                [
                    'before' => $ageDays . ' days ago',
                ],
            ],
            'number' => $batch,
            'orderby' => 'comment_date',
            'order' => 'ASC',
        ]);

        $count = 0;

        foreach ($comments as $comment) {
            if (wp_delete_comment((int) $comment->comment_ID, true)) {
                $count++;
            }
        }

        if ($count > 0 && function_exists('ntdst_log')) {
            ntdst_log('maintenance')->info('Cleaned up spam comments', ['count' => $count]);
        }
    }

    private function cleanupTransients(): void
    {
        delete_expired_transients();

        if (function_exists('ntdst_log')) {
            ntdst_log('maintenance')->info('Cleaned up expired transients');
        }
    }
}
