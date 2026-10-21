<?php

/**
 * MailLayoutService — every mail the site sends leaves in the JOS layout:
 * lemon background, the logo, the message on a white card.
 *
 * One `wp_mail` filter; FluentSMTP's own wp_mail() applies it too. Plain text
 * is escaped and linked, an HTML fragment is taken as markup. A complete page
 * is another plugin's own layout: FluentBooking's is unwrapped to its message,
 * any other (a FluentCRM newsletter, unsubscribe footer and all) leaves as it
 * came. FluentForms is asked for its fragment instead of its page.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\site;

defined('ABSPATH') || exit;

final class MailLayoutService
{
    /**
     * FluentBooking's page keeps the message here. FluentCRM and FluentSMTP
     * share the skeleton but tag body-inner with their own name, so the exact
     * class match leaves their pages alone.
     */
    private const FLUENTBOOKING_MESSAGE = '//td[@class="body-inner"]//td[@class="content"]/div[@class="success"]';

    /** FluentBooking's base text colour and font, inlined by its Emogrifier onto every paragraph. */
    private const FLUENTBOOKING_TEXT_STYLE = '/(color:\s*#444\b'
        . '|font-family:\s*["\']?Helvetica Neue["\']?,\s*Helvetica,\s*Arial,\s*sans-serif)\s*;?\s*/i';

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        \NTDST_Template_Loader::addPath(dirname(__DIR__, 2) . '/templates');

        add_filter('wp_mail', [$this, 'wrap'], 99);
        add_filter('fluentform/send_plain_html_email', '__return_true');
    }

    /**
     * @param array<string, mixed> $mail wp_mail()'s arguments, compacted
     * @return array<string, mixed>
     */
    public function wrap(array $mail): array
    {
        $message = (string) ($mail['message'] ?? '');
        $headers = $this->headerLines($mail['headers'] ?? []);

        if (!$this->isHtml($headers)) {
            $content = wpautop(make_clickable(esc_html($message)));
            $headers = $this->withHtmlContentType($headers);
        } elseif (stripos($message, '<html') !== false) {
            $content = $this->fluentBookingMessage($message);
        } else {
            $content = $message;
        }

        if ($content === null) {
            return $mail;
        }

        $html = $this->render($content, (string) ($mail['subject'] ?? ''));

        // No layout rendered: the mail still goes out, unstyled.
        if ($html === '') {
            return $mail;
        }

        $mail['message'] = $html;
        $mail['headers'] = $headers;

        return $mail;
    }

    private function render(string $content, string $subject): string
    {
        try {
            return ntdst_response()->html('mail/layout', [
                'content' => $content,
                'subject' => $subject,
                // The site's own scheme: a theme URL follows the request, and WP-CLI or a system cron is http.
                'logo'    => set_url_scheme(
                    get_stylesheet_directory_uri() . '/images/logo.png',
                    wp_parse_url(home_url(), PHP_URL_SCHEME)
                ),
            ]);
        } catch (\Throwable $e) {
            ntdst_log('mail')->error('mail layout failed; sent unstyled', ['error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * @param string|list<string> $headers
     * @return list<string>
     */
    private function headerLines($headers): array
    {
        $lines = is_array($headers) ? $headers : explode("\n", str_replace("\r\n", "\n", (string) $headers));

        return array_values(array_filter(array_map('trim', $lines)));
    }

    /**
     * @param list<string> $headers
     */
    private function isHtml(array $headers): bool
    {
        foreach ($headers as $header) {
            if (preg_match('/^content-type:\s*text\/html/i', $header)) {
                return true;
            }
        }

        // Some plugins switch to HTML through the filter instead of a header.
        return apply_filters('wp_mail_content_type', 'text/plain') === 'text/html';
    }

    /**
     * @param list<string> $headers
     * @return list<string>
     */
    private function withHtmlContentType(array $headers): array
    {
        $headers = array_filter($headers, static fn(string $header): bool => stripos($header, 'content-type:') !== 0);
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        return array_values($headers);
    }

    /**
     * FluentBooking has no setting to send its message bare, so it is lifted
     * out of the page, minus the grey text the page imposed on it.
     */
    private function fluentBookingMessage(string $page): ?string
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $found = $loaded ? (new \DOMXPath($dom))->query(self::FLUENTBOOKING_MESSAGE) : false;
        $message = $found ? $found->item(0) : null;

        if (!$message instanceof \DOMElement) {
            return null;
        }

        foreach ((new \DOMXPath($dom))->query('.//*[@style]', $message) ?: [] as $element) {
            if ($element instanceof \DOMElement) {
                $style = preg_replace(self::FLUENTBOOKING_TEXT_STYLE, '', $element->getAttribute('style'));
                $element->setAttribute('style', (string) $style);
            }
        }

        $content = '';
        foreach ($message->childNodes as $child) {
            $content .= $dom->saveHTML($child);
        }

        return $content;
    }
}
