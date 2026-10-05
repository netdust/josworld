<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use josworld\services\site\SiteMetaService;

/**
 * josworld's own values for ntdst-baseline, and the front-end tidying it leaves to the site.
 * The values are the business's real, public ones; a wrong one is published as structured data.
 */
final class SiteMetaServiceTest extends TestCase
{
    private const FACEBOOK = 'https://www.facebook.com/josworldbrussels/';
    private const INSTAGRAM = 'https://www.instagram.com/josworld.agency/';

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__
            . '/../../app/content/mu-plugins/josworld-core/services/site/SiteMetaService.php';

        Functions\when('get_stylesheet_directory_uri')->justReturn('https://jos.test/content/themes/josworld');
    }

    private function service(): SiteMetaService
    {
        return new SiteMetaService();
    }

    public function test_it_hooks_every_thing_it_owns(): void
    {
        $filters = [
            'pre_option_blog_public',
            'body_class',
            'ntdst/baseline/schema/config',
            'ntdst/baseline/seo/config',
            'ntdst/baseline/head_cleanup/config',
        ];

        foreach ($filters as $hook) {
            Filters\expectAdded($hook)->once();
        }
        foreach (['wp_default_scripts', 'init'] as $hook) {
            Actions\expectAdded($hook)->once();
        }

        new SiteMetaService();
    }

    /**
     * @dataProvider hosts
     */
    public function test_only_the_production_host_is_indexable(string $host, bool $indexable): void
    {
        $this->assertSame($indexable, $this->service()->isIndexableHost($host));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function hosts(): array
    {
        return [
            'production'      => ['jos.agency', true],
            'production www'  => ['www.jos.agency', true],
            'case-insensitive' => ['Jos.Agency', true],
            'staging'         => ['staging.jos.agency', false],
            'local ddev'      => ['josworld.ddev.site', false],
            'a lookalike'     => ['jos.agency.evil.test', false],
            'empty'           => ['', false],
        ];
    }

    public function test_page_ids_and_theme_fingerprints_leave_the_body_class(): void
    {
        $in = [
            'home', 'page', 'page-id-11', 'wp-singular', 'page-template-default', 'wp-embed-responsive',
            'wp-theme-yootheme', 'wp-child-theme-josworld', 'postid-7', 'logged-in', 'admin-bar', 'single',
            'single-format-standard',
        ];

        $this->assertSame(
            ['home', 'page', 'logged-in', 'admin-bar', 'single'],
            $this->service()->cleanBodyClasses($in),
        );
    }

    public function test_the_business_is_described_with_its_real_public_details(): void
    {
        $config = $this->service()->schemaConfig(['type' => null]);

        $this->assertSame('localbusiness', $config['type']);
        $this->assertSame('Jos', $config['name']);
        $this->assertSame('We brengen je dichter bij de mensen.', $config['description']);
        $this->assertSame('https://jos.test/content/themes/josworld/images/logo.png', $config['logo']);
        $this->assertSame([self::FACEBOOK, self::INSTAGRAM], $config['social_profiles']);
        $this->assertSame('ProfessionalService', $config['business']['type']);
        $this->assertSame('+32 2 411 20 54', $config['business']['telephone']);
        $this->assertSame('jos@jos.agency', $config['business']['email']);
        $this->assertSame(
            [
                'street'      => 'Lakensestraat 99',
                'city'        => 'Brussel',
                'region'      => 'Brussel-Hoofdstad',
                'postal_code' => '1000',
                'country'     => 'BE',
            ],
            $config['business']['address'],
        );
    }

    public function test_link_previews_fall_back_to_the_logo_card(): void
    {
        $config = $this->service()->seoConfig(['og_tags' => true]);

        $this->assertSame(
            'https://jos.test/content/themes/josworld/images/og-default.png',
            $config['og_image_fallback'],
        );
        $this->assertTrue($config['og_tags']);
    }

    public function test_the_block_editor_styles_are_dropped(): void
    {
        $config = $this->service()->headCleanupConfig(['clean_head' => true]);

        $this->assertTrue($config['remove_block_styles']);
    }

    public function test_jquery_migrate_is_dropped_on_the_front_end_only(): void
    {
        Functions\when('is_admin')->justReturn(false);
        $scripts = $this->scripts();

        $this->service()->dropJqueryMigrate($scripts);

        $this->assertSame(['jquery-core'], $scripts->registered['jquery']->deps);
    }

    public function test_jquery_migrate_stays_in_the_admin(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $scripts = $this->scripts();

        $this->service()->dropJqueryMigrate($scripts);

        $this->assertSame(['jquery-core', 'jquery-migrate'], $scripts->registered['jquery']->deps);
    }

    private function scripts(): object
    {
        return (object) ['registered' => ['jquery' => (object) ['deps' => ['jquery-core', 'jquery-migrate']]]];
    }
}
