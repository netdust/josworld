<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolAppService;
use josworld\services\content\ToolLinkPage;
use josworld\services\content\ToolService;
use ntdst\baseline\services\grants\GrantRepository;
use ntdst\baseline\services\grants\GrantService;
use ntdst\baseline\services\grants\LinkRoute;
use ntdst\baseline\services\grants\PrivateFiles;
use WP_UnitTestCase;

/**
 * The personal link page wears the tool page's header: the `single-tool` template's first section, rendered by
 * the builder for the granted tool, its button turned into the link's action. The builder is not installed in
 * this tier, so a recording builder stands in for the render step; what it is handed is the contract. The real
 * look is proven on DDEV.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class ToolLinkPageTest extends WP_UnitTestCase
{
    private const EMAIL = 'link-page@example.test';
    private const OVERRIDE = 'ntdst-baseline/grants/link.php';
    /** JOS's pink button, the header's "Contacteer ons", is the style's secondary; its primary is aubergine. */
    private const PINK = 'secondary';

    private string $store = '';
    private string $storedOption = '';

    /** @var list<array{layout: object, params: array<string, mixed>, post: int}> */
    public array $builds = [];

    public function set_up(): void
    {
        parent::set_up();
        reset_phpmailer_instance();

        $this->store = sys_get_temp_dir() . '/jw-link-page-' . uniqid();
        add_filter('ntdst/baseline/grants/config', fn (array $c): array => ['store' => $this->store] + $c, 99);
        add_filter('stylesheet', static fn (): string => 'josworld');
        add_filter('template', static fn (): string => 'yootheme');
        self::forgetThemePaths();
        GrantService::reset();
        LinkRoute::reset();
        ntdst_set(PrivateFiles::class); // a fresh store: the shared instance keeps the last test's directory

        $template = json_decode((string) file_get_contents(__DIR__ . '/fixtures/single-tool-template.json'));
        $this->storedOption = (string) wp_json_encode(['templates' => ['goYeARw1' => $template]]);
        update_option('yootheme', $this->storedOption);
    }

    public function tear_down(): void
    {
        ntdst_set(ToolLinkPage::class);
        ntdst_set(ToolAppService::class);
        ntdst_set(PrivateFiles::class);
        GrantService::reset();
        LinkRoute::reset();
        parent::tear_down();
        self::forgetThemePaths();
        if (is_dir($this->store)) {
            exec('rm -rf ' . escapeshellarg($this->store));
        }
    }

    /** locate_template() caches the theme's paths in globals on first use. */
    private static function forgetThemePaths(): void
    {
        unset($GLOBALS['wp_stylesheet_path'], $GLOBALS['wp_template_path']);
    }

    /** The builder's render step records what it was handed and who the current post was. */
    private function recordingBuilder(): void
    {
        $test = $this;
        $page = new class ($test) extends ToolLinkPage {
            public function __construct(private readonly ToolLinkPageTest $test)
            {
            }

            /** @param array<string, mixed> $params */
            protected function build(object $layout, array $params): string
            {
                $post = (int) ($GLOBALS['post']->ID ?? 0);
                $this->test->builds[] = ['layout' => $layout, 'params' => $params, 'post' => $post];

                return '<!-- built -->';
            }
        };
        ntdst_set(ToolLinkPage::class, static fn () => $page);
        ntdst_set(ToolAppService::class, static fn () => new ToolAppService(ntdst_get(ToolService::class), $page));
    }

    /** @param array<string, mixed> $meta */
    private function tool(array $meta, string $content = ''): int
    {
        $id = self::factory()->post->create([
            'post_type' => 'tool',
            'post_status' => 'publish',
            'post_title' => 'Contentkalender',
            'post_content' => $content,
        ]);
        foreach ($meta as $key => $value) {
            update_post_meta($id, '_jw_' . $key, $value);
        }

        return $id;
    }

    /** @return array{0: int, 1: string} the file tool and its file's public uploads URL before it was gated */
    private function fileTool(): array
    {
        $ownLayout = "<p>Fallback</p>\n<!-- {\"type\":\"layout\",\"version\":\"5.0.43\",\"children\":[]} -->";
        $toolId = $this->tool(['delivery' => 'file'], $ownLayout);
        $upload = wp_upload_bits('kalender-' . uniqid() . '.pdf', null, '%PDF-1.4 kalender');
        $this->assertFalse($upload['error']);
        $attachment = (int) wp_insert_attachment(
            ['post_mime_type' => 'application/pdf', 'post_title' => 'kalender', 'post_status' => 'inherit'],
            $upload['file'],
            $toolId,
        );
        update_post_meta($toolId, '_jw_file', $attachment);
        $this->assertTrue(ntdst_get(PrivateFiles::class)->move($attachment));

        return [$toolId, $upload['url']];
    }

    private function grantFor(int $toolId): \WP_Post
    {
        $issued = ntdst_get(GrantService::class)->issue(self::EMAIL, $toolId);
        $this->assertSame('issued', $issued['status']);

        return ntdst_get(GrantRepository::class)->forContent($toolId)[0];
    }

    /**
     * The real route, answering through the template WordPress locates for it, as baseline's render() does
     * minus the theme's header and footer.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function open(string $token): array
    {
        $route = new class (ntdst_get(GrantRepository::class), ntdst_get(PrivateFiles::class)) extends LinkRoute {
            /** @var array<string, mixed> */
            public array $args = [];

            protected function sendHeader(string $line, bool $replace): void
            {
            }

            /** @param array<string, mixed> $args */
            protected function render(array $args): void
            {
                $this->args = $args;
                load_template(locate_template(['ntdst-baseline/grants/link.php']), false, $args);
            }
        };

        ob_start();
        $route->serve(['token' => $token]);

        return [(string) ob_get_clean(), $route->args];
    }

    /** @return list<object> every node under $node, depth first */
    private static function nodes(object $node): array
    {
        $all = [];
        foreach ($node->children ?? [] as $child) {
            $all[] = $child;
            array_push($all, ...self::nodes($child));
        }

        return $all;
    }

    /** @return list<object> */
    private static function ofType(object $layout, string $type): array
    {
        return array_values(array_filter(self::nodes($layout), static fn (object $n): bool => $n->type === $type));
    }

    private static function bound(object $node): bool
    {
        return ($node->source->query->name ?? '') === 'tools.singleTool';
    }

    public function test_the_theme_carries_the_link_page_override(): void
    {
        $this->assertSame(
            get_theme_root() . '/josworld/' . self::OVERRIDE,
            locate_template([self::OVERRIDE]),
        );
    }

    public function test_file_page_is_the_tool_header_with_a_pink_download_button(): void
    {
        $this->recordingBuilder();
        [$toolId, $publicUrl] = $this->fileTool();
        $grant = $this->grantFor($toolId);
        $GLOBALS['post'] = null;

        [$printed, $args] = $this->open((string) ($grant->fields['token'] ?? ''));

        $this->assertSame('file', $args['kind']);
        $this->assertStringContainsString('<!-- built -->', $printed);
        $this->assertCount(1, $this->builds);
        ['layout' => $layout, 'params' => $params, 'post' => $post] = $this->builds[0];

        $this->assertSame($toolId, $post, 'the granted tool is the current post while the builder renders');
        $this->assertNull($GLOBALS['post'], 'and the request\'s own post is back afterwards');
        $this->assertSame('single-tool', $params['template']);
        $this->assertSame('layout', $layout->type);
        $this->assertSame('5.0.43', $layout->version, 'a layout without a version is migrated on render');
        $this->assertCount(1, $layout->children);
        $this->assertSame('Pagina-header', $layout->children[0]->name);
        $height = $layout->children[0]->props->height;
        $this->assertSame('viewport', $height, 'a short page would show the reveal footer sliced');
        $this->assertTrue($layout->children[0]->props->height_viewport_offset);

        $buttons = self::ofType($layout, 'button_item');
        $this->assertCount(1, $buttons);
        $label = GrantService::label('download', (string) ($grant->fields['lang'] ?? ''));
        $this->assertSame($label, $buttons[0]->props->content);
        $this->assertSame(self::PINK, $buttons[0]->props->button_style);
        $this->assertSame($args['download_url'], $buttons[0]->props->link);
        $this->assertFalse(isset($buttons[0]->source), 'the tool page\'s own target binding is gone');

        $this->assertTrue(self::bound(self::ofType($layout, 'image')[0]), 'the tool\'s image');
        $headlines = array_filter(self::ofType($layout, 'headline'), [self::class, 'bound']);
        $this->assertCount(2, $headlines, 'title and subtitle');
        $this->assertSame([], self::ofType($layout, 'text'), 'a tool with its own layout has no prose in its content');

        $this->assertStringNotContainsString($publicUrl, $printed . wp_json_encode($layout));
        $this->assertSame($this->storedOption, get_option('yootheme'), 'the template is read, never written');
    }

    public function test_open_page_is_the_tool_header_with_a_pink_open_button(): void
    {
        $this->recordingBuilder();
        $toolId = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool?x=1'], '<p>Over de tool.</p>');
        $grant = $this->grantFor($toolId);

        [$printed, $args] = $this->open((string) ($grant->fields['token'] ?? ''));

        $this->assertSame('html', $args['kind']);
        $this->assertStringContainsString('<!-- built -->', $printed);
        ['layout' => $layout, 'post' => $post] = $this->builds[0];
        $this->assertSame($toolId, $post);

        $buttons = self::ofType($layout, 'button_item');
        $this->assertCount(1, $buttons);
        $label = GrantService::label('open', (string) ($grant->fields['lang'] ?? ''));
        $this->assertSame($label, $buttons[0]->props->content);
        $this->assertSame(self::PINK, $buttons[0]->props->button_style);
        $this->assertSame('https://example.org/tool?x=1', html_entity_decode($buttons[0]->props->link));
        $this->assertTrue($buttons[0]->props->link_rel_noreferrer);
        $this->assertTrue(self::bound(self::ofType($layout, 'text')[0]), 'a tool without a layout keeps its text');
    }

    public function test_dead_page_is_the_header_section_without_a_tool(): void
    {
        $this->recordingBuilder();

        [$printed, $args] = $this->open(str_repeat('0', 64));

        $this->assertSame('dead', $args['kind']);
        $this->assertTrue(is_404());
        $this->assertStringContainsString('<!-- built -->', $printed);
        $layout = $this->builds[0]['layout'];

        $bound = array_values(array_filter(self::nodes($layout), [self::class, 'bound']));
        $this->assertSame([], $bound, 'no tool, no binding');
        $this->assertSame([], self::ofType($layout, 'image'));
        $headlines = self::ofType($layout, 'headline');
        $this->assertCount(1, $headlines);
        $this->assertSame('h1', $headlines[0]->props->title_element);
        $this->assertSame('Deze link werkt niet meer', $headlines[0]->props->content);
        $text = self::ofType($layout, 'text')[0]->props->content;
        $this->assertStringContainsString('De link die je volgde is niet meer geldig.', $text);
        $this->assertSame([], self::ofType($layout, 'button'), 'no way back for a link that named nothing');
    }

    public function test_a_revoked_link_leads_back_to_the_tool_page(): void
    {
        $this->recordingBuilder();
        $toolId = $this->tool(['delivery' => 'url', 'url' => 'https://example.org/tool']);
        $grant = $this->grantFor($toolId);
        ntdst_get(GrantRepository::class)->revoke($grant->ID);

        [, $args] = $this->open((string) ($grant->fields['token'] ?? ''));

        $this->assertSame('dead', $args['kind']);
        $buttons = self::ofType($this->builds[0]['layout'], 'button_item');
        $this->assertCount(1, $buttons);
        $this->assertSame('Naar de tool', $buttons[0]->props->content);
        $this->assertSame(self::PINK, $buttons[0]->props->button_style);
        $this->assertSame(get_permalink($toolId), $buttons[0]->props->link);
    }

    public function test_without_the_builder_baseline_page_stands(): void
    {
        [$toolId] = $this->fileTool();
        $grant = $this->grantFor($toolId);

        [$printed, $args] = $this->open((string) ($grant->fields['token'] ?? ''));

        $this->assertSame('file', $args['kind']);
        $this->assertStringContainsString('href="' . esc_url($args['download_url']) . '"', $printed);
        $label = GrantService::label('download', (string) ($grant->fields['lang'] ?? ''));
        $this->assertStringContainsString($label, $printed);
    }
}
