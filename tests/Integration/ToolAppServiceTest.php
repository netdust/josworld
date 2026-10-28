<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolAppService;
use WP_UnitTestCase;

/**
 * The interactive tools (campagnestart, faire-aanbestedingsscan) are standalone pages in the theme's
 * tools/ folder, served only at a personal link (ToolGrantAccessTest), through renderForGrant(). Only a
 * file that exists there can be served: the slug is a whitelist lookup, never a path.
 */
final class ToolAppServiceTest extends WP_UnitTestCase
{
    /** @return array{0: string|false|null, 1: string} what renderForGrant returned and what it echoed */
    private function renderLink(string $url): array
    {
        $tool = self::factory()->post->create_and_get(['post_type' => 'tool', 'post_status' => 'publish']);
        update_post_meta($tool->ID, '_jw_delivery', 'url');
        update_post_meta($tool->ID, '_jw_url', $url);
        // The app path never reads the grant; a plain post stands in for it.
        $grant = self::factory()->post->create_and_get();

        ob_start();
        $answer = ntdst_get(ToolAppService::class)->renderForGrant($tool, $grant);

        return [$answer, (string) ob_get_clean()];
    }

    public function test_a_tool_page_is_the_themed_page_not_a_wordpress_template(): void
    {
        [$answer, $html] = $this->renderLink('https://jos.agency/tool-app/campagnestart/');

        $this->assertNull($answer, 'null: the app is the answer');
        $this->assertStringStartsWith('<!DOCTYPE html>', $html, 'the PHP guard line prints nothing');
        $this->assertStringContainsString('Zet de basis voor je campagne', $html);
        $this->assertStringContainsString('/content/themes/josworld/fonts/', $html, 'the site\'s own fonts');
        $this->assertStringNotContainsString('fonts.googleapis.com', $html, 'no call to Google');
        $this->assertStringContainsString('name="robots" content="noindex"', $html);
    }

    /** @dataProvider notATool */
    public function test_anything_that_is_not_a_tool_file_is_not_served(string $slug): void
    {
        [$answer, $printed] = $this->renderLink('https://jos.agency/tool-app/' . $slug . '/');

        $this->assertFalse($answer, 'false: baseline answers with its dead page');
        $this->assertSame('', $printed);
    }

    /** @return array<string, array{string}> */
    public static function notATool(): array
    {
        return [
            'unknown' => ['bestaat-niet'],
            'encoded traversal' => ['..%2f..%2fwp-config'],
            'dot dot' => ['..'],
            'a file that is not an app' => ['logo'],
            'uppercase' => ['CAMPAGNESTART'],
            'the old static name' => ['campagnestart.html'],
            'with extension' => ['campagnestart.html.php'],
        ];
    }
}
