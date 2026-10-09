<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Functions;
use josworld\services\forms\DownloadFormService;
use Mockery;
use NtdstTests\Support\WpPostStub;

/**
 * The confirmation filter: a user-controlled hidden id becomes a download
 * link only for a published tool with a file; everything else degrades to
 * the plain thank-you, and a foreign form passes through untouched.
 */
final class DownloadFormServiceTest extends TestCase
{
    private const MESSAGE = '<p>Bedankt: <a href="{tool_file_url}" download>{tool_title} downloaden</a></p>';

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../Support/WpPostStub.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/ToolService.php';
        require_once __DIR__
            . '/../../app/content/mu-plugins/josworld-core/services/forms/DownloadFormService.php';

        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        Functions\when('add_filter')->justReturn(true);
        Functions\when('absint')->alias(static fn ($v): int => abs((int) $v));
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_url_raw')->alias(
            static fn (string $url, ?array $protocols = null): string => preg_match('#^https?://#i', $url) ? $url : ''
        );
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('get_the_title')->alias(static fn ($post) => $post->post_title);
    }

    public function test_a_form_without_the_hidden_field_passes_through_untouched(): void
    {
        $in = ['message' => 'Thanks', 'redirectUrl' => ''];

        $out = (new DownloadFormService())->injectToolDownload($in, null, null, 1, ['email' => 'a@b.c']);

        $this->assertSame($in, $out);
    }

    public function test_a_published_tool_with_a_file_gets_its_download_link(): void
    {
        $tool = new WpPostStub();
        Functions\when('get_post')->justReturn($tool);
        Functions\when('wp_get_attachment_url')->justReturn('https://josworld.test/content/uploads/checklist.pdf');
        $this->modelReturning(['file' => 219]);

        $out = (new DownloadFormService())->injectToolDownload(
            ['message' => self::MESSAGE],
            null,
            null,
            1,
            ['tool_id' => '163', 'email' => 'a@b.c'],
        );

        $this->assertSame(
            '<p>Bedankt: <a href="https://josworld.test/content/uploads/checklist.pdf" download>'
            . 'Checklist downloaden</a></p>',
            $out['message'],
        );
    }

    /**
     * @dataProvider notDownloadable
     * @param mixed $post
     */
    public function test_anything_but_a_published_tool_degrades_to_the_plain_thank_you($post, string $toolId): void
    {
        Functions\when('get_post')->justReturn($post);

        $out = (new DownloadFormService())
            ->injectToolDownload(['message' => self::MESSAGE], null, null, 1, ['tool_id' => $toolId]);

        $this->assertStringNotContainsString('{tool_file_url}', $out['message']);
        $this->assertStringNotContainsString('<a ', $out['message']);
        $this->assertStringContainsString('nog niet beschikbaar', $out['message']);
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function notDownloadable(): array
    {
        // Providers run before setUp(): load the stand-in here too.
        require_once __DIR__ . '/../Support/WpPostStub.php';

        $draft = new WpPostStub();
        $draft->post_status = 'draft';
        $page = new WpPostStub();
        $page->post_type = 'page';

        return [
            'garbage id' => [null, 'abc'],
            'unknown id' => [null, '999999'],
            'draft tool' => [$draft, '5'],
            'a page, not a tool' => [$page, '20'],
        ];
    }

    public function test_a_tool_without_a_file_degrades_to_the_plain_thank_you(): void
    {
        Functions\when('get_post')->justReturn(new WpPostStub());
        Functions\when('wp_get_attachment_url')->justReturn(false);
        $this->modelReturning(['file' => 0]);

        $out = (new DownloadFormService())
            ->injectToolDownload(['message' => self::MESSAGE], null, null, 1, ['tool_id' => '163']);

        $this->assertStringContainsString('nog niet beschikbaar', $out['message']);
        $this->assertStringNotContainsString('{tool_file_url}', $out['message']);
    }

    /** @param array<string, mixed> $meta */
    private function modelReturning(array $meta): void
    {
        $model = new class ($meta) {
            /** @param array<string, mixed> $meta */
            public function __construct(private array $meta)
            {
            }

            public function getMeta(int $id, string $key, mixed $default = null): mixed
            {
                return $this->meta[$key] ?? $default;
            }
        };
        $manager = new class ($model) {
            public function __construct(private object $model)
            {
            }

            public function get(string $type): object
            {
                return $this->model;
            }
        };
        Functions\when('ntdst_data')->justReturn($manager);
    }

    public function test_a_link_tool_sends_the_visitor_to_its_url(): void
    {
        Functions\when('get_post')->justReturn(new WpPostStub());
        $this->modelReturning(['delivery' => 'url', 'url' => 'https://example.org/tool', 'file' => 219]);

        $out = (new DownloadFormService())->injectToolDownload(
            ['message' => '<a href="{tool_file_url}">{tool_title} {tool_action}</a>'],
            null,
            null,
            1,
            ['tool_id' => '163'],
        );

        $this->assertSame('<a href="https://example.org/tool">Checklist openen</a>', $out['message']);
    }

    public function test_a_file_tool_says_downloaden(): void
    {
        Functions\when('get_post')->justReturn(new WpPostStub());
        Functions\when('wp_get_attachment_url')->justReturn('https://josworld.test/f.pdf');
        $this->modelReturning(['file' => 219]);

        $out = (new DownloadFormService())->injectToolDownload(
            ['message' => '<a href="{tool_file_url}">{tool_title} {tool_action}</a>'],
            null,
            null,
            1,
            ['tool_id' => '163'],
        );

        $this->assertSame('<a href="https://josworld.test/f.pdf">Checklist downloaden</a>', $out['message']);
    }

    /** @dataProvider hostileOrEmptyUrls */
    public function test_a_link_tool_without_a_safe_url_degrades_to_the_plain_thank_you(string $url): void
    {
        Functions\when('get_post')->justReturn(new WpPostStub());
        $this->modelReturning(['delivery' => 'url', 'url' => $url]);

        $out = (new DownloadFormService())
            ->injectToolDownload(['message' => self::MESSAGE], null, null, 1, ['tool_id' => '163']);

        $this->assertStringContainsString('nog niet beschikbaar', $out['message']);
        $this->assertStringNotContainsString('javascript', $out['message']);
    }

    /** @return array<string, array{string}> */
    public static function hostileOrEmptyUrls(): array
    {
        return [
            'empty' => [''],
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'no scheme' => ['example.org/tool'],
        ];
    }
}
