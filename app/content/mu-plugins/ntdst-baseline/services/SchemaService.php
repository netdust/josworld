<?php

declare(strict_types=1);

namespace ntdst\baseline\services;

use ntdst\baseline\support\Config;
use ntdst\baseline\support\JsonLd;

defined('ABSPATH') || exit;

/**
 * SchemaService.
 *
 * Registers the 'schema' boot marker so the boot-orchestration layer (and
 * later T18's kill-switch sweep) can introspect which modules actually
 * booted via `apply_filters('ntdst/baseline/booted', [])`. This marker
 * registration is permanent — it stays even once this stub grows real
 * module logic.
 *
 * T14: wiring only, over T13's pure builders in `support/JsonLd.php`
 * (plan.md §SchemaService lines 588-612; brief task-14-brief.md). Port
 * source: ludoluykx's SchemaService.php — see the Reworks list below, all
 * mandatory.
 *
 * Config key: `ntdst/baseline/schema/config` via `Config::merge()`.
 * DEFAULTS carry NO example values anywhere (M8 empty-safe — the ludoluykx
 * source shipped a placeholder phone/address/"John Doe"/dummy social URLs
 * as LIVE JSON-LD the moment a site set a `type` without filling the rest;
 * see JsonLd.php's own class docblock for the full contract this
 * DEFAULTS shape must never violate).
 *
 * Reworks vs. the ludoluykx source (all mandatory, plan.md lines 606-613):
 * - No `get_option('netdust_*')` reads anywhere — the config filter is the
 *   SINGLE source of truth (source read schema_type/name/social_profiles/
 *   business_type/phone/email/address/person_job_title/skills from wp_options;
 *   all dropped, no split-brain with the DB).
 * - Page detection by ID mapping, not slug lists — `pages =>
 *   ['contact' => <id>, 'about' => <id>, 'faq' => <id>]`. The source's
 *   EN/NL/DE slug-list detection (:224-231) is DROPPED (fragile i18n: a
 *   site whose contact page is not literally slugged "contact"/"kontakt"/
 *   etc. would silently never get ContactPage schema).
 * - Auto-fill is allowed ONLY for name/url/description from bloginfo (real
 *   site values) — never for contact/address/phone data (the placeholder-
 *   data rule JsonLd.php's builders themselves enforce on the receiving
 *   end; this service must not launder placeholder-shaped defaults through
 *   auto-fill either).
 * - `getDeveloperCorporation()` (source :347-355) and the `creator` key it
 *   fed into WebSite schema are DROPPED — see JsonLd::website()'s own
 *   docblock; Netdust branding is never injected into a site's structured
 *   data.
 * - The WooCommerce product branch is DROPPED — daan has no Woo.
 * - Emission uses `wp_json_encode($schema, JSON_UNESCAPED_UNICODE)` inside
 *   `<script type="application/ld+json">`. `JSON_UNESCAPED_SLASHES` is
 *   deliberately NOT set: it strips the `/` -> `\/` escaping that is the only
 *   thing stopping a `</script>` inside post-derived content (a title, an
 *   excerpt) from closing this block early and injecting executable script.
 *   Do not re-add it for output readability — see the guard comment at the
 *   encode site and the regression test
 *   `BaselineSchemaTest::testArticleTitleWithScriptCloseTagCannotBreakOutOfLdJsonBlock`.
 *   skipping robots/feed/trackback requests (port :196-199) — no
 *   `JSON_PRETTY_PRINT` (the source's flag the port drops; pretty-printing
 *   is a cosmetic dev-time nicety with no functional value in production
 *   markup).
 * - `type === null` (the DEFAULTS value) => the service still registers
 *   its `wp_head` hook (so the module remains introspectable/toggleable
 *   the same way every other baseline module is) but `outputSchema()`
 *   emits NOTHING — the M8 denial path.
 *
 * Extension filters (literal names, unchanged from the source):
 * `ntdst/baseline/schema/custom` and `ntdst/baseline/schema/post_type/{type}`.
 *
 * WP-security pillar for this task's data flow: config is site-owned
 * (trusted, not user input); output goes through `wp_json_encode()`
 * (context-correct encoding for the `<script type="application/ld+json">`
 * context) rather than manual string concatenation.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class SchemaService
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    private const DEFAULTS = [
        'type' => null,
        'name' => '',
        'url' => '',
        'logo' => '',
        'description' => '',
        'social_profiles' => [],
        'business' => [],
        'person' => [],
        'pages' => [],
    ];

    public function __construct()
    {
        add_filter('ntdst/baseline/booted', static fn(array $booted): array => [...$booted, 'schema']);

        $this->config = Config::merge(self::DEFAULTS, (array) apply_filters('ntdst/baseline/schema/config', []));

        // Auto-fill ONLY name/url/description from real site values
        // (bloginfo/home_url) — never contact/address/phone data. This
        // mirrors the source's init() auto-fill but drops the logo
        // auto-detect (source getLogoUrl(), which reached into YOOtheme
        // theme config / get_theme_mod('custom_logo') — a presentation
        // concern this baseline mu-plugin does not own).
        if ($this->config['name'] === '') {
            $this->config['name'] = get_bloginfo('name');
        }
        if ($this->config['url'] === '') {
            $this->config['url'] = home_url('/');
        }
        if ($this->config['description'] === '') {
            $this->config['description'] = get_bloginfo('description');
        }

        // Registered unconditionally, including when type === null, so the
        // module stays introspectable/toggleable like every other baseline
        // module — outputSchema() itself is what emits nothing (M8).
        add_action('wp_head', [$this, 'outputSchema']);
    }

    /**
     * Emits every applicable JSON-LD block for the current request inside
     * `<script type="application/ld+json">` tags, encoded via
     * `wp_json_encode()` (context-correct encoding, never manual string
     * concatenation).
     *
     * M8 denial path: when `type` is unconfigured (DEFAULTS: null), no
     * mainEntity/website schema is buildable and, since none of the
     * page-specific branches below produce a schema without a configured
     * `pages` mapping either, ZERO blocks are emitted for an enabled-but-
     * unconfigured module.
     */
    public function outputSchema(): void
    {
        if (is_robots() || is_feed() || is_trackback()) {
            return;
        }

        global $post;
        $schemas = [];

        $entity = JsonLd::mainEntity($this->config);
        if ($entity !== null) {
            $schemas[] = apply_filters('ntdst/baseline/schema/main_entity', $entity);

            $website = JsonLd::website($this->config, $entity);
            $schemas[] = apply_filters('ntdst/baseline/schema/website', $website);
        }

        $pages = (array) $this->config['pages'];

        if (is_singular('post')) {
            $article = $this->buildArticleSchema();
            if ($article !== null) {
                $schemas[] = apply_filters('ntdst/baseline/schema/article', $article, $post);
            }
        } elseif (is_page()) {
            $pageId = $post instanceof \WP_Post ? $post->ID : 0;

            $webpage = $this->buildWebpageSchema();
            if ($webpage !== null) {
                $schemas[] = apply_filters('ntdst/baseline/schema/webpage', $webpage, $post);
            }

            if ($pageId > 0 && isset($pages['contact']) && (int) $pages['contact'] === $pageId) {
                $contact = $this->buildContactPageSchema();
                if ($contact !== null) {
                    $schemas[] = $contact;
                }
            } elseif ($pageId > 0 && isset($pages['about']) && (int) $pages['about'] === $pageId) {
                $about = $this->buildAboutPageSchema();
                if ($about !== null) {
                    $schemas[] = $about;
                }
            } elseif ($pageId > 0 && isset($pages['faq']) && (int) $pages['faq'] === $pageId) {
                $faq = $this->buildFaqPageSchema();
                if ($faq !== null) {
                    $schemas[] = $faq;
                }
            }
        } elseif (is_category() || is_tag() || is_archive()) {
            $collection = $this->buildCollectionSchema();
            if ($collection !== null) {
                $schemas[] = $collection;
            }
        }

        if (is_singular() && $post instanceof \WP_Post) {
            $postType = get_post_type($post);
            if ($postType && !in_array($postType, ['post', 'page'], true)) {
                /**
                 * Filter: ntdst/baseline/schema/post_type/{post_type}
                 * Add schema for specific custom post types.
                 *
                 * @param array<string, mixed>|null $schema
                 * @param \WP_Post $post
                 */
                $customSchema = apply_filters("ntdst/baseline/schema/post_type/{$postType}", null, $post);
                if ($customSchema) {
                    $schemas[] = $customSchema;
                }
            }
        }

        /**
         * Filter: ntdst/baseline/schema/custom
         * Add completely custom schema objects.
         *
         * @param array<int, mixed> $customSchemas
         * @param \WP_Post|null $post
         */
        $customSchemas = (array) apply_filters('ntdst/baseline/schema/custom', [], $post);
        foreach ($customSchemas as $custom) {
            if ($custom) {
                $schemas[] = $custom;
            }
        }

        foreach ($schemas as $schema) {
            if (!$schema) {
                continue;
            }

            echo '<script type="application/ld+json">';
            // FIX ROUND (coordinator finding #1, CRITICAL, reproduced
            // independently before this fix): JSON_UNESCAPED_SLASHES is
            // deliberately DROPPED here. It removes PHP's default
            // '/' -> '\/' escaping, which is the ONLY thing preventing a
            // post/term-derived title/name/description containing
            // "</script>" from closing this block early and injecting
            // live script for every front-end visitor — a stored XSS
            // reachable by any author/editor-capable user. Escaped
            // slashes are fully valid JSON-LD; Google's structured-data
            // tooling handles them with no functional cost. Do NOT
            // reintroduce this flag, and do not "fix" this by sanitizing
            // input strings instead — this is the single shared encode
            // site every builder's output passes through.
            echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE);
            echo '</script>' . "\n";
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildArticleSchema(): ?array
    {
        global $post;
        if (!$post instanceof \WP_Post) {
            return null;
        }

        $author = get_the_author_meta('ID', (int) $post->post_author);

        return JsonLd::article([
            'title' => get_the_title($post),
            'url' => (string) get_permalink($post),
            'description' => get_the_excerpt($post) ?: '',
            'date_published' => get_the_date('c', $post),
            'date_modified' => get_the_modified_date('c', $post),
            'author_name' => get_the_author_meta('display_name', (int) $post->post_author),
            'author_url' => $author ? get_author_posts_url((int) $author) : '',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildWebpageSchema(): ?array
    {
        global $post;
        if (!$post instanceof \WP_Post) {
            return null;
        }

        return JsonLd::webpage([
            'title' => get_the_title($post),
            'url' => (string) get_permalink($post),
            'description' => get_the_excerpt($post) ?: '',
            'date_published' => get_the_date('c', $post),
            'date_modified' => get_the_modified_date('c', $post),
            'site_url' => home_url('/'),
            'site_name' => get_bloginfo('name'),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildContactPageSchema(): ?array
    {
        global $post;
        if (!$post instanceof \WP_Post) {
            return null;
        }

        return JsonLd::contactPage([
            'title' => get_the_title($post),
            'url' => (string) get_permalink($post),
            'description' => get_the_excerpt($post) ?: '',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildAboutPageSchema(): ?array
    {
        global $post;
        if (!$post instanceof \WP_Post) {
            return null;
        }

        return JsonLd::aboutPage([
            'title' => get_the_title($post),
            'url' => (string) get_permalink($post),
            'description' => get_the_excerpt($post) ?: '',
            'main_entity' => JsonLd::mainEntity($this->config),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildFaqPageSchema(): ?array
    {
        global $post;
        if (!$post instanceof \WP_Post) {
            return null;
        }

        /**
         * Filter: ntdst/baseline/schema/faq_items
         * Provide FAQ Q&A items for the FAQ page schema.
         *
         * @param array<int, array{question?: string, answer?: string}> $items
         * @param \WP_Post $post
         */
        $items = (array) apply_filters('ntdst/baseline/schema/faq_items', [], $post);

        return JsonLd::faqPage([
            'title' => get_the_title($post),
            'url' => (string) get_permalink($post),
            'items' => $items,
        ]);
    }

    /**
     * Guards `is_wp_error()` on the term-link call before using it (task
     * brief requirement) — `get_term_link()` returns a `WP_Error` for a
     * malformed/deleted term rather than throwing, so an unguarded use
     * would pass a WP_Error object into the schema array as a "url".
     *
     * @return array<string, mixed>|null
     */
    private function buildCollectionSchema(): ?array
    {
        $object = get_queried_object();
        if (!$object) {
            return null;
        }

        if ($object instanceof \WP_Term) {
            $name = $object->name;
            $description = $object->description ?: '';
            $link = get_term_link($object);
            $url = is_wp_error($link) ? '' : $link;
        } elseif ($object instanceof \WP_Post_Type) {
            $name = $object->label;
            $description = $object->description ?: '';
            $url = (string) (get_post_type_archive_link($object->name) ?: '');
        } else {
            return null;
        }

        return JsonLd::collection([
            'name' => $name,
            'url' => $url,
            'description' => $description,
            'site_url' => home_url('/'),
            'site_name' => get_bloginfo('name'),
        ]);
    }
}
