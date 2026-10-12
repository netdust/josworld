<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Functions;
use josworld\services\content\ToolService;
use josworld\services\forms\DownloadFormService;
use Mockery;
use NtdstTests\Support\WpPostStub;

/**
 * The confirmation filter: a user-controlled hidden id becomes a link only for a published tool that
 * ToolService can point somewhere (a file or a safe link); everything else degrades to the plain
 * thank-you, and a foreign form passes through untouched. ToolService's own answer is proven against
 * real WordPress (ToolTargetTest); here it is a fake behind the container.
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
        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }

        Functions\when('add_filter')->justReturn(true);
        Functions\when('add_action')->justReturn(true);
        Functions\when('__')->returnArg();
        Functions\when('absint')->alias(static fn ($v): int => abs((int) $v));
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('get_the_title')->alias(static fn ($post) => $post->post_title);
    }

    /**
     * A real ToolService over a fake model: where the visitor goes, and whether that is a link.
     * (Its link rules are proven against real WordPress in ToolTargetTest.)
     */
    private function tools(string $url, bool $isLink = false): ToolService
    {
        $fileId = $url === '' ? 0 : 219;
        $meta = $isLink ? ['delivery' => 'url', 'url' => $url] : ['delivery' => 'file', 'file' => $fileId];
        Functions\when('wp_get_attachment_url')->justReturn($url === '' ? false : $url);
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('wp_parse_url')->alias('parse_url');

        $model = Mockery::mock();
        $model->shouldReceive('getMeta')->andReturnUsing( // @phpstan-ignore method.notFound
            static fn ($id, $key, $default = null) => $meta[$key] ?? $default
        );
        $manager = Mockery::mock('NTDST_Data_Manager');
        $manager->shouldReceive('register');
        $manager->shouldReceive('get')->andReturn($model);

        return new ToolService($manager); // @phpstan-ignore argument.type
    }

    /** @return array<string, mixed> */
    private function confirm(ToolService $tools, string $message = self::MESSAGE, string $toolId = '163'): array
    {
        return (new DownloadFormService($tools))
            ->injectToolDownload(['message' => $message], null, null, 1, ['tool_id' => $toolId, 'email' => 'a@b.c']);
    }

    public function test_a_form_without_the_hidden_field_passes_through_untouched(): void
    {
        $in = ['message' => 'Thanks', 'redirectUrl' => ''];

        $service = new DownloadFormService($this->tools(''));
        $out = $service->injectToolDownload($in, null, null, 1, ['email' => 'a@b.c']);

        $this->assertSame($in, $out);
    }

    public function test_a_published_tool_with_a_file_gets_its_download_link(): void
    {
        Functions\when('get_post')->justReturn(new WpPostStub());
        $out = $this->confirm($this->tools('https://josworld.test/content/uploads/checklist.pdf'));

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

        $out = $this->confirm($this->tools('https://x.test/f.pdf'), self::MESSAGE, $toolId);

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
        $out = $this->confirm($this->tools(''));

        $this->assertStringContainsString('nog niet beschikbaar', $out['message']);
        $this->assertStringNotContainsString('{tool_file_url}', $out['message']);
    }

    public function test_a_link_tool_sends_the_visitor_to_its_url_and_is_opened_not_saved(): void
    {
        Functions\when('get_post')->justReturn(new WpPostStub());
        $tools = $this->tools('https://example.org/tool', true);

        $out = $this->confirm($tools, '<p><a href="{tool_file_url}" download>{tool_title} {tool_action}</a></p>');

        $this->assertSame('<p><a href="https://example.org/tool">Checklist openen</a></p>', $out['message']);
    }

    public function test_a_file_tool_says_downloaden_and_keeps_the_download_attribute(): void
    {
        Functions\when('get_post')->justReturn(new WpPostStub());
        $tools = $this->tools('https://josworld.test/f.pdf');

        $out = $this->confirm($tools, '<a href="{tool_file_url}" download>{tool_title} {tool_action}</a>');

        $this->assertSame('<a href="https://josworld.test/f.pdf" download>Checklist downloaden</a>', $out['message']);
    }

    public function test_a_link_tool_with_no_usable_link_degrades_to_the_plain_thank_you(): void
    {
        Functions\when('get_post')->justReturn(new WpPostStub());
        $out = $this->confirm($this->tools('', true));

        $this->assertStringContainsString('nog niet beschikbaar', $out['message']);
    }
}
