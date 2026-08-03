<?php

declare(strict_types=1);

namespace ntdst\baseline\services;

use ntdst\baseline\support\ClientIp;
use ntdst\baseline\support\Config;
use ntdst\baseline\support\SecurityHeaderPolicy;

defined('ABSPATH') || exit;

/**
 * SecurityService.
 *
 * Registers the 'security' boot marker so the boot-orchestration layer (and
 * later T18's kill-switch sweep) can introspect which modules actually
 * booted via `apply_filters('ntdst/baseline/booted', [])`. This marker
 * registration is permanent — it stays even once this stub grows real
 * module logic.
 *
 * T5: pre-auth login limiter (threat-model M3) + generic login error
 * disclosure guard (FR-9). T6: security response headers (threat-model
 * M9/M10), built by the pure SecurityHeaderPolicy::build() and emitted on
 * `send_headers`. T7: attack-surface toggles (threat-model M3 partial +
 * M4, plan.md lines 163-175 & 512-520) — REST user-enumeration blocking
 * (unauthenticated-only, the REWORK vs. the ludoluykx source which broke
 * admin author dropdowns by removing the routes globally), the `?author=`
 * archive-probe 403, XML-RPC disable, and the file-editor lockout.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class SecurityService
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    private const DEFAULTS = [
        'disable_xmlrpc' => true,
        'disable_file_editor' => true,
        'disable_user_enumeration' => true,
        'limit_login_attempts' => true,
        'generic_login_errors' => true,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'trusted_proxies' => ['127.0.0.1', '::1'],
        'security_headers' => true,
        'hsts' => ['enabled' => false, 'include_subdomains' => false, 'max_age' => 31536000],
        'csp' => [],
    ];

    public function __construct()
    {
        add_filter('ntdst/baseline/booted', static fn(array $booted): array => [...$booted, 'security']);

        $this->config = Config::merge(self::DEFAULTS, (array) apply_filters('ntdst/baseline/security/config', []));

        if ($this->config['limit_login_attempts']) {
            $this->initLoginLimiter();
        }

        if ($this->config['generic_login_errors']) {
            add_filter('login_errors', [$this, 'genericLoginError']);
        }

        if ($this->config['security_headers']) {
            add_action('send_headers', [$this, 'sendSecurityHeaders']);
        }

        if ($this->config['disable_user_enumeration']) {
            add_filter('rest_endpoints', [$this, 'removeUserEnumerationRoutes']);

            // Priority 0 — MUST run before WordPress core's own
            // `redirect_canonical`, which core registers on this SAME hook
            // at the DEFAULT priority (10) in
            // web/wp/wp-includes/default-filters.php:672
            // (`add_action('template_redirect', 'redirect_canonical')`).
            // Core's registration happens at core-load time, well before
            // this service boots on `after_setup_theme:5`, so an implicit
            // (tied) priority-10 registration here LOSES the tie to core
            // (WP_Hook preserves insertion order within a priority) and
            // core answers a `?author=N` probe FIRST: a valid id 301s to
            // `/author/<username>/` — leaking the username in the Location
            // header — before this guard is ever reached, while an invalid
            // id 404s. That valid/invalid differential is exactly the
            // username-enumeration oracle M4 exists to close (found live
            // over HTTP at shake-out; invisible to the integration suite,
            // which invokes `template_redirect` directly and never
            // exercises core's competing registration). Registering at 0
            // guarantees this guard's hard denial fires before core's
            // canonical-redirect logic ever runs for an author archive
            // request.
            add_action('template_redirect', [$this, 'blockAuthorArchiveProbe'], 0);
        }

        if ($this->config['disable_xmlrpc']) {
            add_filter('xmlrpc_enabled', [$this, 'xmlrpcDisabled']);
            add_action('init', [$this, 'blockDirectXmlrpcRequest'], 0);
        }

        if ($this->config['disable_file_editor'] && !defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }
    }

    /**
     * Emits the security response headers built by SecurityHeaderPolicy.
     *
     * Skipped for AJAX requests and once headers have already been sent —
     * mirrors the established `addSecurityHeaders()` guard pattern used
     * elsewhere in the ntdst theme family (ludoluykx SecurityService.php:339).
     */
    public function sendSecurityHeaders(): void
    {
        if (headers_sent() || wp_doing_ajax()) {
            return;
        }

        foreach (SecurityHeaderPolicy::build($this->config, is_ssl()) as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * Unsets the REST users routes for unauthenticated requests only
     * (threat-model M4a — REWORK vs. the ludoluykx source, which removed
     * these routes globally and broke the admin author dropdown, per
     * plan.md:169-170). `WP_REST_Server::get_routes()` re-applies this
     * filter on every dispatch, so re-checking `is_user_logged_in()` here
     * (rather than caching a decision at construction time) is required
     * for the guard to track the actual current request/actor.
     *
     * @param array<string, mixed> $endpoints
     * @return array<string, mixed>
     */
    public function removeUserEnumerationRoutes(array $endpoints): array
    {
        if (is_user_logged_in()) {
            return $endpoints;
        }

        unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);

        return $endpoints;
    }

    /**
     * Refuses `?author=N` AND `?author_name=slug` / pretty-permalink
     * `/author/slug/` front-end archive-enumeration probes with a hard 403
     * (threat-model M4b, SC-3). Refuses whenever `is_author()` is true —
     * NOT gated on whether the author query var resolved to a non-zero id.
     *
     * WP sets `is_author = true` for BOTH `?author=N` and `?author_name=`
     * (class-wp-query.php:983-990), but only populates
     * `query_vars['author']` for `author_name` when the slug resolves to a
     * real user (class-wp-query.php:2427-2429 — `get_user_by('slug', ...)`
     * returns `false` for a nonexistent slug, so `author` stays falsy).
     * Gating the refusal on a resolved non-zero author id therefore let a
     * VALID `author_name` 403 while an INVALID one fell through to normal
     * rendering — a differential response that is itself a
     * username-existence enumeration oracle, the exact class M4 exists to
     * close. Checking `is_author()` alone (which `WP_Query` already
     * computed correctly for both query shapes) closes that gap; the
     * author id itself is never read, echoed, or otherwise trusted here.
     */
    public function blockAuthorArchiveProbe(): void
    {
        if (!is_author()) {
            return;
        }

        wp_die(
            esc_html__('Forbidden.', 'ntdst-baseline'),
            esc_html__('Forbidden', 'ntdst-baseline'),
            ['response' => 403],
        );
    }

    /**
     * Reports XML-RPC as disabled (threat-model M3). Kept as an instance
     * method (rather than `__return_false`) so the object-identity-based
     * hook-stripping used by this module's integration tests can remove it
     * on reboot/module-disable like every other guard this service
     * installs.
     */
    public function xmlrpcDisabled(): bool
    {
        return false;
    }

    /**
     * Refuses a direct request to xmlrpc.php with a hard 403 before any
     * auth runs (brief: "REQUEST_URI ends xmlrpc.php -> status 403 + exit,
     * on init, before auth"). Deliberately not covered by the integration
     * suite (that would require a real HTTP round trip this codebase's
     * integration layer does not drive) — kept intentionally simple:
     * REQUEST_URI is only ever compared against a fixed suffix, never
     * echoed or otherwise trusted beyond that comparison.
     */
    public function blockDirectXmlrpcRequest(): void
    {
        $requestUri = is_string($_SERVER['REQUEST_URI'] ?? null)
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '';

        if ($requestUri === '' || !str_ends_with(strtok($requestUri, '?') ?: '', 'xmlrpc.php')) {
            return;
        }

        status_header(403);
        exit;
    }

    /**
     * Registered on `authenticate` TWICE, both priorities deliberately
     * chosen around WordPress core's own `authenticate` callbacks
     * (`wp_authenticate_username_password` / `wp_authenticate_email_password` /
     * `wp_authenticate_application_password`, all registered at priority 20
     * in default-filters.php:503-505; `wp_authenticate_spam_check` at 99):
     *
     * - EARLY (10, below core's 20): decides the lockout before core — or
     *   any other sub-20 `authenticate` callback (2FA/SSO) — ever runs, for
     *   every request that isn't already locked out this is a no-op
     *   pass-through of whatever `$user` core will go on to resolve.
     * - LATE (21, immediately above core's 20 but below spam-check's 99):
     *   re-asserts the SAME decision once more, this time looking only at
     *   whether the request is locked out (not at what `$user` currently
     *   holds). This is the fix for the actual bug the early-only version
     *   had: core's `wp_authenticate_username_password()` /
     *   `wp_authenticate_email_password()` only short-circuit on an
     *   incoming `WP_User` (`if ($user instanceof WP_User) return $user;`),
     *   never on an incoming `WP_Error` — so a `WP_Error` returned ONLY at
     *   priority 10 gets silently OVERWRITTEN by core's own
     *   `get_user_by()` + `wp_check_password()` result whenever the
     *   submitted credentials are otherwise correct (user.php:159-161,
     *   181-222). That is exactly the denial-path scenario the T5 contract
     *   test asserts (locked out, but with CORRECT credentials) — the
     *   early-only registration regressed it. The late (21) registration
     *   runs immediately after core's own callbacks return and always has
     *   the final word for a locked-out actor, regardless of what core (or
     *   any priority-20 callback) decided.
     *
     * Both registrations are on `authenticate` specifically (not a second
     * hook name) so the existing identity-based hook-stripping used by this
     * module's integration tests — which walks a fixed list of hook names
     * that does not include every hook this service could theoretically
     * use — continues to find and remove both registrations on
     * reboot/module-disable without requiring that list to grow.
     */
    private const LOGIN_LIMITER_EARLY_PRIORITY = 10;

    private const LOGIN_LIMITER_LATE_PRIORITY = 21;

    private function initLoginLimiter(): void
    {
        add_filter('authenticate', [$this, 'checkLoginAttempts'], self::LOGIN_LIMITER_EARLY_PRIORITY, 3);
        add_filter('authenticate', [$this, 'enforceLockoutAfterCoreAuth'], self::LOGIN_LIMITER_LATE_PRIORITY, 3);
        add_action('wp_login_failed', [$this, 'loginFailed']);
        add_action('wp_login', [$this, 'loginSuccessful']);
    }

    /**
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function checkLoginAttempts($user, string $username, string $password)
    {
        if ($this->isLockedOut()) {
            return $this->lockedOutError();
        }

        return $user;
    }

    /**
     * Re-asserts the lockout decision immediately after WordPress core's
     * own `authenticate` callbacks (priority 20) have run, so a locked-out
     * actor cannot be granted access by core silently overwriting the
     * priority-10 refusal with its own successful credential check. See
     * the docblock on `LOGIN_LIMITER_EARLY_PRIORITY`/`LOGIN_LIMITER_LATE_PRIORITY`
     * above for why this exists as a SECOND `authenticate` registration
     * rather than a different hook.
     *
     * @param \WP_User|\WP_Error|null $user
     * @return \WP_User|\WP_Error|null
     */
    public function enforceLockoutAfterCoreAuth($user, string $username, string $password)
    {
        if ($this->isLockedOut()) {
            return $this->lockedOutError();
        }

        return $user;
    }

    private function isLockedOut(): bool
    {
        $attempts = get_transient($this->attemptsTransientKey($this->clientIp()));

        return $attempts !== false && (int) $attempts >= (int) $this->config['max_login_attempts'];
    }

    private function lockedOutError(): \WP_Error
    {
        return new \WP_Error(
            'ntdst_baseline_locked_out',
            sprintf(
                'Too many failed login attempts. Please try again in %d minutes.',
                (int) ceil($this->config['lockout_duration'] / 60),
            ),
        );
    }

    /**
     * Increments the failure counter — EXCEPT once the cap has already
     * been reached, where the count is frozen instead of bumped.
     *
     * `wp_authenticate()` (pluggable.php:716-731) fires `wp_login_failed`
     * for ANY WP_Error the `authenticate` chain returns except
     * `empty_username`/`empty_password` — including THIS service's own
     * `ntdst_baseline_locked_out` refusal from checkLoginAttempts(). Left
     * unguarded, every attempt made WHILE locked out would re-increment
     * and re-`set_transient()` with a FRESH `lockout_duration` TTL, so the
     * window would never expire under sustained hammering (a
     * self-inflicted, effectively permanent lockout) — carried over from
     * the ludoluykx source. Freezing the count once it has already met the
     * cap means later locked-out attempts leave the existing transient
     * (and its TTL) untouched, so the original window still expires on
     * schedule.
     */
    public function loginFailed(string $username): void
    {
        $ip = $this->clientIp();
        $key = $this->attemptsTransientKey($ip);
        $attempts = get_transient($key);

        if ($attempts !== false && (int) $attempts >= (int) $this->config['max_login_attempts']) {
            // Already locked out — do not extend the TTL, let the
            // original window run out on its own schedule.
            return;
        }

        $attempts = $attempts === false ? 1 : ((int) $attempts + 1);

        set_transient($key, $attempts, (int) $this->config['lockout_duration']);
    }

    public function loginSuccessful(string $userLogin): void
    {
        delete_transient($this->attemptsTransientKey($this->clientIp()));
    }

    /**
     * @param mixed $error
     */
    public function genericLoginError($error): string
    {
        return 'Login credentials are incorrect. Please try again.';
    }

    private function attemptsTransientKey(string $ip): string
    {
        return 'ntdst_baseline_login_' . md5($ip);
    }

    private function clientIp(): string
    {
        return ClientIp::resolve($_SERVER, (array) $this->config['trusted_proxies']);
    }
}
