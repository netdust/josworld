<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\forms\DownloadFormService;
use josworld\services\site\ToolGrantsMatomo;
use ntdst\baseline\services\grants\GrantModel;
use ntdst\baseline\services\grants\GrantRepository;
use ntdst\baseline\services\grants\GrantService;
use ntdst\baseline\services\grants\LinkRoute;
use ntdst\baseline\services\grants\PrivateFiles;
use WP_UnitTestCase;

/**
 * The download form (FluentForm 3) through its confirmation filter: a valid submission for a
 * published tool issues the visitor's grant, mails the link and sends the browser to it. Every
 * other submission issues nothing. On the link itself the Matomo tracker stays off the page.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class ToolGrantFormTest extends WP_UnitTestCase
{
    private const EMAIL = 'visitor@example.test';

    private const LINK = '#^https?://[^/]+/toegang/[0-9a-f]{64}$#';

    /** Form 3's stored confirmation: the old download message's tokens, filled in by nobody now. */
    private const STORED_MESSAGE = '<p>Bedankt! {tool_title} {tool_action}: <a href="{tool_file_url}">download</a></p>';

    private string $store = '';

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

    private function tool(string $status = 'publish', string $type = 'tool'): int
    {
        return self::factory()->post->create(
            ['post_type' => $type, 'post_status' => $status, 'post_title' => 'Contentkalender'],
        );
    }

    /**
     * @param array<string, mixed> $formData
     * @return array<string, mixed>
     */
    private function submit(array $formData): array
    {
        $returnData = ['message' => '<p><strong>Bedankt!</strong> Je tool staat klaar.</p>', 'action' => 'hide_form'];

        return apply_filters('fluentform/submission_confirmation', $returnData, null, [], 1, $formData);
    }

    /** The "nog niet beschikbaar" answer, never the stored message with its raw tokens. */
    private function assertNeutral(string $message): void
    {
        $this->assertStringContainsString('nog niet beschikbaar', $message);
        $this->assertStringNotContainsString('{tool_', $message);
    }

    /** @return list<\MockPHPMailer|object> */
    private function mails(): array
    {
        return tests_retrieve_phpmailer_instance()->mock_sent;
    }

    private function grantCount(): int
    {
        $query = ['post_type' => GrantModel::POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids'];

        return count(get_posts($query));
    }

    public function test_published_tool_redirects_to_its_link_and_mails_it(): void
    {
        $out = $this->submit(['tool_id' => (string) $this->tool(), 'email' => self::EMAIL]);

        $this->assertMatchesRegularExpression(self::LINK, (string) ($out['redirectUrl'] ?? ''));
        $this->assertSame('', $out['message']);

        $mails = $this->mails();
        $this->assertCount(1, $mails);
        $this->assertSame(self::EMAIL, $mails[0]['to'][0][0]);
        $this->assertStringContainsString($out['redirectUrl'], $mails[0]['body']);
    }

    public function test_draft_tool_id_issues_nothing(): void
    {
        $out = $this->submit(['tool_id' => (string) $this->tool('draft'), 'email' => self::EMAIL]);

        $this->assertEmpty($out['redirectUrl'] ?? '');
        $this->assertStringContainsString('nog niet beschikbaar', $out['message']);
        $this->assertSame([], $this->mails());
        $this->assertSame(0, $this->grantCount());
    }

    public function test_a_page_id_or_garbage_issues_nothing(): void
    {
        foreach ([(string) $this->tool('publish', 'page'), 'abc', '999999'] as $toolId) {
            $out = $this->submit(['tool_id' => $toolId, 'email' => self::EMAIL]);

            $this->assertEmpty($out['redirectUrl'] ?? '', $toolId);
            $this->assertStringContainsString('nog niet beschikbaar', $out['message'], $toolId);
        }

        $this->assertSame([], $this->mails());
        $this->assertSame(0, $this->grantCount());
    }

    public function test_a_form_without_the_hidden_field_passes_through_untouched(): void
    {
        $in = ['message' => 'Thanks', 'action' => 'hide_form'];

        $out = apply_filters('fluentform/submission_confirmation', $in, null, [], 1, ['email' => self::EMAIL]);

        $this->assertSame($in, $out);
    }

    public function test_same_address_twice_gets_the_same_link_and_one_mail(): void
    {
        $data = ['tool_id' => (string) $this->tool(), 'email' => self::EMAIL];

        $first = $this->submit($data);
        $second = $this->submit($data);

        $this->assertMatchesRegularExpression(self::LINK, (string) $first['redirectUrl']);
        $this->assertSame($first['redirectUrl'], $second['redirectUrl']);
        $this->assertCount(1, $this->mails());
    }

    public function test_revoked_address_redirects_to_the_dead_page_without_mail(): void
    {
        $toolId = $this->tool();
        $data = ['tool_id' => (string) $toolId, 'email' => self::EMAIL];
        $first = $this->submit($data);

        $repository = ntdst_get(GrantRepository::class);
        $grant = $repository->forContent($toolId)[0];
        $this->assertNotWPError($repository->revoke($grant->ID));
        reset_phpmailer_instance();

        $again = $this->submit($data);

        $this->assertSame($first['redirectUrl'], $again['redirectUrl']);
        $this->assertNull($repository->verify((string) $grant->fields['token']));
        $this->assertSame([], $this->mails());
    }

    public function test_spam_entry_issues_nothing(): void
    {
        $service = new DownloadFormService(ntdst_get(GrantService::class), static fn (int $insertId): string => 'spam');
        $returnData = ['message' => self::STORED_MESSAGE, 'action' => 'hide_form'];

        $formData = ['tool_id' => (string) $this->tool(), 'email' => self::EMAIL];

        $out = $service->injectToolDownload($returnData, null, [], 1, $formData);

        $this->assertEmpty($out['redirectUrl'] ?? '');
        $this->assertNeutral((string) $out['message']);
        $this->assertSame([], $this->mails());
        $this->assertSame(0, $this->grantCount());
    }

    /**
     * The production reader against FluentForm's model: a stored `spam` entry gets nothing, a normal
     * entry gets its link. A missing row is no spam flag, so it is issued: FluentForm stores every
     * entry, spam included, before this filter runs.
     */
    public function test_the_default_reader_reads_fluentforms_stored_status(): void
    {
        require_once __DIR__ . '/fixtures/fluentform-submission-stub.php';
        \FluentForm\App\Models\Submission::$rows = [41 => 'spam', 42 => 'unread', 43 => 'read'];
        $service = new DownloadFormService(ntdst_get(GrantService::class));
        $confirm = fn (int $entry, string $email): array => $service->injectToolDownload(
            ['message' => self::STORED_MESSAGE, 'action' => 'hide_form'],
            null,
            [],
            $entry,
            ['tool_id' => (string) $this->tool(), 'email' => $email],
        );

        try {
            $spam = $confirm(41, 'spam@example.test');
            $this->assertEmpty($spam['redirectUrl'] ?? '');
            $this->assertNeutral((string) $spam['message']);
            $this->assertSame([], $this->mails());
            $this->assertSame(0, $this->grantCount());

            $passing = [42 => 'unread@example.test', 43 => 'read@example.test', 44 => 'norow@example.test'];
            foreach ($passing as $entry => $email) {
                $link = (string) ($confirm($entry, $email)['redirectUrl'] ?? '');
                $this->assertMatchesRegularExpression(self::LINK, $link, $email);
            }
            $this->assertCount(3, $this->mails());
        } finally {
            \FluentForm\App\Models\Submission::$rows = [];
        }
    }

    public function test_matomo_callbacks_are_removed_on_a_grant_request(): void
    {
        require_once __DIR__ . '/fixtures/matomo-stubs.php';
        $this->onlyTheBootedGuardOnTheHeadAndFooter();
        $tracking = new \WpMatomo\TrackingCode();
        $bots = new \WpMatomo\AIBotTracking();
        add_action('wp_head', [$tracking, 'add_javascript_code']);
        add_action('wp_footer', [$tracking, 'add_noscript_code']);
        add_action('wp_footer', [$bots, 'do_ai_bot_tracking'], 999999);

        $issued = $this->submit(['tool_id' => (string) $this->tool(), 'email' => self::EMAIL]);
        $this->assertMatchesRegularExpression(self::LINK, (string) $issued['redirectUrl']);
        $token = (string) ntdst_get(GrantRepository::class)->forRecipient(self::EMAIL)[0]->fields['token'];

        $this->linkRoute()->serve(['token' => $token]);

        $this->assertTrue(LinkRoute::isGrantRequest());
        $this->assertSame([], $tracking->calls);
        $this->assertSame([], $bots->calls);
    }

    public function test_matomo_keeps_tracking_an_ordinary_page(): void
    {
        require_once __DIR__ . '/fixtures/matomo-stubs.php';
        $this->onlyTheBootedGuardOnTheHeadAndFooter();
        $tracking = new \WpMatomo\TrackingCode();
        add_action('wp_head', [$tracking, 'add_javascript_code']);

        ob_start();
        do_action('wp_head');
        ob_end_clean();

        $this->assertSame(['add_javascript_code'], $tracking->calls);
    }

    /** The guard as the boot wired it, alone: the rest of the head would print what earlier tests enqueued. */
    private function onlyTheBootedGuardOnTheHeadAndFooter(): void
    {
        $guard = [ntdst_get(ToolGrantsMatomo::class), 'dropTracker'];
        $this->assertSame(0, has_action('wp_head', $guard));

        remove_all_actions('wp_head');
        remove_all_actions('wp_footer');
        add_action('wp_head', $guard, 0);
    }

    /** The real route, minus what a test process cannot do: send headers, or load a theme around the page. */
    private function linkRoute(): LinkRoute
    {
        return new class (ntdst_get(GrantRepository::class), ntdst_get(PrivateFiles::class)) extends LinkRoute {
            protected function sendHeader(string $line, bool $replace): void
            {
            }

            /** @param array<string, mixed> $args */
            protected function render(array $args): void
            {
                ob_start();
                do_action('wp_head');
                do_action('wp_footer');
                ob_end_clean();
            }
        };
    }
}
