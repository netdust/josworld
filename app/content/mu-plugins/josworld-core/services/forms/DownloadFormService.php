<?php

/**
 * DownloadFormService — the FluentForm "Download tool" confirmation sends the
 * visitor to their personal link.
 *
 * The form is shared by every tool single; a hidden `tool_id` field carries
 * the embedding post. After a valid submission baseline's grants module issues
 * the grant for the typed address (and mails the link); the browser follows
 * FluentForm's own `redirectUrl` to it. The submission itself (validation,
 * storage, notification) stays FluentForm's.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\forms;

use ntdst\baseline\services\grants\GrantService;

defined('ABSPATH') || exit;

final class DownloadFormService
{
    public const TOOL_ID_FIELD = 'tool_id';

    public const EMAIL_FIELD = 'email';

    /** @var \Closure(int): string the stored submission's status; FluentForm flags a caught one `spam` */
    private readonly \Closure $entryStatus;

    public function __construct(private readonly GrantService $grants, ?callable $entryStatus = null)
    {
        $this->entryStatus = \Closure::fromCallable($entryStatus ?? [self::class, 'submissionStatus']);
        $this->init();
    }

    private function init(): void
    {
        add_filter('fluentform/submission_confirmation', [$this, 'injectToolDownload'], 10, 5);
    }

    /**
     * Only a submission that carries the hidden field is ours; anything else passes through untouched.
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
        if (! array_key_exists(self::TOOL_ID_FIELD, $formData)) {
            return $returnData;
        }

        // FluentForm still runs this filter for an entry it caught as spam: that address gets nothing.
        if (($this->entryStatus)(absint($insertId)) === 'spam') {
            return $returnData;
        }

        // issue() refuses anything but a published tool and an address exactly as typed (it sanitizes and compares).
        $email = is_string($formData[self::EMAIL_FIELD] ?? null) ? $formData[self::EMAIL_FIELD] : '';
        $grant = $this->grants->issue($email, absint($formData[self::TOOL_ID_FIELD]));

        if ($grant['status'] === 'refused' || $grant['url'] === '') {
            return $this->withoutDownload($returnData);
        }

        $returnData['redirectUrl'] = $grant['url'];
        $returnData['message'] = '';

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

    /** '' when FluentForm is not installed. */
    private static function submissionStatus(int $insertId): string
    {
        if ($insertId <= 0 || ! class_exists(\FluentForm\App\Models\Submission::class)) {
            return '';
        }

        $entry = \FluentForm\App\Models\Submission::find($insertId);

        return is_object($entry) ? (string) ($entry->status ?? '') : '';
    }
}
