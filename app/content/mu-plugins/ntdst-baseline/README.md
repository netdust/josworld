# ntdst-baseline

> **Vendored per-site, kept IDENTICAL across sites.** There is no shared
> package yet; each site carries a byte-identical copy of this plugin
> (`daan`: `web/app/mu-plugins/ntdst-baseline/`, `josworld`:
> `app/content/mu-plugins/ntdst-baseline/`). Converged 2026-08-11 by
> merging the superset of both copies: josworld's gate-required hardening
> (sanitized `REQUEST_URI` / `If-None-Match` reads, `wp_kses()` over the
> filtered head-tag loop, the `ntdst/baseline/schema/*` filter rename,
> phpcs/phpstan compliance) plus daan's `purge_exclude_post_types`
> cache-purge exclusion (S-2) and its user-enum flag documentation.
>
> **A fix made in one copy must land in the other in the same change.**
> Behavioural tests live where the behaviour's threat is real (e.g.
> `BaselinePurgeExclusionTest` in daan's suite, the input-handling and
> filter-namespace tests in josworld's); the identity of the copies is what
> carries the coverage across.

**What it is.** The third mu-plugin in the stack, alongside `ntdst-core` (the
DI/data/routing framework, shared across every Netdust project) and
`<project>-core` (this site's business logic — e.g. `daan-core`). Where
`ntdst-core` provides the framework and `<project>-core` provides the
domain, `ntdst-baseline` fills the gap in between: the generic
WordPress-hardening and housekeeping work every site needs and none of
them should have to hand-roll — security headers/login-throttling, head
cleanup, SEO meta/robots, JSON-LD schema, scheduled maintenance, and cache
headers/purge. It is six independent modules, each a single service class
under `services/`, booted (or not) individually.

Load order: `ntdst-baseline.php` wires everything on `plugins_loaded:5`,
deliberately after `ntdst-core` (loaded earlier by mu-plugin filename
sort) has had a chance to register `ntdst_get()`. If the framework
function is absent, boot is skipped entirely and silently — this plugin
must never fatal a site that doesn't have `ntdst-core` present (FR-20/AC-9).

---

## The config contract

Every knob in this plugin is a WordPress filter, applied by the consuming
site — never a hardcoded value inside this directory.

**Master toggle** — `ntdst/baseline/modules` (an array of `module_key =>
bool`, merged over defaults where all six start `true`):

```php
add_filter('ntdst/baseline/modules', function (array $modules): array {
    $modules['maintenance'] = false; // e.g. disable if the site already runs its own cron cleanup
    return $modules;
});
```

**Per-module config** — one filter per module, literal names (never
derived from the key programmatically — read them off this table, don't
guess):

| Module key | Class | Config filter |
|---|---|---|
| `security` | `SecurityService` | `ntdst/baseline/security/config` |
| `head_cleanup` | `HeadCleanupService` | `ntdst/baseline/head_cleanup/config` |
| `seo` | `SeoService` | `ntdst/baseline/seo/config` |
| `schema` | `SchemaService` | `ntdst/baseline/schema/config` |
| `maintenance` | `MaintenanceService` | `ntdst/baseline/maintenance/config` |
| `cache_headers` | `CacheHeadersService` | `ntdst/baseline/cache_headers/config` |

**Content-extension filters** — beyond the per-module `…/config` above,
two modules expose filters for changing what they *emit*. These are the
extension points a site actually hooks to add its own structured data or
head tags:

| Filter | Args | What it does |
|---|---|---|
| `ntdst/baseline/seo/meta` | `string[] $tags` | The final `<meta>`/`<link>` tag strings, just before they are echoed on `wp_head`. **Anything added here is passed through `wp_kses()` with a `<meta>`/`<link>` allow-list** — post-filter content cannot be assumed pre-escaped, so a tag that is not one of those two elements will not survive. |
| `ntdst/baseline/schema/main_entity` | `array $entity` | The site's main-entity (Organization/Person) node. |
| `ntdst/baseline/schema/website` | `array $website` | The WebSite node. |
| `ntdst/baseline/schema/article` | `array $article`, `WP_Post $post` | The Article node on a singular post. |
| `ntdst/baseline/schema/webpage` | `array $webpage`, `WP_Post $post` | The WebPage node on a singular page. |
| `ntdst/baseline/schema/post_type/{post_type}` | `array\|null $schema`, `WP_Post $post` | Per-CPT schema. Returns `null` by default; return an array to emit a node for that post type. The `{post_type}` segment is interpolated, so the literal hook name for a `case` CPT is `ntdst/baseline/schema/post_type/case`. |
| `ntdst/baseline/schema/custom` | `array $schemas`, `WP_Post\|null $post` | Append entirely custom schema objects. |
| `ntdst/baseline/schema/faq_items` | `array $items`, `WP_Post\|null $post` | FAQPage items; emits nothing when empty. |

> **Renamed 2026-08-03.** The seven `schema/*` filters above previously
> fired under `ntdst/schema/*` — the one place this plugin's naming was
> inconsistent with itself, since `SchemaService`'s own config filter
> already used `ntdst/baseline/`. They now match the convention, and
> `tests/Unit/BaselineFilterNamespaceTest.php` fails the build if a future
> filter drifts back out of the `ntdst/baseline/` namespace. **If you are
> updating a site that hooked the old names, rename there too — a stale
> hook name does not error, it just silently stops firing.**

Each config filter receives an array and must return an array; unset keys
fall back to that module's internal `DEFAULTS` via `Config::merge()`
(shallow-merge — a key you don't set is untouched, not wiped).

**Worked example** — this is `daan-core.php`'s actual shape (the real
reference to copy from, not an invented sketch):

```php
// Module toggle — registered even when unchanged, so the toggle point is
// discoverable here without needing to already know the filter name exists.
add_filter('ntdst/baseline/modules', function (array $modules): array {
    return $modules;
});

// Schema config — only name/type set from certain, factual site knowledge.
add_filter('ntdst/baseline/schema/config', function (array $config): array {
    return array_merge($config, [
        'type' => 'person',
        'name' => 'Daan',
    ]);
});
```

---

## The hard rule: this plugin is never edited per site

`ntdst-baseline` ships shared across every Netdust site the same way
`ntdst-core` does. **No site-specific value — no phone number, address,
social URL, domain, page ID, or anything else — is ever hardcoded inside
this plugin directory.** Every value a site needs comes from that site's
own `<project>-core` via the filters above.

*One named exception: this README.* The worked example above quotes daan's
real `daan-core.php` filter verbatim — including `'name' => 'Daan'` — because
a concrete example you can copy beats an invented sketch. The rule governs
executable code and config: a `.php` file under this directory must carry no
site-specific value. Illustrative markdown and code comments naming a real
site are fine, and the INV-6 bypass grep is `.php`-scoped for the same reason.

If you find yourself editing
a file under `web/app/mu-plugins/ntdst-baseline/` to make one site behave
differently, stop — that value belongs in a config filter in
`<project>-core.php` instead. Site-only content living outside this
plugin is what keeps it upgradeable in place across every site that uses
it.

---

## Empty-safe defaults

Every module's `DEFAULTS` constant is empty/off/null for anything that
could plausibly be site-specific data — no example values baked in
anywhere. This is a deliberate rule (M8 in the plan's threat model), not
an oversight, and it exists because of a real failure mode in the plugin
this one replaces: the ported `SchemaService`'s `DEFAULTS` shipped a
placeholder phone number, a placeholder address, a "John Doe" author, and
dummy social-profile URLs. The moment a site set a schema `type` without
also filling in every other field, that placeholder data went out as
**live JSON-LD** — structured data search engines and other consumers
would treat as fact. `SchemaService` in this plugin fixes that: `type`
defaults to `null`, and when it's unconfigured, `outputSchema()` emits
nothing at all (the module still registers its `wp_head` hook so it stays
introspectable/toggleable, it just produces no output). Auto-fill is
allowed **only** for `name`/`url`/`description` from real site values
(`bloginfo()`/`home_url()`) — never for contact, address, or phone data.
The same discipline applies across every module: an unset config key
means "don't do this," never "do this with a fake value."

---

## Per-module summary

### SecurityService

Fills the gap of: pre-auth brute-force protection, generic login-error
disclosure, standard security response headers, and closing several
default-WordPress attack-surface/enumeration leaks. Key config keys:
`limit_login_attempts` / `max_login_attempts` (default 5) /
`lockout_duration` (default 900s), `generic_login_errors`,
`security_headers`, `hsts` (`{enabled, include_subdomains, max_age}`),
`csp` (a header-value list), `disable_user_enumeration`,
`disable_xmlrpc`, `disable_file_editor`, `trusted_proxies` (IP/CIDR list
used to resolve the real client IP behind a proxy for the login limiter).
Notable default-OFF toggle: **`hsts.enabled` defaults to `false`** — HSTS
is a one-way door (a browser that receives it will refuse plaintext HTTP
for the configured duration), so it is opt-in per site rather than a
baseline default, unlike every other security header this module sends
unconditionally.

### HeadCleanupService

Fills the gap of: stripping WordPress's default `<head>` bloat and
version-fingerprinting output (feed/RSD/wlwmanifest discovery links,
shortlink, adjacent-post links, the generator meta tag, REST/oEmbed
discovery links, emoji script/styles, embeds). Key config keys:
`disable_emojis`, `disable_embeds`, `clean_head`, `remove_version_strings`,
`remove_dashicons`, `remove_jquery`, `remove_block_styles`. Notable
default-OFF toggles: **`remove_jquery` and `remove_block_styles` both
default to `false`** (a deliberate rework vs. the plugin this replaces,
which defaulted both `true`) — either one can break a YOOtheme-dependent
or Gutenberg-block-styled site outright, so they're opt-in per site
rather than assumed safe everywhere. Admin-only requests skip this module
entirely (`is_admin()` short-circuit before any hook registers).

### SeoService

Fills the gap of: baseline SEO meta tags (title/description/canonical/
Open Graph) and a generated `robots.txt`. Key config keys: `meta_tags`,
`robots_txt`, `sitemap_users_provider` (default `false` — no sitemap
provider ships by default), `robots_rules` (extra disallow rules, merged
with the built-in `wp-login.php`/`wp-admin/` disallow pair, which is
derived from `admin_url()` rather than hardcoded so it works under a
Bedrock `/wp/` web root too). Also patches a canonical-URL gap: when core's
own `rel_canonical()` output is removed by something else on the site,
this module emits its own singular canonical from `get_permalink()` as a
fallback so a canonical is never silently absent.

**Open Graph / Twitter Card.** `og_tags` (default `true`) gates the whole
tag set — when `false`, zero `og:`/`twitter:` tags are emitted. When on,
`outputMetaTags()` additionally emits `og:title`, `og:description` (the
same derivation as the plain `<meta name="description">` tag — never a
second, divergent description), `og:url` (the real canonical URL for the
current request, resolved independently of whether core's own
`rel_canonical()` tag fired, since `og:url` is a different tag and must
never go empty just because the `<link rel="canonical">` de-dupe above
stayed silent), `og:type` (`article` on singular content, `website`
otherwise), `og:site_name`, and on singular content
`article:published_time` / `article:modified_time`. `og:title` on
singular content uses the raw `post_title` (not `get_the_title()`, whose
`the_title` filter runs `wptexturize()` and would rewrite an editor's
literal `"`/`<` before `esc_attr()` ever sees it) — same rationale as
`resolveDescription()`'s use of raw post data.

`og:image` is the one M8 empty-safe DENIAL in this module (see "Empty-safe
defaults" above): it is emitted **only** when the current singular post has
a real featured image, or the site has set the `og_image_fallback` config
key to a URL — a real featured image always wins over the fallback when
both are present. With neither, the tag is **omitted entirely**, never a
placeholder or stock image. `twitter:card` tracks the same signal:
`summary_large_image` when an `og:image` is present, `summary` otherwise.
`og_image_fallback` defaults to `''` (unset, per the empty-safe rule — no
example URL baked in).

### SchemaService

Fills the gap of: JSON-LD structured data (Person/Organization,
Article/CreativeWork per post, WebSite). Key config keys: `type` (must be
explicitly set — no default entity type), `name`/`url`/`description`
(auto-filled from real site values when left empty), `logo`,
`social_profiles`, `business`, `person`, `pages` (an ID map, e.g.
`['contact' => 12, 'about' => 5]` — page detection is by explicit ID, not
a slug guess, because a slug-list approach silently misses any page not
named exactly `contact`/`kontakt`/etc.). See "Empty-safe defaults" above
for why none of these carry example values. Output goes through
`wp_json_encode($schema, JSON_UNESCAPED_UNICODE)` — **`JSON_UNESCAPED_SLASHES`
is deliberately NOT set**, because it strips the `/` → `\/` escaping that
is the only thing preventing a `</script>` sequence inside post-derived
content (a title, an excerpt) from closing the `<script
type="application/ld+json">` block early and injecting executable markup.
Do not add that flag back for output-readability reasons; it reopens a
stored-XSS path, regression-locked by
`BaselineSchemaTest::testArticleTitleWithScriptCloseTagCannotBreakOutOfLdJsonBlock`.

### MaintenanceService

Fills the gap of: scheduled WP-Cron cleanup (old post revisions, spam
comments) and a configurable heartbeat interval. Key config keys:
`limit_post_revisions` (default 5, applied via the `wp_revisions_to_keep`
filter), `heartbeat_interval` (default 60s), `cleanup.revisions` /
`cleanup.spam` / `cleanup.transients` (each a bool, all default `true`),
`cleanup.age_days` (default 30), `cleanup.batch` (default 100, caps rows
deleted per cron run), `preconnect_domains` (resource-hint domains,
empty by default — site-specific, never guessed). No `autosave_interval`
key exists — WordPress core has no `apply_filters('autosave_interval',
...)` anywhere to hook (verified against WP core source); a config key
that looked configurable but did nothing was judged worse than no key at
all, so it was deliberately never added. If a site needs non-default
autosave cadence, that's a `define('AUTOSAVE_INTERVAL', ...)` in
`wp-config.php`, outside this plugin's scope.

### CacheHeadersService

Fills the gap of: cache-control/ETag response headers (with a
logged-in-visitor bypass cookie + header), and — see the dedicated
section below — the single convergence point for cache **purging**
(INV-6). Key config keys: `max_age` (default 3600s), `singular_factor` /
`archive_factor` (multipliers applied to `max_age` for those page types,
default 24 / 6), `etag` (default `true`), `bypass_cookie` (default
`ntdst_nocache`), `purge_debounce_seconds` (default 30) /
`purge_debounce_limit` (default 5) — the purge rate-limit window/ceiling,
see below.

---

## Purge providers (INV-6)

Cache purging converges on one action:
`do_action('ntdst/baseline/purge', string $scope, array $context = [])`,
fired from a single call-site inside `CacheHeadersService`. **Baseline
ships no destructive provider of its own** — no direct object-cache
flush, no PHP opcode-cache reset, no CDN API call, anywhere in this
plugin directory. Attaching a provider is entirely the consuming site's
job, at the infrastructure layer:

```php
add_action('ntdst/baseline/purge', function (string $scope, array $context = []): void {
    // e.g. nginx FastCGI cache purge, a CDN API call, wp_cache_flush(), etc.
    // $scope is 'content' (an automatic content-change fire) or 'all'
    // (the manage_options-gated manual trigger). $context carries
    // ['post_id' => int] for the content-change hooks that have one.
}, 10, 2);
```

Two producers fire the action, and there is no third path:

- **Content hooks** (`save_post` minus revisions, `deleted_post` minus
  revisions, `switch_theme`, `wp_update_nav_menu`) fire scope `content`
  automatically — no capability check needed, since WordPress's own
  content-mutation hooks are already capability-gated upstream of these.
- **The manual trigger** — the `ntdst/api_data/baseline_purge` handler
  and an admin-bar "Purge cache" node — fires scope `all`, and both entry
  points check `current_user_can('manage_options')`, failing closed
  otherwise. `baseline_purge` must never be added to
  `ntdst/api/public_actions`; the router only default-denies a
  logged-out caller when the action is absent from that allowlist, so
  listing it there would silently reopen the exact DoS gap this
  convergence point exists to close.

**Content-scope rate limit.** A same-status repeat save of an
already-published post (a low-privilege, trivially-loopable REST lever —
repeatedly `PATCH`ing `/wp/v2/posts/{id}` with no status change) is
throttled: a counter-with-ceiling per `(scope, post_id)` bucket, allowing
up to `purge_debounce_limit` fires per `purge_debounce_seconds` window
before suppressing further fires in that window. A genuine
`post_status` transition (draft→publish, publish→draft, any change)
**always fires, unconditionally, never counted against the limit** — a
plain time-bucketed throttle with no transition-awareness was tried and
rejected during review because it could silently swallow the one purge a
cache provider can least afford to miss (a post going live). `all`-scope
purges (the manual trigger) are never rate-limited either, since they're
already gated behind `manage_options`. See `ARCHITECTURE-INVARIANTS.md`
INV-6 for the full convergence-point contract and the bypass grep a
reviewer should run.

---

## Accepted deferrals / known limits

This plugin is honest about what it does not cover. Read this section
before assuming a security property you haven't verified:

- **The login-attempt counter is not atomic under concurrency (known,
  not theoretical).** `SecurityService`'s login limiter uses
  `get_transient()` → compare → `set_transient()`, which is a
  read-modify-write with no lock. A flake investigation (see
  `.superpowers/sdd/tasks/flake-investigation-report.md`) reproduced a
  "must be locked out" test failure where the counter never reached the
  cap, under conditions that ruled out a bad assertion — a real,
  reachable race. An attacker firing genuinely parallel login attempts
  from one IP may land slightly more than `max_login_attempts` guesses
  before lockout engages. The limiter still locks out; it is imprecise
  under concurrency. A correct fix needs an atomic primitive
  (`wp_cache_incr` against a persistent object cache, which this site
  stack does not currently provision) — deferred to v2.
- **Application-password / REST Basic-Auth brute force is NOT
  rate-limited.** WordPress validates application passwords on
  `determine_current_user`, a completely different filter from
  `authenticate`, so REST Basic-Auth credential guessing bypasses the
  login limiter entirely. This is a real, separate surface the limiter
  does not defend — deferred to v2 (extending the limiter to
  `determine_current_user`), not something to assume is covered.
- **Locked-out login attempts still pay WordPress core's bcrypt cost.**
  The limiter correctly *refuses* a locked-out login (access is denied),
  but it cannot cheaply stop core's own `wp_authenticate_username_password`
  from still running `get_user_by()` + `wp_check_password()` on every
  attempt, because core only short-circuits on an incoming `WP_User`, never
  on an incoming `WP_Error`. Unhooking core's own callbacks to avoid that
  work was judged materially riskier than the CPU cost is worth. Accepted:
  unauthorized access is prevented; per-attempt CPU cost on an
  already-locked-out IP is not.
- **REST-embedded author enumeration is not covered.**
  `/wp/v2/posts?_embed` exposes author data via `wp:author` links; the
  `disable_user_enumeration` guard removes whole REST routes, not
  embedded sub-resources reachable through a different endpoint. A fifth
  user-enumeration surface beyond the four this module does close —
  deferred to v2.
- **Distributed brute force** (a botnet making one attempt per IP) is not
  stoppable by per-IP limiting; that needs CAPTCHA/2FA, a v2 decision.
- **Shared-NAT collateral lockout**: per-IP lockout can block innocent
  co-tenants of an attacker's NAT for up to `lockout_duration`. Accepted
  residual.
- **IPv6 /64 rotation**: an attacker rotating addresses within their own
  /64 gets a fresh rate-limit identity each time; range-keying the
  limiter is deferred.
- **CSP is a config-list policy**, not nonce/strict-dynamic based — that
  refinement is a per-site v2 concern, not something this plugin decides
  for every site.
- **Cache poisoning at the edge** (nginx/CDN configuration correctness)
  is out of scope — this plugin emits correct cache-control/bypass
  headers and the purge convergence point; edge config correctness is
  infra/deploy territory, not this plugin's.

None of the above are silent gaps — each is a named, deliberate deferral
with a reason, and several are regression-tested for the property that
IS covered (e.g. the lockout race is asserted as a known-reachable
defect, not weakened away).

---

## Testing

Run both suites from the site root (`ddev` shells into the web
container):

```bash
# Unit suite (pure logic — policy builders, config merging, etc.)
ddev exec vendor/bin/phpunit --testsuite Unit

# Integration suite — needs a disposable test DB, set via env var
ddev exec 'DAAN_TEST_DB_DISPOSABLE=1 vendor/bin/phpunit --testsuite Integration'
```

Lint (WordPress Coding Standards via Pint):

```bash
ddev exec composer lint
```

The Integration suite currently reports a number of pre-existing PHPUnit
notices (deprecations from WP core / test scaffolding, not from this
plugin's own code) — a passing run with that notice count unchanged is
expected; a passing run with the notice count **increased** signals a new
regression worth investigating, not something to ignore.
