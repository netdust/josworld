<?php

declare(strict_types=1);

namespace ntdst\baseline\services;

use ntdst\baseline\support\CacheHeaderPolicy;
use ntdst\baseline\support\Config;

defined('ABSPATH') || exit;

/**
 * CacheHeadersService — M5 cache-header wiring (T16, GREEN).
 *
 * Registers the 'cache_headers' boot marker so the boot-orchestration layer (and
 * later T18's kill-switch sweep) can introspect which modules actually
 * booted via `apply_filters('ntdst/baseline/booted', [])`. This marker
 * registration is permanent — it stays even once this stub grows real
 * module logic.
 *
 * T16 wires the pure `CacheHeaderPolicy::compute()` (T15) onto
 * `template_redirect` (threat-model M5, plan.md §CacheHeadersService).
 * `applyCacheHeaders()` builds `$ctx` from live WP conditionals, computes
 * the policy, and routes ALL header/cookie/short-circuit emission through
 * the three protected seams below — this PUBLIC/PROTECTED method surface is
 * part of the contract `BaselineCacheHeadersTest.php` observes and must not
 * be renamed without the test-author's sign-off.
 *
 * IMPORTANT — why `applyCacheHeaders()` is an inert no-op when its own
 * preconditions aren't met is N/A now that it is implemented, but the
 * REASON it must never throw still applies: the REAL, container-managed
 * CacheHeadersService instance is constructed once at genuine WP boot
 * (before ANY test class runs) and stays registered on `template_redirect`
 * for the ENTIRE Integration suite process — every OTHER test file that
 * fires `do_action('template_redirect')` for its own unrelated reasons
 * (e.g. BaselineSecurityFixesTest / BaselineUserEnumTest asserting
 * SecurityService::blockAuthorArchiveProbe()'s 403) shares that one hook
 * queue. An exception thrown from this method during THEIR test run would
 * abort `do_action('template_redirect')` for them too — this is why
 * `applyCacheHeaders()`'s real body never calls `header()`/`setcookie()`/
 * `exit` directly and never throws on its own: it delegates to the three
 * seams, and `sendNotModified()`'s `exit` only fires on a genuine ETag
 * match, exactly like the ported source's own short-circuit.
 * BaselineCacheHeadersTest.php constructs its OWN `CacheHeadersService`
 * instance directly (never through the shared container/hook) and calls
 * `applyCacheHeaders()` on THAT instance explicitly, so its assertions are
 * observed in isolation, with zero risk to any other test file's shared
 * hook queue.
 *
 * WHY the surface is shaped this way (see BaselineCacheHeadersTest.php's
 * class docblock for the full rationale): PHPUnit's CLI SAPI has no working
 * `headers_list()` / `xdebug_get_headers()` in this environment (no xdebug
 * extension loaded), so raw `header()`/`setcookie()` calls made directly
 * inside a hook callback are UNOBSERVABLE from a test. This class therefore
 * factors header/cookie emission and the 304 short-circuit into separate,
 * overridable protected methods:
 *
 * - `applyCacheHeaders()` — the real `template_redirect` callback. Builds
 *   ctx from live WP conditionals, computes the policy, and delegates to
 *   the three protected seams below. NEVER call header()/setcookie()/exit
 *   directly in this method — always through the seams.
 * - `emitHeaders(array $headers): void` — wraps the `header()` loop behind
 *   a `headers_sent()` guard. Protected + overridable so a test subclass
 *   can capture the array instead of emitting real headers.
 * - `setBypassCookie(): void` — wraps the ONE `setcookie()` call for the
 *   logged-in bypass cookie (M5). Protected + overridable so a test
 *   subclass can capture the exact args passed to `setcookie()` (name,
 *   value, options array) instead of calling the real function.
 * - `sendNotModified(): void` — wraps `status_header(304); exit;` (the 304
 *   short-circuit, port of the source's `setCacheHeaders`). Protected +
 *   overridable so a test subclass can record that the short-circuit was
 *   REACHED without the real `exit` killing the PHPUnit process.
 *
 * `computeContext(): array` is public and pure-ish (reads WP conditionals,
 * returns the `$ctx` array `CacheHeaderPolicy::compute()` expects) so the
 * anonymous/logged-in/singular ctx-building can be asserted directly,
 * independent of the header-emission seam.
 *
 * NOT `final` (unlike every sibling service in this module): the
 * integration test's observable seam requires subclassing (see
 * `CacheHeadersObservableService` in BaselineCacheHeadersTest.php) to
 * override the three protected emission methods without touching real
 * header()/setcookie()/exit. This is a deliberate, contract-required
 * deviation from the module's usual `final` convention — do not re-add
 * `final` without the test-author's sign-off, it will fatal the suite.
 *
 * T17 — Purge convergence point (INV-6, ARCHITECTURE-INVARIANTS.md).
 * `do_action('ntdst/baseline/purge', string $scope, array $context = [])`
 * is the SINGLE place cache purging is decided for the whole platform.
 * ludoluykx's "Purge All" admin button fired an action nothing listened
 * to, after its provider service had been deleted — the page cache became
 * silently unpurgeable from WordPress even though the button reported
 * success. INV-6's fix: one named action, and baseline itself attaches NO
 * destructive default cache-clearing provider (no direct object-cache
 * flush, no PHP opcode-cache reset, no CDN call anywhere in this plugin)
 * — sites attach their own providers to the action at the infrastructure
 * layer (v1 providers are deliberately out of scope here, see plan.md
 * deferral).
 *
 * Two halves make the convergence point meaningful rather than a single,
 * easily-reachable DoS lever (threat-model M6):
 *  - Content hooks (`save_post` minus revisions, `deleted_post`,
 *    `switch_theme`, `wp_update_nav_menu`) fire scope `content`
 *    automatically — no capability check needed, WordPress's own
 *    content-mutation hooks are already capability-gated upstream.
 *  - The manual trigger (`ntdst/api_data/baseline_purge` handler + the
 *    admin-bar node) fires scope `all` and MUST check
 *    `current_user_can('manage_options')`, failing closed with an error
 *    envelope otherwise (INV-4). `baseline_purge` MUST NEVER be added to
 *    `ntdst/api/public_actions` — the router only default-denies
 *    logged-out callers when the action is absent from that allowlist
 *    (INV-2); listing it there would silently reopen the DoS gap this
 *    task closes.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
class CacheHeadersService
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    private const DEFAULTS = [
        'max_age' => 3600,
        'singular_factor' => 24,
        'archive_factor' => 6,
        'etag' => true,
        'bypass_cookie' => 'ntdst_nocache',
        // T17 fix-round #2: rate-limit window (seconds) + ceiling for
        // same-status-repeat content-scope purge fan-out. See
        // `firePurge()`'s docblock for the full rationale. 0 (or any
        // non-positive) `purge_debounce_seconds` disables the limit.
        'purge_debounce_seconds' => 30,
        'purge_debounce_limit' => 5,
    ];

    /**
     * Allow-listed `$scope` values for `ntdst/baseline/purge` (T17 brief,
     * line 14). Anything else is rejected by the manual-purge handler.
     */
    private const VALID_PURGE_SCOPES = ['content', 'all'];

    /**
     * Request-scoped marker of post IDs whose `post_status` just changed,
     * set by `onTransitionPostStatus()` (which fires before `save_post`)
     * and consumed/cleared by `onSavePost()` for that same save. Never
     * persisted (no transient) — a status transition is inherently a
     * single-request fact, not something that needs to survive across
     * requests the way the rate-limit counters do.
     *
     * @var array<int, true>
     */
    private array $postsWithPendingStatusTransition = [];

    public function __construct()
    {
        add_filter('ntdst/baseline/booted', static fn(array $booted): array => [...$booted, 'cache_headers']);

        $this->config = Config::merge(self::DEFAULTS, (array) apply_filters('ntdst/baseline/cache_headers/config', []));

        // T16 wiring: register the real template_redirect callback. The
        // constructor itself must NEVER throw/fail — BaselineBootTest.php
        // (pre-existing, must stay green) resolves this class as part of
        // real WP boot for every other integration test file that shares
        // the bootstrap; a throwing constructor here would fatally break
        // the ENTIRE Integration suite, not just this task's test. The
        // sentinel failures live inside applyCacheHeaders() and its seam
        // methods below instead, so they fire only when this contract is
        // actually exercised.
        add_action('template_redirect', [$this, 'applyCacheHeaders']);

        // T17 wiring: content-change hooks converge on the ONE purge
        // action (INV-6). Same never-throw discipline as above — every one
        // of these callbacks is a thin wrapper around firePurge(), which
        // itself never throws (do_action() against an empty/absent
        // listener list is a safe no-op).
        //
        // transition_post_status MUST be registered so onSavePost() can
        // tell "status just changed" from "same-status repeat save" — see
        // onTransitionPostStatus()'s docblock for why this hook (not
        // save_post's own $update arg) is the correct signal, and
        // firePurge()'s docblock for why that distinction is fix-round #2's
        // fix (a status transition must always purge; only a same-status
        // repeat save is rate-limited).
        //
        // NOT added to the test-side ALL_PURGE_HOOKS strip list (this
        // codebase's cross-reboot hygiene mechanism) because doing so is
        // harmless by construction, not because it was overlooked: every
        // stale instance's onTransitionPostStatus() writes only into THAT
        // instance's own $postsWithPendingStatusTransition property, never
        // shared state, and only the CURRENTLY-live instance's onSavePost()
        // (registered on the identity-stripped save_post hook, so always
        // exactly one live registration) ever reads its own copy. A stale
        // instance's redundant write is wasted work, never a wrong read.
        add_action('transition_post_status', [$this, 'onTransitionPostStatus'], 10, 3);
        add_action('save_post', [$this, 'onSavePost'], 10, 2);
        add_action('deleted_post', [$this, 'onDeletedPost'], 10, 2);
        add_action('switch_theme', [$this, 'onSwitchTheme']);
        add_action('wp_update_nav_menu', [$this, 'onNavMenuUpdate']);

        // T17 wiring: the manual-purge convergence door. Capability- and
        // router-gated (see the class docblock's INV-2/INV-4 note).
        //
        // Unconditional add_filter(), same as every other hook in this
        // constructor (fix-round finding #1, superseded/corrected):
        // `ntdst/api_data/baseline_purge` IS identity-strippable —
        // StripsServiceHooksByIdentity::stripServiceHooks() walks
        // $wp_filter[$hook]->callbacks generically, and WordPress unifies
        // actions and filters through that same $wp_filter global, so
        // there is no action-vs-filter distinction for stripping. The
        // original double-firing bug (fixed here in a prior fix-round
        // pass) was a TEST-LIST omission — this hook was simply missing
        // from BaselinePurgeTest::ALL_PURGE_HOOKS, not a production
        // registration-identity problem — and is being corrected in the
        // test-author's own file, not here. No guard belongs in this
        // constructor; see the `stripServiceHooks()`/`rebootService()`
        // contract every other hook in this class already relies on.
        add_filter('ntdst/api_data/baseline_purge', [$this, 'handleManualPurge'], 10, 2);

        add_action('admin_bar_menu', [$this, 'registerPurgeAdminBarNode'], 100);
    }

    /**
     * Builds the `$ctx` array `CacheHeaderPolicy::compute()` expects, from
     * the real WP conditionals for the current request (plan.md
     * §CacheHeadersService, lines 704-710).
     *
     * @return array<string, mixed>
     */
    public function computeContext(): array
    {
        global $wp_query;

        $modified = null;

        if (is_singular()) {
            $postId = get_queried_object_id();

            if ($postId > 0) {
                $modifiedGmt = get_post_field('post_modified_gmt', $postId);

                if (is_string($modifiedGmt) && $modifiedGmt !== '' && $modifiedGmt !== '0000-00-00 00:00:00') {
                    $timestamp = strtotime($modifiedGmt . ' UTC');
                    $modified = $timestamp !== false ? $timestamp : null;
                }
            }
        }

        return [
            'logged_in' => is_user_logged_in(),
            'is_404' => is_404(),
            'is_search' => is_search(),
            'is_feed' => is_feed(),
            'is_singular' => is_singular(),
            'is_front' => is_front_page(),
            'is_archive' => is_archive(),
            // Fix-round FINDING I-1: WordPress's sitemap server and
            // robots.txt handler both run on `template_redirect` (sitemaps:
            // priority 10, registered later than ours at `init` — see
            // CacheHeaderPolicy.php's docblock for the full ordering proof)
            // but leave `is_front_page()` / `is_home()` true on a
            // `show_on_front=posts` site, so without these two flags both
            // paths fell through to the public branch below. `is_robots()`
            // is WP core's own conditional for `/robots.txt`; the two
            // `sitemap`/`sitemap-stylesheet` query vars are what core's
            // sitemap server sets for the index and every sub-type/
            // stylesheet request (`WP_Sitemaps_Registry`/`WP_Sitemaps::
            // render_sitemaps()`).
            'is_robots' => is_robots(),
            'is_sitemap' => (bool) get_query_var('sitemap') || (bool) get_query_var('sitemap-stylesheet'),
            'modified' => $modified,
        ];
    }

    /**
     * The real `template_redirect` callback (T16). MUST call
     * `computeContext()`, `CacheHeaderPolicy::compute()`, then delegate
     * emission to `emitHeaders()` / `setBypassCookie()` / `sendNotModified()`
     * — never call `header()`/`setcookie()`/`exit` directly here.
     *
     * Also emits the M5 `X-Cache-Bypass` header + bypass cookie for a
     * logged-in request (via `emitHeaders()` / `setBypassCookie()`, never
     * directly), and takes the 304 short-circuit (via `sendNotModified()`)
     * when the computed ETag matches `HTTP_IF_NONE_MATCH` per RFC 9110
     * §8.8.3.2 weak comparison (`CacheHeaderPolicy::matches()`).
     *
     * BUG-FIX ROUND (.superpowers/sdd/tasks/etag-diagnosis.md) — TWO
     * independent defects fixed here, do not "simplify" either back out:
     *
     * DEFECT 1 (host-independent, broke 304 on EVERY host): WordPress
     * core's `wp_magic_quotes()` (wp-includes/load.php) runs `addslashes()`
     * over the ENTIRE `$_SERVER` superglobal on every request, UNCONDITIONALLY,
     * during bootstrap (well before `template_redirect` fires) — this is not
     * opt-in, not plugin-specific, not host-specific. A client's
     * `If-None-Match: "abc"` therefore arrives here as the escaped string
     * `\"abc\"`, not the clean `"abc"` nginx/HTTP saw. Comparing that raw
     * escaped value against our clean computed ETag always failed, byte for
     * byte, regardless of comparison algorithm. `wp_unslash()` is core's own
     * documented, idiomatic reversal of this — this codebase already uses
     * that exact pattern elsewhere (MetaboxGenerator.php) before touching
     * `$_POST`; `$_SERVER['HTTP_IF_NONE_MATCH']` needs the same treatment.
     * DO NOT read `$_SERVER['HTTP_*']` anywhere in this codebase without
     * `wp_unslash()` first — it is silently magic-quoted on every request.
     *
     * DEFECT 2 (portability, most hosts once #1 is fixed): RFC 9110
     * §8.8.3.2 mandates WEAK comparison for If-None-Match — `W/"x"` and
     * `"x"` MUST match. nginx (and most gzip-capable reverse proxies)
     * weakens a strong ETag to `W/"…"` whenever it compresses the response,
     * so a client echoing that weak validator back would never match a
     * naive strong/exact comparison. `If-None-Match` may also be a
     * comma-separated list, or the literal `*`. `CacheHeaderPolicy::matches()`
     * implements the full RFC-correct comparison (see its own docblock);
     * `hash_equals()` is preserved INSIDE that helper for the final
     * per-tag byte comparison (fix-round finding #3's rationale still
     * applies — cheap insurance against a future ETag derived from
     * anything secret).
     */
    public function applyCacheHeaders(): void
    {
        $ctx = $this->computeContext();
        $headers = CacheHeaderPolicy::compute($ctx, $this->config);

        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH']))
            : null;

        if (
            isset($headers['ETag'])
            && is_string($ifNoneMatch)
            && $ifNoneMatch !== ''
            && CacheHeaderPolicy::matches($headers['ETag'], $ifNoneMatch)
        ) {
            $this->sendNotModified();

            return;
        }

        if ($ctx['logged_in'] === true) {
            $headers['X-Cache-Bypass'] = '1';
        }

        $this->emitHeaders($headers);

        if ($ctx['logged_in'] === true) {
            $this->setBypassCookie();
        }
    }

    /**
     * Emits each computed header via `header()` behind a `headers_sent()`
     * guard. Protected + overridable seam — see class docblock.
     *
     * @param array<string, string> $headers
     */
    protected function emitHeaders(array $headers): void
    {
        if (headers_sent()) {
            return;
        }

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * Sets the logged-in cache-bypass cookie (M5) via `setcookie()`.
     * Protected + overridable seam — see class docblock. Cookie name comes
     * from `config['bypass_cookie']` (default `ntdst_nocache`). Collision
     * guard (asserted by BaselineCacheHeadersTest's source-level grep):
     * the ported source's original cookie-name literal, which collides
     * with a WP core auth-cookie prefix, must never appear anywhere in
     * this plugin directory's source — including in comments.
     */
    protected function setBypassCookie(): void
    {
        $name = (string) ($this->config['bypass_cookie'] ?? 'ntdst_nocache');

        setcookie($name, '1', [
            'expires' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => is_ssl(),
        ]);
    }

    /**
     * Sends the 304 short-circuit (`status_header(304); exit;`) when the
     * computed ETag matches `$_SERVER['HTTP_IF_NONE_MATCH']`. Protected +
     * overridable seam — see class docblock; a test subclass overrides this
     * to record that the short-circuit was reached WITHOUT letting a real
     * `exit` kill the PHPUnit process.
     */
    protected function sendNotModified(): void
    {
        status_header(304);
        exit;
    }

    // -----------------------------------------------------------------
    // T17 — Purge convergence point (INV-6). See class docblock.
    // -----------------------------------------------------------------

    /**
     * `transition_post_status` — fires strictly BEFORE `save_post` within
     * the same `wp_insert_post()`/`wp_update_post()` call
     * (wp-includes/post.php: `wp_transition_post_status()` at line 5083 /
     * 5355, `do_action('save_post', ...)` at line 5193 / 5367). Used ONLY
     * to mark "this post just changed status" for `onSavePost()` to read —
     * fires no purge itself, so it needs no revision guard of its own
     * (the revision guard lives on `onSavePost()`, which still runs for
     * every save regardless of status change).
     *
     * @param string   $newStatus
     * @param string   $oldStatus
     * @param \WP_Post $post
     */
    public function onTransitionPostStatus(string $newStatus, string $oldStatus, \WP_Post $post): void
    {
        if ($newStatus !== $oldStatus) {
            $this->postsWithPendingStatusTransition[$post->ID] = true;
        }
    }

    /**
     * `save_post` — fires scope `content`, EXCEPT for a revision save
     * (brief line 10 / test `testSavingARevisionFiresPurgeZeroTimes`).
     * `wp_is_post_revision()` is WordPress's own guard for exactly this:
     * it returns the parent post ID when `$post` is an autosave/revision
     * row, `false` otherwise — checking it here is the revision-guard
     * denial path itself, not an approximation of it.
     *
     * Fix-round finding #2 (coordinator ruling): a status transition
     * (draft->publish, publish->draft, any change) ALWAYS fires, bypassing
     * the content-purge rate limit — see `firePurge()`'s docblock for why
     * a pure time-bucketed throttle is wrong here (it would silently
     * swallow the one purge a cache provider can least afford to miss). A
     * same-status repeat save (the low-privilege REST-loop lever the
     * security audit found: hammering `wp_update_post` on an
     * already-published post with no status change) still goes through
     * the normal rate limit in `firePurge()`.
     *
     * @param int      $postId
     * @param \WP_Post $post
     */
    public function onSavePost(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        $isStatusTransition = isset($this->postsWithPendingStatusTransition[$postId]);
        unset($this->postsWithPendingStatusTransition[$postId]);

        $this->firePurge('content', ['post_id' => $postId], $isStatusTransition);
    }

    /**
     * `deleted_post` — fires scope `content`, EXCEPT for a revision
     * deletion (fix-round finding #4). WordPress fires `deleted_post`
     * AFTER the row is already removed from `$wpdb->posts`
     * (wp-includes/post.php `wp_delete_post()`, `do_action('deleted_post',
     * $post_id, $post)` at line 3934, called post-`$wpdb->delete()`), so
     * `wp_is_post_revision($postId)` (a fresh DB lookup) would always
     * return `false` here regardless of what was just deleted — it cannot
     * be reused the way `onSavePost()` reuses it. WordPress passes the
     * FULL `WP_Post` object it captured before deletion as the second
     * argument specifically so callers on this hook can still inspect
     * what was deleted; `$post->post_type === 'revision'` is the correct
     * check on THIS hook.
     *
     * Without this guard, this plugin's own `MaintenanceService` revision
     * pruning (deletes up to `batch` revisions per cron run) would fire a
     * full `content`-scope purge PER PRUNED REVISION — the exact purge
     * storm INV-6's revision guard exists to prevent, just never extended
     * to the delete path until now.
     *
     * @param int      $postId
     * @param \WP_Post $post
     */
    public function onDeletedPost(int $postId, \WP_Post $post): void
    {
        if ($post->post_type === 'revision') {
            return;
        }

        $this->firePurge('content', ['post_id' => $postId]);
    }

    public function onSwitchTheme(): void
    {
        $this->firePurge('content');
    }

    /**
     * @param int $menuId
     */
    public function onNavMenuUpdate($menuId = 0): void
    {
        $this->firePurge('content');
    }

    /**
     * The `ntdst/api_data/baseline_purge` handler — the manual-purge door
     * onto the convergence action (scope `all`). Fail-closed: rejects
     * anyone without `manage_options` (AC-6, INV-4) and rejects any
     * `$scope` outside the allow-list, BEFORE the action ever fires.
     * MUST NOT be added to `ntdst/api/public_actions` (INV-2) — see the
     * class docblock.
     *
     * @param mixed                $data
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function handleManualPurge($data, array $params = []): array
    {
        if (!current_user_can('manage_options')) {
            return $this->purgeErrorEnvelope(
                'Insufficient permissions to purge the cache.',
                'forbidden',
            );
        }

        $scope = sanitize_text_field((string) ($params['scope'] ?? 'all'));

        if (!in_array($scope, self::VALID_PURGE_SCOPES, true)) {
            return $this->purgeErrorEnvelope(
                'Invalid purge scope requested.',
                'invalid_scope',
            );
        }

        $this->firePurge($scope);

        return [
            'success' => true,
            'data' => ['scope' => $scope],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purgeErrorEnvelope(string $message, string $code): array
    {
        return [
            'success' => false,
            'data' => [
                'message' => $message,
                'code' => $code,
            ],
        ];
    }

    /**
     * Admin-bar "Purge cache" node — the second manual-purge entry point
     * (brief line 13), gated behind the SAME `manage_options` check as the
     * API handler. Mirrors the existing `SectorRegistry::registerAdminBarIndicator()`
     * convention (top-secondary parent, `current_user_can` guard before
     * building anything).
     *
     * The node itself only needs to exist and be capability-gated for this
     * task's contract (brief line 13; not directly asserted by
     * BaselinePurgeTest, which the author's report notes is "out of this
     * task's brief-required test contract"). Its `href` intentionally
     * points at the front-end site rather than an unbuilt click-through
     * endpoint — wiring a JS/AJAX purge trigger onto this node is future
     * scope, not something this task's contract requires.
     *
     * @param \WP_Admin_Bar $wpAdminBar
     */
    public function registerPurgeAdminBarNode($wpAdminBar): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // NOTE (final-review M-4): this node is an INDICATOR, not a trigger.
        // Clicking it does nothing — wiring a real click-through needs a
        // nonce fetch + POST to `ntdst/api_data/baseline_purge`, i.e. an
        // admin JS asset this plugin deliberately does not yet ship.
        // Shipping a button that silently no-ops is the exact ludoluykx
        // failure INV-6 exists to prevent, so the label says what it is:
        // the working door is the API action, and this only advertises that
        // the convergence point is live. Wire it or drop it — do not leave
        // it labelled as an action it cannot perform.
        $wpAdminBar->add_node([
            'id' => 'ntdst-baseline-purge',
            'title' => esc_html__('Cache purge: API only', 'ntdst-baseline'),
            'parent' => 'top-secondary',
            'meta' => [
                'title' => __(
                    'The ntdst/baseline/purge convergence point is active. Trigger a full purge via the '
                    . 'baseline_purge API action (requires manage_options); this indicator is not clickable.',
                    'ntdst-baseline',
                ),
            ],
        ]);
    }

    /**
     * The single call-site that fires the convergence action. Nothing in
     * this plugin may call a direct object-cache flush, a PHP opcode-cache
     * reset, or a CDN purge API directly — this method (and only this
     * method) fires `ntdst/baseline/purge`, so a source-level grep for
     * those forbidden calls anywhere under the plugin directory staying
     * empty IS the proof that "no destructive default provider" holds
     * (see `testNoDestructiveCacheCallAnywhereInPluginSource`).
     *
     * T17 fix-round finding #2 — content-scope rate limit: any
     * `edit_posts` actor (Author+, well below `manage_options`) can
     * trigger a `content`-scope purge on every non-revision `save_post`,
     * with no throttle, trivially loopable via the REST `/wp/v2/posts`
     * endpoint (repeatedly re-saving an ALREADY-published post with no
     * status change). Impact is zero TODAY (baseline ships no provider —
     * see the class docblock), but INV-6 exists precisely so sites WILL
     * attach one (nginx/FastCGI/CDN purge); at that point this becomes a
     * low-privilege origin-overload lever. The revision guard in
     * `onSavePost()`/`onDeletedPost()` is an authorization/correctness
     * gate and is unaffected by this — this is purely about FREQUENCY.
     *
     * STATE-TRANSITION AWARE (coordinator ruling, second fix-round
     * amendment): a `$bypassRateLimit = true` fire (any `post_status`
     * transition — draft->publish, publish->draft, any change, signalled
     * by `onTransitionPostStatus()`/`onSavePost()`) ALWAYS fires,
     * unconditionally, never counted against or blocked by the limit. A
     * plain time/count-bucketed throttle with NO transition awareness was
     * tried and rejected during this fix round: it silently swallowed a
     * draft->publish fire that landed in the same short window as an
     * earlier draft-insert fire on the SAME post — exactly what
     * `testSavePostOnPublishedPostFiresContentPurgeExactlyOnce` exercises
     * (`wp_insert_post()` as a draft immediately followed by
     * `wp_update_post()` publishing it) — which is a correctness bug, not
     * throttling: a cache provider can least afford to miss the one purge
     * that flips a post from draft/not-found to publicly published. Only a
     * SAME-STATUS repeat save (the actual REST-loop lever the audit found)
     * is subject to the rate limit below.
     *
     * The rate limit itself is a COUNTER-WITH-CEILING (allow up to N fires
     * per window, then throttle) — mirrors `Endpoints::checkRateLimit()`'s
     * shape (Endpoints.php) exactly, get_transient()/set_transient()
     * holding a count, not a boolean lock — the established precedent in
     * this codebase for an allow-N-then-throttle tradeoff.
     *
     * One bucket per (scope, post_id). Keying by post_id (when present in
     * $context) means throttling one post's rapid re-saves never
     * suppresses a genuinely different post's purge, or a site-wide event
     * (switch_theme/nav-menu update, which carry no post_id and get their
     * own single global bucket, and never carry a status transition either
     * — those two hooks always pass `$bypassRateLimit = false`).
     *
     * `all`-scope (the capability-gated manual trigger) is deliberately
     * NEVER throttled here — it is already gated behind `manage_options`
     * in `handleManualPurge()`, so no low-privilege fan-out is possible on
     * that path; a manual purge should always fire when an admin asks for
     * it.
     *
     * A site attaching a real provider to `ntdst/baseline/purge` inherits
     * this rate limit automatically — the provider always sees every
     * status-transition fire, plus fires up to the ceiling per window for
     * same-status repeats, never unbounded per-save frequency. Both the
     * window and the ceiling are configurable via `purge_debounce_seconds`
     * / `purge_debounce_limit` in `ntdst/baseline/cache_headers/config`
     * (defaults: 30s window, 5 fires per window; 0/negative window
     * disables the limit entirely).
     *
     * @param array<string, mixed> $context
     */
    private function firePurge(string $scope, array $context = [], bool $bypassRateLimit = false): void
    {
        if (
            $scope === 'content'
            && !$bypassRateLimit
            && $this->isContentPurgeRateLimited($context)
        ) {
            return;
        }

        do_action('ntdst/baseline/purge', $scope, $context);
    }

    /**
     * Returns true when this bucket has already fired `purge_debounce_limit`
     * times within the current `purge_debounce_seconds` window — i.e. this
     * call should be SUPPRESSED. Returns false (and increments the
     * counter, starting a fresh window on the first call for a bucket) for
     * every fire up to and including the ceiling. Only ever consulted for
     * same-status repeat saves — see `firePurge()`'s docblock for why a
     * status transition never reaches this method at all.
     *
     * @param array<string, mixed> $context
     */
    private function isContentPurgeRateLimited(array $context): bool
    {
        $window = (int) ($this->config['purge_debounce_seconds'] ?? 0);

        if ($window <= 0) {
            return false;
        }

        $limit = max(1, (int) ($this->config['purge_debounce_limit'] ?? 5));

        $bucket = isset($context['post_id']) ? 'post_' . (int) $context['post_id'] : 'global';
        $key = 'ntdst_baseline_purge_rl_' . md5($bucket);

        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return true;
        }

        set_transient($key, $count + 1, $window);

        return false;
    }
}
