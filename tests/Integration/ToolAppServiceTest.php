<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolAppService;
use WP_UnitTestCase;

/**
 * The interactive tools (campagnestart, faire-aanbestedingsscan) are standalone pages in the theme's
 * tools/ folder, served at /tool-app/<slug>/. Only a file that exists there can be served: the slug is
 * a whitelist lookup, never a path.
 */
final class ToolAppServiceTest extends WP_UnitTestCase
{
    private function service(): ToolAppService
    {
        return ntdst_get(ToolAppService::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->set_permalink_structure('/%postname%/');
        $this->service()->addRoute();
        flush_rewrite_rules();
    }

    public function test_the_two_tools_are_online_at_a_clean_url(): void
    {
        $this->assertSame(home_url('/tool-app/campagnestart/'), $this->service()->url('campagnestart'));

        $this->go_to(home_url('/tool-app/faire-aanbestedingsscan/'));

        $this->assertSame('faire-aanbestedingsscan', get_query_var(ToolAppService::QUERY_VAR));
    }

    public function test_a_tool_page_is_the_themed_page_not_a_wordpress_template(): void
    {
        $html = $this->service()->render('campagnestart');

        $this->assertIsString($html);
        $this->assertStringContainsString('Zet de basis voor je campagne', $html);
        $this->assertStringContainsString('/content/themes/josworld/fonts/', $html, 'the site\'s own fonts');
        $this->assertStringNotContainsString('fonts.googleapis.com', $html, 'no call to Google');
        $this->assertStringContainsString('name="robots" content="noindex"', $html);
    }

    /** @dataProvider notATool */
    public function test_anything_that_is_not_a_tool_file_is_not_served(string $slug): void
    {
        $this->assertNull($this->service()->render($slug));
    }

    /** @return array<string, array{string}> */
    public static function notATool(): array
    {
        return [
            'unknown' => ['bestaat-niet'],
            'empty' => [''],
            'traversal' => ['../../../wp-config'],
            'encoded traversal' => ['..%2f..%2fwp-config'],
            'a file that is not html' => ['logo'],
            'uppercase' => ['CAMPAGNESTART'],
            'with extension' => ['campagnestart.html'],
        ];
    }

    public function test_the_route_rewrites_only_simple_slugs(): void
    {
        $rules = get_option('rewrite_rules');
        $pattern = array_search('index.php?' . ToolAppService::QUERY_VAR . '=$matches[1]', $rules, true);

        $this->assertNotFalse($pattern);
        $this->assertSame(1, preg_match('#' . $pattern . '#', 'tool-app/campagnestart/'));
        $this->assertSame(0, preg_match('#' . $pattern . '#', 'tool-app/../x/'));
    }
}
