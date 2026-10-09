<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolService;
use WP_UnitTestCase;

/**
 * A tool is a downloadable file OR a link to an external page. One function decides which
 * (ToolService::targetUrl) and the download form and the builder both ask it.
 */
final class ToolTargetTest extends WP_UnitTestCase
{
    /** @param array<string, mixed> $meta */
    private function tool(array $meta): int
    {
        $id = self::factory()->post->create(['post_type' => 'tool', 'post_status' => 'publish']);
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

    public function test_a_link_tool_targets_its_url(): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool']);

        $this->assertSame('https://example.org/tool', ToolService::targetUrl($id));
    }

    public function test_a_file_tool_targets_its_attachment(): void
    {
        $id = $this->tool([]);
        update_post_meta($id, '_jw_file', $this->attachment($id));
        update_post_meta($id, '_jw_delivery', 'file');

        $this->assertStringEndsWith('checklist.pdf', ToolService::targetUrl($id));
    }

    public function test_a_tool_saved_before_the_choice_existed_stays_a_file_tool(): void
    {
        $id = $this->tool([]);
        update_post_meta($id, '_jw_file', $this->attachment($id));

        $this->assertStringEndsWith('checklist.pdf', ToolService::targetUrl($id));
    }

    public function test_the_choice_decides_when_both_are_filled_in(): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool']);
        update_post_meta($id, '_jw_file', $this->attachment($id));

        $this->assertSame('https://example.org/tool', ToolService::targetUrl($id));
    }

    /** @dataProvider unsafeUrls */
    public function test_a_link_that_is_not_http_is_no_target(string $url): void
    {
        $id = $this->tool(['delivery' => 'url', 'url' => $url]);

        $this->assertSame('', ToolService::targetUrl($id));
    }

    /** @return array<string, array{string}> */
    public static function unsafeUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'mailto' => ['mailto:a@b.c'],
            'empty' => [''],
        ];
    }

    public function test_the_editor_shows_only_the_field_of_the_chosen_kind(): void
    {
        $schema = ntdst_data()->get('tool')->getSchema();

        $this->assertSame('select', $schema['delivery']['type']);
        $this->assertSame(['delivery' => 'file'], $schema['file']['condition']);
        $this->assertSame(['delivery' => 'url'], $schema['url']['condition']);
        $this->assertSame('url', $schema['url']['type']);
    }
}
