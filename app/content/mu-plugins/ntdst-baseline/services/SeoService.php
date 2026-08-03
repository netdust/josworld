<?php

declare(strict_types=1);

namespace ntdst\baseline\services;

use ntdst\baseline\support\Config;

defined('ABSPATH') || exit;

/**
 * SeoService.
 *
 * Registers the 'seo' boot marker so the boot-orchestration layer (and
 * later T18's kill-switch sweep) can introspect which modules actually
 * booted via `apply_filters('ntdst/baseline/booted', [])`. This marker
 * registration is permanent — it stays even once this stub grows real
 * module logic.
 *
 * T11: meta-tag output only — description, canonical, robots directives —
 * on `wp_head` priority 1, port of ludoluykx's SeoService.php :173-236
 * (description derivation), :242-268 (robots directives), :274-301
 * (canonical). The entire custom sitemap (source :51-87, :353-455) is
 * DROPPED here; T12 owns robots.txt + the core sitemap posture instead of
 * the ported custom XML sitemap.
 *
 * Config key: `ntdst/baseline/seo/config` via `Config::merge()`. T11 reads
 * `meta_tags` from DEFAULTS; T12 adds `robots_txt`, `sitemap_users_provider`,
 * `robots_rules`.
 *
 * T12: robots.txt posture (threat-model M7, plan.md lines 190-194) + core
 * sitemap posture (threat-model M4c, plan.md lines 168-175; §SeoService
 * design, plan.md lines 578-586).
 *
 * M7 — respect `blog_public`: the `robots_txt` filter returns core's
 * discouraged-crawling output byte-identical whenever the second filter
 * argument is `'0'` (non-public). daan's `bedrock-disallow-indexing`
 * mu-plugin forces `blog_public=0` on staging/dev, so this service must
 * NEVER override that output in that state — doing so would expose staging
 * to crawlers. This service also never touches core's own sitemap-enabled
 * gate — core alone derives that from `blog_public`
 * (class-wp-sitemaps.php); overriding it would independently re-expose
 * staging even with a correct robots.txt guard.
 *
 * M4c — the core users sitemap provider (a user-enumeration surface, since
 * it lists registered usernames) is removed via
 * `wp_sitemaps_add_provider` returning `false` for `name === 'users'`.
 *
 * Dropped: the entire ludoluykx custom XML sitemap (source :51-87,
 * :353-455) — core's own `WP_Sitemaps` is the sitemap; this service only
 * posts to `robots.txt` and prunes one provider. Do NOT re-implement any
 * sitemap here.
 *
 * WP-security pillar for this task's data flow (site-owned content — post
 * excerpt/content, term description, author bio, site tagline — into an
 * HTML attribute context): every content/name attribute is escaped with
 * `esc_attr()`, the canonical href with `esc_url()`. Site-owned content is
 * still escaped on output because an editor-authored excerpt/bio can
 * legitimately contain `"` or `<` that would otherwise break the
 * surrounding attribute.
 *
 * Canonical de-duplication (fix round 1, C4 review): WordPress core
 * emits its own `<link rel="canonical">` for singular content via
 * `rel_canonical()` (wp_head priority 10, wp-includes/link-template.php)
 * — unlike the ludoluykx port source, which ran standalone in a theme
 * with no such core collision to worry about. Emitting a second
 * canonical for `is_singular()` unconditionally would duplicate that tag,
 * so `resolveCanonical()` checks `has_action('wp_head', 'rel_canonical')`
 * — but that check happens INSIDE `outputMetaTags()` at EMISSION time,
 * not at construct time: this service's own callback runs at wp_head
 * priority 1, and a plugin loading after ntdst-baseline (Yoast, Rank
 * Math, All in One SEO, and similar SEO plugins all
 * `remove_action('wp_head', 'rel_canonical')` to install their own) may
 * not have removed core's default yet when SeoService itself
 * constructs. Checking at construct time would miss any removal that
 * happens later in the request lifecycle; checking at emission time
 * (wp_head has already fired to priority 1, but core's own priority-10
 * `rel_canonical` registration state is queryable via `has_action()`
 * regardless of whether it has actually fired yet) closes the ordering
 * gap for every removal staged BEFORE wp_head fires — which is the real
 * -world case, since SEO plugins unhook on `init` or in their
 * constructor. One narrow window remains open and is accepted: a plugin
 * that calls `remove_action('wp_head', 'rel_canonical')` from its own
 * wp_head callback at a priority between 1 and 10 removes core's after
 * this check has already deferred to it, so neither canonical is
 * emitted. That is an SEO-metadata-quality gap, not a security one, and
 * it is strictly better than the pre-fallback state (no fallback at
 * all). ntdst-baseline is designed to ship unedited on
 * every Netdust site (plan.md FR-20); the moment any full SEO plugin is
 * installed alongside it, core's canonical disappears and — without this
 * fallback — singular pages would silently lose their canonical
 * entirely. So: if core's `rel_canonical` is still registered at
 * emission time, this service stays out of the way (its prior
 * behavior, unchanged); if core's has been removed, this service emits
 * its own singular canonical from `get_permalink()` as a fallback so a
 * canonical is never silently absent. Front page, term archives, and
 * author archives are unaffected by this fallback — core's
 * `rel_canonical()` never covered those cases in the first place, so
 * this service has always emitted them unconditionally.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class SeoService
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    private const DEFAULTS = [
        'meta_tags' => true,
        'robots_txt' => true,
        'sitemap_users_provider' => false,
        'robots_rules' => [],
        'og_tags' => true,
        'og_image_fallback' => '',
    ];

    /**
     * Bedrock-agnostic built-in disallow rules. `wp-login.php` lives at the
     * WP-core install ROOT (Bedrock: `/wp/wp-login.php`; classic:
     * `/wp-login.php`), never under the admin path — see
     * `buildDefaultRobotsRules()`, which derives each rule from the correct
     * source (`wp_login_url()`/`site_url()` for the login rule, `admin_url()`
     * for the admin rule) rather than concatenating a shared suffix list
     * onto a single path. Never a hardcoded `/wp-admin/` or `/wp/wp-admin/`
     * literal.
     */

    public function __construct()
    {
        add_filter('ntdst/baseline/booted', static fn(array $booted): array => [...$booted, 'seo']);

        $this->config = Config::merge(self::DEFAULTS, (array) apply_filters('ntdst/baseline/seo/config', []));

        if ($this->config['meta_tags']) {
            add_action('wp_head', [$this, 'outputMetaTags'], 1);
        }

        if ($this->config['robots_txt']) {
            add_filter('robots_txt', [$this, 'filterRobotsTxt'], 10, 2);
        }

        if ($this->config['sitemap_users_provider'] === false) {
            add_filter('wp_sitemaps_add_provider', [$this, 'removeUsersSitemapProvider'], 10, 2);
        }
    }

    /**
     * Emits the description meta, robots meta, and canonical link for the
     * current request, each individually optional (empty derivation ⇒ no
     * tag echoed for that piece), then lets other code append via the
     * `ntdst/baseline/seo/meta` filter before echoing the final tag list.
     */
    public function outputMetaTags(): void
    {
        $tags = [];

        $description = $this->resolveDescription();
        if ($description !== '') {
            $tags[] = '<meta name="description" content="' . esc_attr($description) . '">';
        }

        $robots = $this->resolveRobots();
        if ($robots !== '') {
            $tags[] = '<meta name="robots" content="' . esc_attr($robots) . '">';
        }

        $canonical = $this->resolveCanonical();
        if ($canonical !== '') {
            $tags[] = '<link rel="canonical" href="' . esc_url($canonical) . '">';
        }

        if ($this->config['og_tags']) {
            $tags = [...$tags, ...$this->buildOgTags($description)];
        }

        /**
         * Filter: ntdst/baseline/seo/meta
         * Add or modify the meta/link tag strings emitted on wp_head before
         * this service echoes them.
         *
         * @param string[] $tags
         */
        $tags = apply_filters('ntdst/baseline/seo/meta', $tags);

        /*
         * Each tag this service builds above already escapes its own values
         * (esc_attr/esc_url). The escaping here exists for the FILTER: after
         * `ntdst/baseline/seo/meta`, a tag string may come from third-party
         * code and can no longer be assumed pre-escaped. wp_kses with a
         * head-tag allow-list preserves the <meta>/<link> structure these
         * tags require (wp_kses_post would strip them outright) while
         * neutralising anything script-bearing a filter might inject.
         */
        $allowedHeadTags = [
            'meta' => [
                'name' => true,
                'property' => true,
                'content' => true,
                'charset' => true,
                'http-equiv' => true,
            ],
            'link' => [
                'rel' => true,
                'href' => true,
                'hreflang' => true,
                'type' => true,
                'sizes' => true,
                'title' => true,
            ],
        ];

        foreach ($tags as $tag) {
            echo wp_kses($tag, $allowedHeadTags) . "\n";
        }
    }

    // =========================================================================
    // OPEN GRAPH / TWITTER CARD
    // =========================================================================

    /**
     * Builds the Open Graph + Twitter Card tag set. `$description` is the
     * SAME value `outputMetaTags()` already derived via `resolveDescription()`
     * — reused rather than re-derived, so og:description can never drift
     * from the plain `<meta name="description">` tag.
     *
     * M8 EMPTY-SAFE DENIAL: `og:image` is emitted ONLY when a featured image
     * exists (singular content) or the site-level `og_image_fallback` config
     * is set — otherwise the tag is OMITTED ENTIRELY. Never a placeholder or
     * stock image; see README's "Empty-safe defaults" section. This is the
     * one branch in this method that must never grow a fallback-to-a-fake-
     * value shortcut.
     *
     * @return string[]
     */
    private function buildOgTags(string $description): array
    {
        $tags = [];

        $isSingular = is_singular();
        // Raw post_title, NOT get_the_title() — the `the_title` filter runs
        // wptexturize() (straight quotes -> curly &#8220;/&#8221; entities),
        // which would silently rewrite an editor's raw quote/angle-bracket
        // content before esc_attr() ever sees it. The plain description tag
        // (resolveDescription()) already uses raw post data for the same
        // reason; og:title stays consistent with that.
        $title = '';
        if ($isSingular) {
            $singularPost = get_post();
            $title = $singularPost ? (string) $singularPost->post_title : '';
        } else {
            $title = (string) get_bloginfo('name');
        }
        $url = $this->resolveOgUrl();
        $type = $isSingular ? 'article' : 'website';
        $siteName = (string) get_bloginfo('name');
        $imageUrl = $this->resolveOgImage();

        if ($title !== '') {
            $tags[] = '<meta property="og:title" content="' . esc_attr($title) . '">';
        }

        if ($description !== '') {
            $tags[] = '<meta property="og:description" content="' . esc_attr($description) . '">';
        }

        if ($url !== '') {
            $tags[] = '<meta property="og:url" content="' . esc_url($url) . '">';
        }

        $tags[] = '<meta property="og:type" content="' . esc_attr($type) . '">';

        if ($siteName !== '') {
            $tags[] = '<meta property="og:site_name" content="' . esc_attr($siteName) . '">';
        }

        if ($imageUrl !== '') {
            $tags[] = '<meta property="og:image" content="' . esc_url($imageUrl) . '">';
        }

        if ($isSingular) {
            $post = get_post();
            if ($post) {
                $tags[] = '<meta property="article:published_time" content="'
                    . esc_attr(get_the_date('c', $post)) . '">';
                $tags[] = '<meta property="article:modified_time" content="'
                    . esc_attr(get_the_modified_date('c', $post)) . '">';
            }
        }

        $twitterCard = $imageUrl !== '' ? 'summary_large_image' : 'summary';
        $tags[] = '<meta name="twitter:card" content="' . esc_attr($twitterCard) . '">';

        return $tags;
    }

    /**
     * og:url always resolves the real canonical URL for the current
     * request, independent of whether core's own `rel_canonical()` tag has
     * fired — unlike `resolveCanonical()`, which intentionally stays silent
     * on singular content when core already emits that specific `<link>`
     * tag (avoiding a duplicate). og:url is a different tag entirely
     * (Open Graph, not `<link rel="canonical">`), so it must never be
     * empty merely because core happens to also be emitting its own
     * canonical link.
     */
    private function resolveOgUrl(): string
    {
        if (is_singular()) {
            $permalink = get_permalink();

            return $permalink !== false ? $permalink : '';
        }

        if (is_front_page()) {
            return home_url('/');
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $link = get_term_link($term);

                return is_string($link) ? $link : '';
            }
        }

        if (is_author()) {
            $author = get_queried_object();
            if ($author instanceof \WP_User) {
                return get_author_posts_url($author->ID);
            }
        }

        return '';
    }

    /**
     * M8 EMPTY-SAFE DENIAL — returns '' (never a placeholder) unless a real
     * featured image exists on the current singular post OR the site has
     * configured `og_image_fallback`. Precedence: a real featured image
     * always wins over the config fallback when both are present.
     */
    private function resolveOgImage(): string
    {
        if (is_singular()) {
            $post = get_post();
            if ($post && has_post_thumbnail($post->ID)) {
                $imageUrl = wp_get_attachment_image_url((int) get_post_thumbnail_id($post->ID), 'full');
                if (is_string($imageUrl) && $imageUrl !== '') {
                    return $imageUrl;
                }
            }
        }

        $fallback = (string) $this->config['og_image_fallback'];

        return $fallback;
    }

    // =========================================================================
    // ROBOTS.TXT (M7) + SITEMAP POSTURE (M4c)
    // =========================================================================

    /**
     * M7 DENIAL — return $robots UNTOUCHED for every falsy shape of
     * `$public`, not merely the ones that satisfy PHP's `== '0'` loose-
     * equality table. Any override here — even one that still resembles a
     * normal robots.txt — would defeat daan's `bedrock-disallow-indexing`
     * staging guard.
     *
     * FIX ROUND (coordinator finding #2, hardened): the original
     * `$public == '0'` check is correct for core's real call (core passes
     * a native bool via `do_robots()`, and `false == '0'` holds), but
     * `'' == '0'` and `null == '0'` are BOTH false in PHP — so a non-core
     * caller passing `''`/`null` would fall through to the "public"
     * branch and rewrite robots.txt. This is the highest-stakes guard in
     * the cluster; `filter_var(..., FILTER_VALIDATE_BOOLEAN)` treats
     * `''`, `null`, `false`, `0`, and `'0'` uniformly as falsy, so the
     * guard now fails CLOSED (untouched robots.txt) for every one of
     * them, not just the subset PHP's loose-equality table happens to
     * cover.
     *
     * Public site: prepends the Bedrock-corrected disallow rules (or the
     * site's own `robots_rules` config override, when non-empty), then
     * normalizes CORE's OWN `Sitemap: ` line (the one pointing at
     * `home_url('/wp-sitemap.xml')`) to exactly one occurrence —
     * core's own `WP_Sitemaps::add_robots()` (priority 0, runs before
     * this callback's priority 10) already appends one whenever the site
     * is public, so THAT specific line is deduped, replaced with a single
     * normalized one, rather than appending a second.
     *
     * FIX ROUND (coordinator finding #3, MEDIUM): the prior blanket
     * regex (anchored on "^Sitemap:" with the multiline modifier)
     * stripped EVERY `Sitemap:` line regardless of origin, silently
     * deleting a third-party sitemap plugin's own line. The dedupe below
     * now matches only lines whose URL is core's own
     * `home_url('/wp-sitemap.xml')` — a foreign
     * `Sitemap:` line is left untouched. This never re-implements the
     * sitemap itself — core is still the sitemap; this only guarantees
     * the count contract for core's own line.
     *
     * @param string $robots
     * @param string|bool $public
     */
    public function filterRobotsTxt($robots, $public): string
    {
        $robots = (string) $robots;

        if (!filter_var($public, FILTER_VALIDATE_BOOLEAN)) {
            return $robots;
        }

        $rules = (array) $this->config['robots_rules'];
        if ($rules === []) {
            $rules = $this->buildDefaultRobotsRules();
        }

        // Skip any directive core (or an earlier filter) has already emitted
        // verbatim — otherwise our `Disallow: <admin path>` duplicates the
        // identical line WordPress core adds by default, which is harmless to
        // crawlers but makes the served file look broken to anyone reading it.
        // Compared on the trimmed line so trailing whitespace never defeats it.
        $existingLines = array_map('trim', preg_split('/\r?\n/', $robots) ?: []);

        $ourDirectives = '';
        foreach ($rules as $rule) {
            $rule = trim((string) $rule);

            if ($rule === '' || in_array($rule, $existingLines, true)) {
                continue;
            }

            $ourDirectives .= $rule . "\n";
        }

        $coreSitemapUrl = home_url('/wp-sitemap.xml');
        $coreSitemapPattern = '/^Sitemap:\s*' . preg_quote($coreSitemapUrl, '/') . '\s*\r?\n?/m';
        $robots = (string) preg_replace($coreSitemapPattern, '', $robots);
        $robots = rtrim($robots, "\n");

        // Insert our directives INSIDE the FIRST `User-agent:` group rather
        // than prepending them above the whole file — directives before any
        // User-agent line are outside any group per the robots.txt spec and
        // crawlers may ignore them. If no User-agent line exists at all
        // (an unusual/empty $robots), emit our own group header so our
        // rules are always inside a valid group.
        if (preg_match('/^User-agent:.*$/mi', $robots, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $lineEnd = $matches[0][1] + strlen($matches[0][0]);
            $robots = substr($robots, 0, $lineEnd) . "\n" . rtrim($ourDirectives, "\n")
                . substr($robots, $lineEnd);
        } else {
            $robots = rtrim($robots . "\n\n" . "User-agent: *\n" . $ourDirectives, "\n");
        }

        $robots = rtrim($robots, "\n") . "\n\n" . 'Sitemap: ' . $coreSitemapUrl . "\n";

        return $robots;
    }

    /**
     * Derives BOTH the WP-core-root path (for `wp-login.php`) and the admin
     * path (for `wp-admin/`) from live WP functions rather than hardcoded
     * literals — correct on both Bedrock (`/wp/wp-login.php`,
     * `/wp/wp-admin/`) and classic (`/wp-login.php`, `/wp-admin/`) layouts.
     *
     * `wp-login.php` lives at the WP-core install ROOT, not under
     * `admin_url()` — derived from `wp_login_url()` (falling back to
     * `site_url()` if the login URL ever yields no path). `wp-admin/` IS
     * the admin path already — `admin_url()` is used as-is, never with a
     * `wp-admin/` suffix appended onto it again (that produced the doubled
     * `/wp-admin/wp-admin/` bug).
     *
     * @return string[]
     */
    private function buildDefaultRobotsRules(): array
    {
        $loginPath = parse_url(wp_login_url(), PHP_URL_PATH);
        if (!is_string($loginPath) || $loginPath === '') {
            $loginPath = parse_url(site_url('/wp-login.php'), PHP_URL_PATH);
        }
        $loginPath = is_string($loginPath) && $loginPath !== '' ? $loginPath : '/wp-login.php';

        $adminPath = parse_url(admin_url(), PHP_URL_PATH);
        $adminPath = is_string($adminPath) && $adminPath !== '' ? rtrim($adminPath, '/') . '/' : '/wp-admin/';

        return [
            'Disallow: ' . $loginPath,
            'Disallow: ' . $adminPath,
        ];
    }

    /**
     * M4c — removes the core `users` sitemap provider (a user-enumeration
     * surface: it lists every registered username). Every other provider
     * (posts, taxonomies, ...) is returned untouched; this service never
     * re-implements or otherwise replaces them.
     *
     * @param \WP_Sitemaps_Provider|false $provider
     * @return \WP_Sitemaps_Provider|false
     */
    public function removeUsersSitemapProvider($provider, string $name)
    {
        if ($name === 'users') {
            return false;
        }

        return $provider;
    }

    // =========================================================================
    // DESCRIPTION
    // =========================================================================

    private function resolveDescription(): string
    {
        $description = '';

        if (is_singular()) {
            $description = get_the_excerpt();
            if (!$description) {
                $post = get_post();
                if ($post) {
                    $description = wp_trim_words(strip_tags($post->post_content), 30, '...');
                }
            }
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $description = $term->description;
            }
        } elseif (is_author()) {
            $author = get_queried_object();
            if ($author instanceof \WP_User) {
                $description = (string) get_the_author_meta('description', $author->ID);
            }
        } elseif (is_search() || is_404()) {
            $description = '';
        } elseif (is_front_page()) {
            $description = get_bloginfo('description');
        }

        return self::trimDescription($description);
    }

    /**
     * Pure, WP-free trim helper — contract-tested directly by
     * tests/Unit/SeoTrimTest.php.
     *
     * Trims $text to at most $limit characters at a WORD boundary (never
     * mid-word), appending an ellipsis when a cut actually occurred.
     * Entities are decoded and whitespace collapsed BEFORE measuring
     * length, so an entity-heavy string is not mismeasured against its raw
     * encoded form.
     */
    public static function trimDescription(string $text, int $limit = 155): string
    {
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false) {
            $cut = mb_substr($cut, 0, $lastSpace);
        } else {
            // No word boundary within the budget at all (a single word
            // longer than $limit) — hard cut, but still leave room for the
            // ellipsis so the final result never exceeds $limit.
            $ellipsisRoom = max(0, $limit - 3);
            $cut = mb_substr($cut, 0, $ellipsisRoom);
        }

        return $cut . '...';
    }

    // =========================================================================
    // ROBOTS DIRECTIVE
    // =========================================================================

    private function resolveRobots(): string
    {
        $directives = [];

        if (is_search() || is_404()) {
            $directives[] = 'noindex';
            $directives[] = 'follow';
        } elseif (is_singular() && get_post_status() === 'private') {
            $directives[] = 'noindex';
            $directives[] = 'nofollow';
        } elseif (is_paged() && (int) get_query_var('paged') > 1) {
            $directives[] = 'noindex';
            $directives[] = 'follow';
        }

        return implode(', ', $directives);
    }

    // =========================================================================
    // CANONICAL URL
    // =========================================================================

    /**
     * Handles is_singular() ONLY as a fallback — see the class docblock's
     * "Canonical de-duplication" note. Checked at EMISSION time (this
     * method runs from inside outputMetaTags(), itself the wp_head
     * priority-1 callback) via has_action('wp_head', 'rel_canonical'), NOT
     * at construct time, so a plugin that removes core's rel_canonical
     * registration anywhere in the request lifecycle before wp_head fires
     * is still correctly detected.
     *
     * SCOPE DECISION (fix round 1): this fallback intentionally does NOT
     * replicate core's wp_get_canonical_url() pagination handling
     * (page=N for `<!--nextpage-->` multi-page posts, cpage=N for
     * paginated comments — link-template.php:4082-4096). The fallback's
     * job is denial-of-degradation ("never silently absent a canonical",
     * not "byte-identical to core in every edge case") — it only engages
     * at all once a THIRD PARTY has already removed core's own canonical
     * logic, at which point that third party (a full SEO plugin) is the
     * far more likely source of a site's canonical/pagination handling
     * going forward. get_permalink() here always returns the page-1 URL
     * of a paginated post/comment thread, never the paginated URL a
     * request to page 2+ is actually on.
     */
    private function resolveCanonical(): string
    {
        if (is_singular() && !has_action('wp_head', 'rel_canonical')) {
            $permalink = get_permalink();

            return $permalink !== false ? $permalink : '';
        }

        if (is_front_page()) {
            return home_url('/');
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $link = get_term_link($term);

                return is_string($link) ? $link : '';
            }
        }

        if (is_author()) {
            $author = get_queried_object();
            if ($author instanceof \WP_User) {
                return get_author_posts_url($author->ID);
            }
        }

        return '';
    }
}
