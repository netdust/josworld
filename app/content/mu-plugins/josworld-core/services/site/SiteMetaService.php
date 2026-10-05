<?php

/**
 * SiteMetaService — josworld's own values for the ntdst-baseline modules, and the front-end
 * tidying baseline leaves to the site. Baseline is shared and holds no site value; every fact
 * here is one the business publishes on its own site.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\site;

defined('ABSPATH') || exit;

final class SiteMetaService
{
    private const NAME = 'Jos';

    private const BASELINE = 'We brengen je dichter bij de mensen.';

    private const PROFILES = [
        'https://www.facebook.com/josworldbrussels/',
        'https://www.instagram.com/josworld.agency/',
    ];

    /** Only these ask to be indexed; staging and every local copy must not compete with them. */
    private const INDEXABLE_HOSTS = ['jos.agency', 'www.jos.agency'];

    private const BODY_CLASS_NOISE = '/^(wp-singular|wp-embed-responsive|wp-theme-.+|wp-child-theme-.+|page-id-\d+'
        . '|postid-\d+|parent-pageid-\d+|page-template.*|single-format-.+|page-parent|page-child)$/';

    public function __construct()
    {
        add_filter('pre_option_blog_public', [$this, 'filterBlogPublic']);
        add_filter('body_class', [$this, 'cleanBodyClasses']);
        add_action('wp_default_scripts', [$this, 'dropJqueryMigrate']);
        // The page's excerpt is its meta description: baseline reads it before the content.
        add_action('init', static fn () => add_post_type_support('page', 'excerpt'));

        add_filter('ntdst/baseline/schema/config', [$this, 'schemaConfig']);
        add_filter('ntdst/baseline/seo/config', [$this, 'seoConfig']);
        add_filter('ntdst/baseline/head_cleanup/config', [$this, 'headCleanupConfig']);
    }

    /**
     * Read from home_url, the site's own configured address, not the request's Host header.
     *
     * @param mixed $pre the option's stored value passes through on production
     * @return mixed
     */
    public function filterBlogPublic($pre)
    {
        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);

        return $this->isIndexableHost($host) ? $pre : '0';
    }

    public function isIndexableHost(string $host): bool
    {
        return in_array(strtolower($host), self::INDEXABLE_HOSTS, true);
    }

    /**
     * @param list<string> $classes
     * @return list<string>
     */
    public function cleanBodyClasses(array $classes): array
    {
        return array_values(array_filter(
            $classes,
            static fn (string $class): bool => preg_match(self::BODY_CLASS_NOISE, $class) !== 1,
        ));
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function schemaConfig(array $config): array
    {
        return array_merge($config, [
            'type'            => 'localbusiness',
            'name'            => self::NAME,
            'description'     => self::BASELINE,
            'logo'            => $this->imageUrl('logo.png'),
            'social_profiles' => self::PROFILES,
            'business'        => [
                'type'      => 'ProfessionalService',
                'telephone' => '+32 2 411 20 54',
                'email'     => 'jos@jos.agency',
                'address'   => [
                    'street'      => 'Lakensestraat 99',
                    'city'        => 'Brussel',
                    'region'      => 'Brussel-Hoofdstad',
                    'postal_code' => '1000',
                    'country'     => 'BE',
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function seoConfig(array $config): array
    {
        return array_merge($config, ['og_image_fallback' => $this->imageUrl('og-default.png')]);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function headCleanupConfig(array $config): array
    {
        return array_merge($config, ['remove_block_styles' => true]);
    }

    /**
     * jQuery itself stays (FluentForms needs it); the back-compat shim for pre-1.9 code does not.
     *
     * @param object $scripts WP_Scripts
     */
    public function dropJqueryMigrate($scripts): void
    {
        if (is_admin() || ! isset($scripts->registered['jquery'])) {
            return;
        }

        $jquery = $scripts->registered['jquery'];
        $jquery->deps = array_values(array_diff((array) $jquery->deps, ['jquery-migrate']));
    }

    private function imageUrl(string $file): string
    {
        return get_stylesheet_directory_uri() . '/images/' . $file;
    }
}
