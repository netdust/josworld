<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use WP_UnitTestCase;

/**
 * Every mail the site sends leaves in the JOS layout (lemon background, logo,
 * white card): driven through the real wp_mail() and read back from
 * WordPress's test mailer.
 *
 * A test file by nature declares a symbol and executes side effects.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class MailLayoutTest extends WP_UnitTestCase
{
    private const LEMON = '#fff794';

    private const LOGO = '/themes/josworld/images/logo.png';

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
        preg_match('/<a href="(https:\/\/jos\.agency[^"]+)"/', $sent->body, $link);
        $this->assertSame($resetUrl, html_entity_decode($link[1] ?? ''), 'a bare URL becomes a link');
    }

    public function test_an_html_fragment_is_wrapped_as_markup(): void
    {
        wp_mail('jos@jos.agency', 'Stageaanvraag', '<p><strong>Naam</strong>: Sarah</p>', self::HTML);

        $sent = $this->sent();

        $this->assertStringContainsString('<p><strong>Naam</strong>: Sarah</p>', $sent->body);
        $this->assertStringContainsString(self::LEMON, $sent->body);
        $this->assertStringContainsString(self::LOGO, $sent->body);
    }

    public function test_a_fluentbooking_page_is_rewrapped_around_its_content(): void
    {
        $message = '<p>Je afspraak met Jos staat vast — <strong>do 24 oktober</strong>.</p>';
        $page = '<!DOCTYPE html><html><head><title></title></head><body style="background-color: #f1f1f1;">'
            . '<table class="body" style="background-color: #f1f1f1;"><tr><td class="body-inner">'
            . '<table class="container"><tr><td class="content" style="border-top: 4px solid #0069ff;">'
            . '<div class="success" style="text-align: left;">' . $message . '</div>'
            . '</td></tr></table></td></tr>'
            . '<tr><td><p>Powered by FluentBooking</p></td></tr></table></body></html>';

        wp_mail('sarah@example.org', 'Afspraak bevestigd', $page, self::HTML);

        $body = $this->sent()->body;

        $this->assertStringContainsString($message, $body);
        $this->assertStringContainsString(self::LEMON, $body);
        $this->assertStringContainsString(self::LOGO, $body);
        $this->assertStringNotContainsString('#0069ff', $body, "FluentBooking's own page is gone");
        $this->assertStringNotContainsString('Powered by FluentBooking', $body);
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
}
