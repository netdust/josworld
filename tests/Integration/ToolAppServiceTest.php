<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolAppService;
use WP_UnitTestCase;

/**
 * The interactive tools (campagnestart, faire-aanbestedingsscan) are standalone pages in the theme's
 * tools/ folder, served only at a personal link (ToolGrantAccessTest). Only a file that exists there
 * can be served: the slug is a whitelist lookup, never a path.
 */
final class ToolAppServiceTest extends WP_UnitTestCase
{
    private function service(): ToolAppService
    {
        return ntdst_get(ToolAppService::class);
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
}
