<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolService;
use WP_UnitTestCase;

/**
 * A tool is a downloadable file OR a link to a page. ToolService::link is the one reader of the
 * link: a safe address for a link-kind tool, nothing for a file tool. (`target` is the tool's own
 * page: ToolGrantAccessTest.)
 */
final class ToolTargetTest extends WP_UnitTestCase
{
    /** @param array<string, mixed> $meta */
    private function tool(array $meta, string $status = 'publish'): int
    {
        $id = self::factory()->post->create(['post_type' => 'tool', 'post_status' => $status]);
        foreach ($meta as $key => $value) {
            update_post_meta($id, '_jw_' . $key, $value);
        }

        return $id;
    }

    private function link(int $toolId): string
    {
        return ntdst_get(ToolService::class)->link($toolId);
    }

    public function test_a_link_tool_gives_its_url(): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool']);

        $this->assertSame('https://example.org/tool', $this->link($id));
    }

    public function test_a_file_tool_has_no_link_even_with_one_filled_in(): void
    {
        $this->assertSame('', $this->link($this->tool(['delivery' => 'file', 'url' => 'https://example.org/tool'])));
    }

    public function test_a_tool_saved_before_the_choice_existed_is_a_file_tool(): void
    {
        $this->assertSame('', $this->link($this->tool(['url' => 'https://example.org/tool'])));
    }

    public function test_an_unpublished_tool_still_resolves_so_the_builder_preview_works(): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool'], 'draft');

        $this->assertSame('https://example.org/tool', $this->link($id));
    }

    /** @dataProvider unsafeUrls */
    public function test_a_link_that_is_not_an_http_address_with_a_host_is_no_link(string $url): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => $url]);

        $this->assertSame('', $this->link($id));
    }

    /** @return array<string, array{string}> */
    public static function unsafeUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'mailto' => ['mailto:a@b.c'],
            'ftp' => ['ftp://example.org/x'],
            'protocol-relative' => ['//evil.example/x'],
            'triple slash' => ['///evil.example'],
            'site-relative path' => ['/wp-admin/'],
            'scheme without slashes' => ['http:evil.example'],
            'empty' => [''],
        ];
    }

    public function test_an_address_without_a_scheme_is_completed_by_wordpress_as_http(): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => 'example.org/tool']);

        $this->assertSame('http://example.org/tool', $this->link($id));
    }

    public function test_the_schema_declares_which_field_belongs_to_which_kind(): void
    {
        $schema = ntdst_data()->get('tool')->getSchema();

        $this->assertSame('select', $schema['delivery']['type']);
        $this->assertSame(['delivery' => 'file'], $schema['file']['condition']);
        $this->assertSame(['delivery' => 'url'], $schema['url']['condition']);
        $this->assertSame('url', $schema['url']['type']);
    }
}
