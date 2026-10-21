<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * Every mail the site sends leaves in the JOS layout (lemon background, logo,
 * white card): driven through the real wp_mail() and read back from
 * WordPress's test mailer. The FluentBooking page is a real one, rendered
 * and emogrified by the plugin and kept under fixtures/.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class MailLayoutTest extends WP_UnitTestCase
{
    private const LEMON = '#fff794';

    private const LOGO = '/images/logo.png';

    private const HTML = ['Content-Type: text/html; charset=UTF-8'];

    public function set_up(): void
    {
        parent::set_up();
        reset_phpmailer_instance();
    }

    public function test_a_plain_text_mail_is_sent_as_html_in_the_jos_layout(): void
    {
        $resetUrl = 'https://jos.agency/wp-login.php?action=rp&key=abc';
        wp_mail('sarah@example.org', 'Nieuw wachtwoord', "Hallo <Sarah>,\n\nKies een wachtwoord: {$resetUrl}");

        $sent = $this->sent();

        $this->assertStringContainsString('Content-Type: text/html', $sent->header);
        $this->assertStringContainsString(self::LEMON, $sent->body);
        $this->assertStringContainsString(self::LOGO, $sent->body);
        $this->assertStringContainsString('Hallo &lt;Sarah&gt;', $sent->body, 'plain text is escaped, never markup');
        $this->assertSame($resetUrl, $this->firstLink($sent->body, 'https://jos.agency'), 'a bare URL becomes a link');
    }

    public function test_the_real_password_reset_mail_keeps_a_working_reset_link(): void
    {
        $login = 'sarah';
        self::factory()->user->create(['user_login' => $login, 'user_email' => 'sarah@example.org']);

        retrieve_password($login);

        $link = $this->firstLink($this->sent()->body, network_site_url('wp-login.php'));
        parse_str((string) wp_parse_url($link, PHP_URL_QUERY), $query);
        $this->assertSame('rp', $query['action'] ?? null);
        $this->assertSame($login, $query['login'] ?? null);
        $this->assertNotEmpty($query['key'] ?? '');
    }

    public function test_sender_reply_to_and_bcc_survive_the_switch_to_html(): void
    {
        $headers = "From: Jos <jos@jos.agency>\nReply-To: sarah@example.org\nBcc: goele@jos.agency\n"
            . 'Content-Type: text/plain; charset=UTF-8';

        wp_mail('jos@jos.agency', 'Vraag', 'Hallo Jos', $headers);

        $sent = $this->sent();
        $this->assertStringContainsString('From: Jos <jos@jos.agency>', $sent->header);
        $this->assertStringContainsString('Reply-To: sarah@example.org', $sent->header);
        $this->assertStringContainsString('Content-Type: text/html', $sent->header);
        $this->assertSame('goele@jos.agency', $sent->bcc[0][0] ?? null);
        $this->assertStringContainsString(self::LEMON, $sent->body);
    }

    public function test_an_html_fragment_is_wrapped_as_markup(): void
    {
        wp_mail('jos@jos.agency', 'Stageaanvraag', '<p><strong>Naam</strong>: Sarah</p>', self::HTML);

        $sent = $this->sent();

        $this->assertStringContainsString('<p><strong>Naam</strong>: Sarah</p>', $sent->body);
        $this->assertStringContainsString(self::LEMON, $sent->body);
        $this->assertStringContainsString(self::LOGO, $sent->body);
    }

    public function test_html_declared_through_the_content_type_filter_is_wrapped_as_markup(): void
    {
        $html = static fn(): string => 'text/html';
        add_filter('wp_mail_content_type', $html);

        wp_mail('jos@jos.agency', 'Voucher', '<p><strong>Code</strong>: JOS-2026</p>');

        remove_filter('wp_mail_content_type', $html);
        $this->assertStringContainsString('<p><strong>Code</strong>: JOS-2026</p>', $this->sent()->body);
    }

    public function test_fluentforms_is_asked_for_its_message_instead_of_its_page(): void
    {
        $this->assertTrue(apply_filters('fluentform/send_plain_html_email', false, [], []));
    }

    public function test_a_fluentbooking_page_is_rewrapped_around_its_message_in_jos_colours(): void
    {
        wp_mail('sarah@example.org', 'Afspraak bevestigd', $this->fixture('fluentbooking-page.html'), self::HTML);

        $body = $this->sent()->body;

        $this->assertStringContainsString('Je afspraak met Jos staat vast.', $body);
        $this->assertStringContainsString('<a href="https://jos.agency/afspraak">Opnieuw inplannen</a>', $body);
        $this->assertStringContainsString(self::LEMON, $body);
        $this->assertStringNotContainsString('#0069ff', $body, "FluentBooking's own page is gone");
        $this->assertStringNotContainsString('Lakensestraat 99', $body, "FluentBooking's footer goes with it");
        $this->assertStringNotContainsString('color: #444', $body, 'the message reads in brand brown, not grey');
    }

    public function test_a_plain_text_mail_quoting_a_booking_page_stays_escaped(): void
    {
        $quoted = 'Nieuwe reactie: ' . $this->fixture('fluentbooking-page.html')
            . "\n\nGoedkeuren: https://jos.agency/wp-admin";

        wp_mail('jos@jos.agency', 'Reactie wacht op moderatie', $quoted);

        $body = $this->sent()->body;
        $this->assertStringContainsString('Nieuwe reactie: &lt;', $body);
        $this->assertSame('https://jos.agency/wp-admin', $this->firstLink($body, 'https://jos.agency/wp-admin'));
    }

    public function test_a_page_with_fluentbookings_skeleton_from_another_plugin_is_sent_untouched(): void
    {
        $page = $this->fixture('fluentcrm-page.html');

        wp_mail('sarah@example.org', 'Uitschrijven bevestigen', $page, self::HTML);

        $this->assertSame($this->lines($page), $this->lines($this->sent()->body));
    }

    public function test_a_complete_page_it_does_not_know_is_sent_untouched(): void
    {
        $newsletter = '<!DOCTYPE html><html><body><table><tr><td>Nieuwsbrief — '
            . '<a href="https://jos.agency/unsubscribe">Uitschrijven</a></td></tr></table></body></html>';

        wp_mail('sarah@example.org', 'Nieuwsbrief', $newsletter, self::HTML);

        $this->assertSame($newsletter, trim($this->sent()->body));
    }

    private function sent(): object
    {
        $sent = tests_retrieve_phpmailer_instance()->get_sent(0);
        $this->assertNotFalse($sent, 'wp_mail() sent nothing');

        return $sent;
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/' . $name);
    }

    /** PHPMailer sends CRLF line endings; the page is otherwise byte-for-byte what came in. */
    private function lines(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }

    private function firstLink(string $body, string $prefix): string
    {
        $found = preg_match('/<a href="(' . preg_quote($prefix, '/') . '[^"]*)"/', $body, $link);
        $this->assertSame(1, $found, "no link starting with {$prefix}");

        return html_entity_decode($link[1]);
    }
}
