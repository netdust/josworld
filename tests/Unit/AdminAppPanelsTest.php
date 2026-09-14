<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Functions;
use josworld\services\admin\AdminAppService;
use josworld\services\admin\ActivitySource;

/**
 * The two activity panels — the mitigation for threat-model T1.
 *
 * A booker and a form submitter both choose their own name and mail address,
 * and those strings render inside an authenticated admin page. If they reach
 * the page unescaped, a visitor can run script in an editor's session with
 * that editor's privileges. The payload tests below are that mitigation; the
 * empty-state tests exist because locally there are 0 submissions, so one
 * panel is empty while the other is not on every single load.
 */
final class AdminAppPanelsTest extends TestCase
{
    private const PAYLOAD = '<script>alert(1)</script>';

    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        $dir = dirname(__DIR__, 2) . '/app/content/mu-plugins/josworld-core/services/admin/';
        require_once $dir . 'ActivitySource.php';
        require_once $dir . 'BookingRepository.php';
        require_once $dir . 'SubmissionRepository.php';
        require_once $dir . 'AdminAppService.php';

        Functions\when('add_filter')->justReturn(true);
        Functions\when('__')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('_n')->alias(
            static fn(string $single, string $plural, int $n): string => $n === 1 ? $single : $plural,
        );
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('admin_url')->alias(static fn(string $p): string => '/wp/wp-admin/' . $p);
        Functions\when('wp_count_posts')->justReturn((object) ['publish' => 0, 'draft' => 0]);
        Functions\when('get_post_type_object')->justReturn(
            (object) ['cap' => (object) ['create_posts' => 'edit_posts']],
        );
        // The real escaper, not a passthrough: a returnArg() stub here would
        // make every assertion below pass against an unescaped renderer.
        Functions\when('esc_html')->alias(
            static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'),
        );
    }

    /**
     * @param list<array<string,mixed>> $bookings
     * @param list<array<string,mixed>> $submissions
     */
    private function render(array $bookings, array $submissions): string
    {
        $bookingRepo = new class ($bookings) implements ActivitySource {
            /** @param list<array<string,mixed>> $rows */
            public function __construct(private array $rows)
            {
            }

            public function latest(): array
            {
                return $this->rows;
            }
        };

        $submissionRepo = new class ($submissions) implements ActivitySource {
            /** @param list<array<string,mixed>> $rows */
            public function __construct(private array $rows)
            {
            }

            public function latest(): array
            {
                return $this->rows;
            }
        };

        $service = new AdminAppService();
        $service->useSources($bookingRepo, $submissionRepo);

        ob_start();
        $service->renderDashboard();

        return (string) ob_get_clean();
    }

    public function test_a_script_payload_in_a_booker_name_renders_as_text(): void
    {
        $html = $this->render([[
            'who' => self::PAYLOAD, 'event' => 'Kennismaking',
            'when' => '2026-09-17 10:00:00', 'url' => '/wp/wp-admin/x',
        ]], []);

        $this->assertStringNotContainsString('<script>', $html, 'a booker name must never reach the page as markup');
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_script_payload_in_a_submitter_name_renders_as_text(): void
    {
        $html = $this->render([], [[
            'form' => 'Contact Form Demo', 'who' => self::PAYLOAD,
            'when' => '2026-09-13 10:00:00', 'url' => '/wp/wp-admin/x',
        ]]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_payload_in_a_form_title_renders_as_text(): void
    {
        $html = $this->render([], [[
            'form' => self::PAYLOAD, 'who' => 'jan@example.be',
            'when' => '2026-09-13 10:00:00', 'url' => '/wp/wp-admin/x',
        ]]);

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_both_panels_render_side_by_side(): void
    {
        $html = $this->render([], []);

        $this->assertStringContainsString('ntdst-admin__split', $html);
        $this->assertSame(2, substr_count($html, 'ntdst-admin__panel"'), 'two panels, one row');
    }

    public function test_each_panel_renders_its_empty_state_independently(): void
    {
        $html = $this->render([[
            'who' => 'Marie Peeters', 'event' => 'Kennismaking',
            'when' => '2026-09-17 10:00:00', 'url' => '/wp/wp-admin/x',
        ]], []);

        // Bookings has a row, submissions has none: exactly one empty state.
        $this->assertSame(
            1,
            substr_count($html, 'ntdst-admin__empty"'),
            'an empty panel must not blank the panel beside it',
        );
        $this->assertStringContainsString('Marie Peeters', $html);
    }

    public function test_both_empty_renders_two_empty_states(): void
    {
        $this->assertSame(2, substr_count($this->render([], []), 'ntdst-admin__empty"'));
    }
}
