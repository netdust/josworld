<?php

/**
 * CrmContactLanguageService — tags every FluentCRM contact created from a
 * FluentForms submission or a FluentBooking booking with the language the
 * visitor was browsing in.
 *
 * The forms and the booking calendar are single Dutch entities rendered on
 * nl/en/fr pages, so a submission carries no language of its own; without this, three languages
 * collapse into one undifferentiated contact pool and every campaign has to
 * be hand-segmented. Polylang runs `force_lang: 1` (directory prefix) with
 * `hide_default: true`, so the URL the form was posted from IS the signal:
 * a leading `/fr/` or `/en/` segment, absent for the default `nl`.
 *
 * ABSENCE CONTRACT — both FluentCRM and Polylang are git-untracked
 * (`.gitignore:153` ignores `app/content/plugins/*`), so "neither is
 * installed" is the DEFAULT state of a fresh clone or deploy, exactly as
 * MultilingualService documents for Polylang. Every call out of this class
 * is guarded; the site must load with no fatal when both are gone.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\forms;

defined('ABSPATH') || exit;

final class CrmContactLanguageService
{
    private const TAG_SLUG_PREFIX = 'lang-';

    private const DEFAULT_LANGUAGE = 'nl';

    public function __construct()
    {
        $this->init();
    }

    private function init(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        // Both hooks, not just the first: a returning visitor who downloads a
        // second tool updates an existing contact, and a contact that moved
        // language is the case worth catching.
        add_action('fluent_crm/contact_added_by_fluentform', [$this, 'tagSubmissionLanguage'], 10, 2);
        add_action('fluent_crm/contact_updated_by_fluentform', [$this, 'tagSubmissionLanguage'], 10, 2);

        // A booking stores the same `source_url`; FluentBooking fires this one action
        // for both a new and an existing contact.
        add_action('fluent_crm/contact_added_by_fluent_booking', [$this, 'tagSubmissionLanguage'], 10, 2);
    }

    /**
     * @param mixed $subscriber FluentCrm\App\Models\Subscriber
     * @param mixed $entry      the FluentForms submission, or the FluentBooking booking
     */
    public function tagSubmissionLanguage($subscriber, $entry): void
    {
        if (! is_object($subscriber) || ! method_exists($subscriber, 'attachTags')) {
            return;
        }

        $language = $this->languageFromUrl(
            (string) ($entry->source_url ?? ''),
            $this->configuredLanguages(),
            $this->defaultLanguage(),
            $this->sitePath(),
        );

        $tagId = $this->tagIdFor($language);

        if ($tagId > 0) {
            $subscriber->attachTags([$tagId]);
        }
    }

    /**
     * Pure: the whole language decision, so it is provable without
     * WordPress, Polylang or FluentCRM loaded.
     *
     * `$basePath` is the site's own path prefix — `/wp/` here, because this is
     * a `custom-app` structure and FluentForms stores `source_url` as
     * `site_url($referer)`. Strip it or the language segment is never first.
     *
     * @param list<string> $languages
     */
    public function languageFromUrl(string $url, array $languages, string $default, string $basePath = ''): string
    {
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $base = trim($basePath, '/');

        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base) + 1);
        }

        $firstSegment = strtok($path, '/');

        // A segment only counts when Polylang actually knows that language —
        // otherwise a Dutch slug like /de/ would be read as German.
        return $firstSegment !== false && in_array($firstSegment, $languages, true)
            ? $firstSegment
            : $default;
    }

    public function tagSlugFor(string $language): string
    {
        return self::TAG_SLUG_PREFIX . $language;
    }

    /**
     * @return list<string>
     */
    private function configuredLanguages(): array
    {
        if (! function_exists('pll_languages_list')) {
            return [self::DEFAULT_LANGUAGE];
        }

        return array_values(array_filter(array_map('strval', (array) pll_languages_list())));
    }

    /**
     * `site_url`, not `home_url`: it is the function FluentForms itself used
     * to build the value being parsed back.
     */
    private function sitePath(): string
    {
        if (! function_exists('site_url')) {
            return '';
        }

        return (string) parse_url(site_url('/'), PHP_URL_PATH);
    }

    private function defaultLanguage(): string
    {
        if (! function_exists('pll_default_language')) {
            return self::DEFAULT_LANGUAGE;
        }

        $default = pll_default_language();

        return is_string($default) && $default !== '' ? $default : self::DEFAULT_LANGUAGE;
    }

    /**
     * `importBulk` is FluentCRM's own idempotent updateOrCreate, so the tag
     * is created on a database that never ran the seed and reused everywhere
     * after. Returns 0 when FluentCRM is absent.
     */
    private function tagIdFor(string $language): int
    {
        if (! function_exists('FluentCrmApi') || ! class_exists(\FluentCrm\App\Models\Tag::class)) {
            return 0;
        }

        $slug = $this->tagSlugFor($language);

        FluentCrmApi('tags')->importBulk([
            ['title' => 'Taal: ' . strtoupper($language), 'slug' => $slug],
        ]);

        $tag = \FluentCrm\App\Models\Tag::where('slug', $slug)->first();

        return is_object($tag) && isset($tag->id) ? (int) $tag->id : 0;
    }
}
