<?php

/**
 * MailLayoutService — every mail the site sends leaves in the JOS layout:
 * lemon background, the logo, the message on a white card.
 *
 * One `wp_mail` filter; FluentSMTP's own wp_mail() applies it too. Plain text
 * is escaped and linked, an HTML fragment is taken as markup. A complete page
 * is another plugin's own layout: FluentBooking's is unwrapped to its content,
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
    private const LOGO = 'themes/josworld/images/logo.png';

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

        if (stripos($message, '<html') !== false) {
            $content = $this->fluentBookingContent($message);
        } elseif ($this->isHtml($headers)) {
            $content = $message;
        } else {
            $content = wpautop(make_clickable(esc_html($message)));
            $headers = $this->withHtmlContentType($headers);
        }

        if ($content === null) {
            return $mail;
        }

        $html = ntdst_response()->html('mail/layout', [
            'content' => $content,
            'subject' => (string) ($mail['subject'] ?? ''),
            'logo'    => content_url(self::LOGO),
        ]);

        // No template resolved: the mail still goes out, unstyled.
        if ($html === '') {
            return $mail;
        }

        $mail['message'] = $html;
        $mail['headers'] = $headers;

        return $mail;
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
     * FluentBooking has no setting to send its message bare; its page keeps
     * the message in `td.content > div.success`.
     */
    private function fluentBookingContent(string $page): ?string
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $page);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        $found = (new \DOMXPath($dom))->query('//td[contains(@class, "content")]/div[contains(@class, "success")]');
        $message = $found ? $found->item(0) : null;

        if (!$message) {
            return null;
        }

        $content = '';
        foreach ($message->childNodes as $child) {
            $content .= $dom->saveHTML($child);
        }

        return $content;
    }
}
