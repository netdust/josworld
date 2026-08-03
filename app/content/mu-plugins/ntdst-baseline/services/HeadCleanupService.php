<?php

declare(strict_types=1);

namespace ntdst\baseline\services;

use ntdst\baseline\support\Config;

defined('ABSPATH') || exit;

/**
 * HeadCleanupService.
 *
 * Registers the 'head_cleanup' boot marker so the boot-orchestration layer (and
 * later T18's kill-switch sweep) can introspect which modules actually
 * booted via `apply_filters('ntdst/baseline/booted', [])`. This marker
 * registration is permanent — it stays even once this stub grows real
 * module logic.
 *
 * T8: port of ludoluykx's CleanupService.php PLUS the fingerprint head-bits
 * plan.md assigns here rather than to SecurityService (wp_generator, version
 * strings, REST/oEmbed discovery links — SecurityService deliberately does
 * NOT duplicate them; plan.md lines 548-550, 552-563).
 *
 * Frontend-only: constructs, registers the boot marker, and returns early on
 * `is_admin()` before installing any of its own hooks — the boot marker
 * itself is NOT gated on is_admin(), mirroring SecurityService's pattern of
 * an unconditional marker registration ahead of any config-gated logic.
 *
 * Two deliberate reworks vs. the ported source:
 * - `remove_jquery` / `remove_block_styles` default to FALSE (source :39-40
 *   defaulted both to true) — they break YOOtheme-less/Gutenberg sites, so
 *   they are opt-in only here.
 * - The source's `doing_it_wrong_trigger_error` /
 *   `_load_textdomain_just_in_time_error` notice suppressions (source
 *   :53-55) are NOT ported at all — they are a debugging footgun that hides
 *   real problems rather than fixing them. Their absence is contract-tested.
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class HeadCleanupService
{
    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    private const DEFAULTS = [
        'disable_emojis' => true,
        'disable_embeds' => true,
        'clean_head' => true,
        'remove_version_strings' => true,
        'remove_dashicons' => true,
        'remove_jquery' => false,
        'remove_block_styles' => false,
    ];

    public function __construct()
    {
        add_filter('ntdst/baseline/booted', static fn(array $booted): array => [...$booted, 'head_cleanup']);

        if (is_admin()) {
            return;
        }

        $this->config = Config::merge(self::DEFAULTS, (array) apply_filters('ntdst/baseline/head_cleanup/config', []));

        if ($this->config['disable_emojis']) {
            add_action('init', [$this, 'disableEmojis']);
        }

        if ($this->config['disable_embeds']) {
            add_action('init', [$this, 'disableEmbeds']);
        }

        if ($this->config['clean_head']) {
            add_action('init', [$this, 'cleanHead']);
        }

        if ($this->config['remove_version_strings']) {
            add_action('init', [$this, 'removeVersionStrings']);
        }

        if ($this->config['remove_dashicons']) {
            add_action('wp_enqueue_scripts', [$this, 'removeDashicons'], 100);
        }

        if ($this->config['remove_jquery']) {
            add_action('wp_enqueue_scripts', [$this, 'removeJquery']);
        }

        if ($this->config['remove_block_styles']) {
            add_action('wp_enqueue_scripts', [$this, 'removeBlockStyles'], 100);
        }
    }

    /**
     * Feeds, adjacent-post links, shortlink, RSD/wlwmanifest discovery
     * links, the version-generator meta tag, and REST/oEmbed discovery
     * links — the fingerprint + bloat head-bits this task owns (plan.md
     * lines 552-563; SecurityService deliberately does not duplicate these).
     */
    public function cleanHead(): void
    {
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');

        // Fingerprint: version-generator meta tag (moved here from
        // SecurityService per plan.md:548-550).
        remove_action('wp_head', 'wp_generator');
        add_filter('the_generator', '__return_empty_string');
        remove_action('rss2_head', 'the_generator');
        remove_action('commentsrss2_head', 'the_generator');
        remove_action('rss_head', 'the_generator');
        remove_action('rdf_header', 'the_generator');
        remove_action('atom_head', 'the_generator');
        remove_action('app_head', 'the_generator');

        // Fingerprint: REST + oEmbed discovery links (moved here from
        // SecurityService per plan.md:548-550). wp_oembed_add_discovery_links
        // is core-registered TWICE (default-filters.php:713-714): once at
        // priority 4 ("printed after feed_links() and feed_links_extra()")
        // and once at the default priority 10, kept purely for back-compat
        // so a plugin that removed only the original priority-10 hook still
        // sees the callback self-unhook on its first wp_head fire. Both
        // registrations must be removed explicitly — remove_action() only
        // matches an exact (hook, callback, priority) tuple, so removing
        // just one leaves the other live.
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 4);
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
    }

    public function disableEmojis(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        add_filter('tiny_mce_plugins', static function ($plugins) {
            return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : $plugins;
        });

        add_filter('wp_resource_hints', static function ($urls, $relationType) {
            if ($relationType === 'dns-prefetch') {
                $emojiSvgUrl = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/');
                $urls = array_diff($urls, [$emojiSvgUrl]);
            }

            return $urls;
        }, 10, 2);
    }

    public function disableEmbeds(): void
    {
        add_action('wp_footer', static function (): void {
            wp_deregister_script('wp-embed');
        });

        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);

        add_filter('embed_oembed_discover', '__return_false');

        add_filter('tiny_mce_plugins', static function ($plugins) {
            return is_array($plugins) ? array_diff($plugins, ['wpembed']) : $plugins;
        });

        add_filter('rewrite_rules_array', static function (array $rules): array {
            foreach ($rules as $rule => $rewrite) {
                if (str_contains($rewrite, 'embed=true')) {
                    unset($rules[$rule]);
                }
            }

            return $rules;
        });
    }

    public function removeVersionStrings(): void
    {
        add_filter('style_loader_src', [$this, 'removeVersionQuery'], 10, 2);
        add_filter('script_loader_src', [$this, 'removeVersionQuery'], 10, 2);
    }

    /**
     * @param mixed $src
     * @param mixed $handle
     * @return mixed
     */
    public function removeVersionQuery($src, $handle)
    {
        if (is_string($src) && str_contains($src, 'ver=')) {
            return remove_query_arg('ver', $src);
        }

        return $src;
    }

    public function removeDashicons(): void
    {
        if (!is_user_logged_in()) {
            wp_deregister_style('dashicons');
        }
    }

    public function removeJquery(): void
    {
        wp_deregister_script('jquery');
    }

    public function removeBlockStyles(): void
    {
        $stylesToRemove = [
            'wp-block-library',
            'wp-block-library-theme',
            'classic-theme-styles',
            'global-styles',
        ];

        foreach ($stylesToRemove as $style) {
            wp_dequeue_style($style);
            wp_deregister_style($style);
        }
    }
}
