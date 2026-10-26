<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\ToolAppService;
use josworld\services\content\ToolService;
use ntdst\baseline\services\grants\Cli;
use ntdst\baseline\services\grants\GrantRepository;
use ntdst\baseline\services\grants\GrantService;
use ntdst\baseline\services\grants\LinkRoute;
use ntdst\baseline\services\grants\PrivateFiles;
use WP_UnitTestCase;

use function josworld\services\content\resolve_tool_target;

/**
 * What a visitor reaches with a personal link: a tool app of the site's own opens there and nowhere
 * else, a link to another domain gets an "Open" page, a file tool gets baseline's file page. The
 * builder's `target` is the tool's own page, where the form is.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class ToolGrantAccessTest extends WP_UnitTestCase
{
    private const EMAIL = 'visitor@example.test';

    private string $store = '';

    /** @var array<string, mixed>|null what the route handed its template, null when it rendered nothing */
    private ?array $rendered = null;

    public function set_up(): void
    {
        parent::set_up();
        reset_phpmailer_instance();

        $this->store = sys_get_temp_dir() . '/jw-grants-' . uniqid();
        add_filter('ntdst/baseline/grants/config', fn (array $c): array => ['store' => $this->store] + $c, 99);
        GrantService::reset();
        LinkRoute::reset();
    }

    public function tear_down(): void
    {
        GrantService::reset();
        LinkRoute::reset();
        if (is_dir($this->store)) {
            exec('rm -rf ' . escapeshellarg($this->store));
        }
        parent::tear_down();
    }

    /** @param array<string, mixed> $meta */
    private function tool(array $meta): int
    {
        $id = self::factory()->post->create([
            'post_type' => 'tool',
            'post_status' => 'publish',
            'post_title' => 'Campagnestart',
            'post_name' => 'campagnestart',
        ]);
        foreach ($meta as $key => $value) {
            update_post_meta($id, '_jw_' . $key, $value);
        }

        return $id;
    }

    private function linkTool(string $url): int
    {
        return $this->tool(['delivery' => 'url', 'url' => $url]);
    }

    private function grantFor(int $toolId): \WP_Post
    {
        $issued = ntdst_get(GrantService::class)->issue(self::EMAIL, $toolId);
        $this->assertSame('issued', $issued['status']);

        return ntdst_get(GrantRepository::class)->forContent($toolId)[0];
    }

    /** The personal link served by the real route; returns what it printed itself. */
    private function open(\WP_Post $grant): string
    {
        $route = new class (ntdst_get(GrantRepository::class), ntdst_get(PrivateFiles::class)) extends LinkRoute {
            /** @var \Closure(array<string, mixed>): void */
            public \Closure $onRender;

            protected function sendHeader(string $line, bool $replace): void
            {
            }

            /** @param array<string, mixed> $args */
            protected function render(array $args): void
            {
                ($this->onRender)($args);
            }
        };
        $route->onRender = function (array $args): void {
            $this->rendered = $args;
        };

        ob_start();
        $route->serve(['token' => (string) ($grant->fields['token'] ?? '')]);

        return (string) ob_get_clean();
    }

    public function test_tool_app_opens_at_the_personal_link(): void
    {
        // The host is ignored: staging runs on production's database.
        $grant = $this->grantFor($this->linkTool('https://jos.agency/tool-app/campagnestart/'));

        $printed = $this->open($grant);

        $this->assertStringContainsString('Zet de basis voor je campagne', $printed);
        $this->assertNull($this->rendered, 'the app is the whole answer: no link page around it');
        $reread = ntdst_get(GrantRepository::class)->forContent((int) ($grant->fields['content_id'] ?? 0))[0];
        $this->assertSame(1, (int) $reread->fields['access_count'], 'the open is recorded');
    }

    public function test_the_staging_faire_link_opens_its_app_at_the_personal_link(): void
    {
        $grant = $this->grantFor($this->linkTool('https://staging.jos.agency/tool-app/faire-aanbestedingsscan/'));

        $printed = $this->open($grant);

        $this->assertStringContainsString('<title>Faire aanbestedingsscan | JOS</title>', $printed);
        $this->assertNull($this->rendered, 'the app is the whole answer: no link page around it');
    }

    /** A static file under the theme is served by the web server to anyone: no app may be one. */
    public function test_the_tool_apps_are_not_static_files(): void
    {
        $dir = get_theme_root() . '/josworld/tools/';

        $this->assertSame([], glob($dir . '*.html'), 'no app is a public .html file');
        foreach (['campagnestart', 'faire-aanbestedingsscan'] as $slug) {
            $first = strtok((string) file_get_contents($dir . $slug . '.html.php'), "\n");
            $guard = "<?php defined('ABSPATH') || exit; ?>";
            $this->assertSame($guard, $first, $slug . ' opened directly prints nothing');
        }
    }

    public function test_tool_app_direct_url_is_404(): void
    {
        $this->set_permalink_structure('/%postname%/');
        flush_rewrite_rules();

        $this->go_to(home_url('/tool-app/campagnestart/'));

        $this->assertSame('', (string) get_query_var('ntdst_page'), 'no page route claims the URL');
        $this->assertTrue(is_404());
    }

    public function test_an_unknown_tool_app_on_this_site_is_the_dead_page(): void
    {
        $grant = $this->grantFor($this->linkTool('https://jos.agency/tool-app/bestaat-niet/'));

        $this->assertSame('', $this->open($grant));
        $this->assertSame('dead', $this->rendered['kind'] ?? null);
    }

    public function test_external_link_tool_shows_an_open_button(): void
    {
        $grant = $this->grantFor($this->linkTool('https://example.org/tool?x=1'));

        $this->assertSame('', $this->open($grant));

        $this->assertSame('html', $this->rendered['kind'] ?? null);
        $page = wp_kses_post((string) $this->rendered['body']);
        $this->assertStringContainsString('href="https://example.org/tool?x=1"', $page);
        $open = ['nl' => 'Open de tool', 'en' => 'Open the tool', 'fr' => 'Ouvrir l’outil'];
        $lang = $grant->fields['lang'] ?? 'nl';
        $this->assertStringContainsString($open[$lang], $page, 'the button in the link\'s language');
        $this->assertStringContainsString('Campagnestart', $page);
    }

    public function test_an_unsafe_link_is_never_an_open_button(): void
    {
        $grant = $this->grantFor($this->linkTool('javascript:alert(1)'));

        $this->open($grant);

        $this->assertSame('dead', $this->rendered['kind'] ?? null);
    }

    public function test_file_kind_tool_returns_false(): void
    {
        $toolId = $this->tool(['delivery' => 'file']);
        $grant = $this->grantFor($toolId);

        $this->assertFalse(ntdst_get(ToolAppService::class)->renderForGrant(get_post($toolId), $grant));
    }

    public function test_target_is_the_tool_page(): void
    {
        $fileTool = $this->tool(['delivery' => 'file']);
        $linkTool = $this->linkTool('https://jos.agency/tool-app/campagnestart/');

        foreach ([$fileTool, $linkTool] as $id) {
            $page = home_url('/?tool=' . get_post_field('post_name', $id));
            $this->assertSame($page, ntdst_get(ToolService::class)->target($id));
            $this->assertSame($page, resolve_tool_target(get_post($id)));
        }

        $this->assertNull(resolve_tool_target(null));
    }

    public function test_gate_files_moves_existing_tool_files(): void
    {
        require_once __DIR__ . '/fixtures/wp-cli-stub.php';
        $toolId = $this->tool(['delivery' => 'file']);
        $upload = wp_upload_bits('checklist-' . uniqid() . '.pdf', null, '%PDF-1.4 checklist');
        $this->assertSame(false, $upload['error']);
        $attachment = (int) wp_insert_attachment(
            ['post_mime_type' => 'application/pdf', 'post_title' => 'checklist', 'post_status' => 'inherit'],
            $upload['file'],
            $toolId,
        );
        // Saved with no editor logged in: the file stays public, as one uploaded before the module was on.
        update_post_meta($toolId, '_jw_file', $attachment);
        $this->assertSame($upload['url'], wp_get_attachment_url($attachment));

        \WP_CLI::$lines = [];
        (new Cli(ntdst_get(PrivateFiles::class)))->gate_files([], []);

        $this->assertContains("Moved attachment {$attachment}.", \WP_CLI::$lines);
        $this->assertFileDoesNotExist($upload['file'], 'the uploads URL answers nothing now');
        $private = ntdst_get(PrivateFiles::class)->path($attachment);
        $this->assertNotNull($private);
        $this->assertStringStartsWith(realpath($this->store) . '/', $private);
        $this->assertEmpty(wp_get_attachment_url($attachment), 'a visitor gets no URL for it');
    }
}
