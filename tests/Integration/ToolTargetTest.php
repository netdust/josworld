<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolService;
use WP_UnitTestCase;

/**
 * A tool is a downloadable file OR a link to an external page. One answer decides which
 * (ToolService::target) and the download form and the builder both ask it.
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

    private function attachment(int $toolId): int
    {
        $attachment = ['post_mime_type' => 'application/pdf', 'post_title' => 'checklist', 'post_status' => 'inherit'];

        return (int) wp_insert_attachment($attachment, 'checklist.pdf', $toolId);
    }

    /** @return array{url: string, isLink: bool} */
    private function target(int $toolId): array
    {
        return ntdst_get(ToolService::class)->target($toolId);
    }

    public function test_a_link_tool_targets_its_url_and_says_it_is_a_link(): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool']);

        $this->assertSame(['url' => 'https://example.org/tool', 'isLink' => true], $this->target($id));
    }

    public function test_a_file_tool_targets_its_attachment(): void
    {
        $id = $this->tool(['delivery' => 'file']);
        update_post_meta($id, '_jw_file', $this->attachment($id));

        $target = $this->target($id);

        $this->assertStringEndsWith('checklist.pdf', $target['url']);
        $this->assertFalse($target['isLink']);
    }

    public function test_a_tool_saved_before_the_choice_existed_stays_a_file_tool(): void
    {
        $id = $this->tool([]);
        update_post_meta($id, '_jw_file', $this->attachment($id));

        $this->assertStringEndsWith('checklist.pdf', $this->target($id)['url']);
    }

    public function test_the_choice_decides_when_both_are_filled_in(): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool']);
        update_post_meta($id, '_jw_file', $this->attachment($id));

        $this->assertSame('https://example.org/tool', $this->target($id)['url']);
    }

    public function test_an_unpublished_tool_still_resolves_so_the_builder_preview_works(): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool'], 'draft');

        $this->assertSame('https://example.org/tool', $this->target($id)['url']);
    }

    /** @dataProvider unsafeUrls */
    public function test_a_link_that_is_not_an_http_address_with_a_host_is_no_target(string $url): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => $url]);

        $this->assertSame('', $this->target($id)['url']);
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

        $this->assertSame('http://example.org/tool', $this->target($id)['url']);
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
