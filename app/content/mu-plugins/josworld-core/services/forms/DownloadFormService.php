<?php

/**
 * DownloadFormService — resolves the tool download behind the FluentForm
 * "Download tool" confirmation.
 *
 * The form is shared by every tool single; a hidden `tool_id` field carries
 * the embedding post. After a valid submission the confirmation message
 * template holds `{tool_file_url}` and `{tool_title}`, which this service
 * fills from the tool's `file` field. The submission itself (validation,
 * storage, notification) stays FluentForm's.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\forms;

use josworld\services\content\ToolService;

defined('ABSPATH') || exit;

final class DownloadFormService
{
    public const TOOL_ID_FIELD = 'tool_id';
    public const FILE_URL_TOKEN = '{tool_file_url}';
    public const TITLE_TOKEN = '{tool_title}';
    public const ACTION_TOKEN = '{tool_action}';

    public function __construct(private readonly ToolService $tools)
    {
        $this->init();
    }

    private function init(): void
    {
        add_filter('fluentform/submission_confirmation', [$this, 'injectToolDownload'], 10, 5);
    }

    /**
     * Only a confirmation that names the token AND a submission that carries
     * the hidden field is ours; anything else passes through untouched.
     *
     * @param array<string, mixed> $returnData
     * @param mixed                $form
     * @param mixed                $confirmation
     * @param mixed                $insertId
     * @param array<string, mixed> $formData
     * @return array<string, mixed>
     */
    public function injectToolDownload(array $returnData, $form, $confirmation, $insertId, array $formData): array
    {
        $message = (string) ($returnData['message'] ?? '');

        if (! array_key_exists(self::TOOL_ID_FIELD, $formData) || ! str_contains($message, self::FILE_URL_TOKEN)) {
            return $returnData;
        }

        // The hidden field is user-controlled: an integer, a published tool, or nothing.
        $toolId = absint($formData[self::TOOL_ID_FIELD]);
        $tool = $toolId > 0 ? get_post($toolId) : null;

        if (! $tool instanceof \WP_Post || $tool->post_type !== 'tool' || $tool->post_status !== 'publish') {
            return $this->withoutDownload($returnData);
        }

        $target = $this->tools->target($toolId);

        if ($target['url'] === '') {
            return $this->withoutDownload($returnData);
        }

        // A link is opened, not saved: the stored message's `download` attribute would save a same-site page as a file.
        if ($target['isLink']) {
            $message = (string) preg_replace('/(<a\b[^>]*?)\sdownload(?:="[^"]*")?(?=[\s>])/i', '$1', $message);
        }
        $returnData['message'] = str_replace(
            [self::FILE_URL_TOKEN, self::TITLE_TOKEN, self::ACTION_TOKEN],
            [esc_url($target['url']), esc_html(get_the_title($tool)), $target['isLink'] ? 'openen' : 'downloaden'],
            $message,
        );

        return $returnData;
    }

    /**
     * @param array<string, mixed> $returnData
     * @return array<string, mixed>
     */
    private function withoutDownload(array $returnData): array
    {
        $returnData['message'] = esc_html__(
            'Bedankt! We hebben je gegevens ontvangen. Dit bestand is nog niet beschikbaar; '
            . 'we sturen het je zo snel mogelijk toe.',
            JOSWORLD_CORE_TEXTDOMAIN,
        );

        return $returnData;
    }
}
