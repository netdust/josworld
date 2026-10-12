<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Actions;
use josworld\services\forms\CrmContactLanguageService;

/**
 * The language a CRM contact is tagged with is read from the URL the form
 * was submitted from, because nothing in a submission carries it: the four
 * FluentForms are single Dutch entities rendered on nl/en/fr pages.
 *
 * Polylang runs `force_lang: 1` (directory prefix) with `hide_default: true`,
 * so the first path segment IS the language for en/fr and is absent for nl.
 * That contract is what these cases pin — the resolver is pure so it can be
 * proven without WordPress, Polylang or FluentCRM present.
 */
final class CrmContactLanguageTest extends TestCase
{
    private const LANGUAGES = ['nl', 'en', 'fr'];

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__
            . '/../../app/content/mu-plugins/josworld-core/services/forms/CrmContactLanguageService.php';
    }

    private function resolve(string $url, string $basePath = ''): string
    {
        return (new CrmContactLanguageService())->languageFromUrl($url, self::LANGUAGES, 'nl', $basePath);
    }

    public function test_a_prefixed_url_resolves_to_that_language(): void
    {
        $this->assertSame('fr', $this->resolve('https://josworld.com/fr/outils/'));
        $this->assertSame('en', $this->resolve('https://josworld.com/en/tools/some-tool/'));
    }

    public function test_an_unprefixed_url_is_the_hidden_default_language(): void
    {
        $this->assertSame('nl', $this->resolve('https://josworld.com/tools/een-tool/'));
        $this->assertSame('nl', $this->resolve('https://josworld.com/'));
    }

    /**
     * The trap this pins: a Dutch slug that happens to start with a
     * two-letter segment matching no language must NOT be read as one.
     */
    public function test_a_path_segment_that_is_not_a_configured_language_falls_back(): void
    {
        $this->assertSame('nl', $this->resolve('https://josworld.com/de/tools/'));
        $this->assertSame('nl', $this->resolve('https://josworld.com/es/'));
    }

    public function test_a_query_string_or_fragment_never_reaches_the_language(): void
    {
        $this->assertSame('fr', $this->resolve('https://josworld.com/fr/?utm_source=nieuwsbrief'));
        $this->assertSame('nl', $this->resolve('https://josworld.com/?lang=fr'));
    }

    /**
     * FluentForms writes `source_url` from the request; a malformed or empty
     * value must degrade to the default rather than tag a contact wrongly.
     */
    public function test_a_missing_or_unparseable_url_is_the_default_language(): void
    {
        $this->assertSame('nl', $this->resolve(''));
        $this->assertSame('nl', $this->resolve('not a url'));
    }

    /**
     * The regression that shipped wrong once: this is a `custom-app` structure,
     * so WordPress lives at `/wp` and FluentForms writes `source_url` as
     * `site_url($referer)` — every real submission arrives as
     * `https://josworld.com/wp/fr/…`. Without stripping that base path the
     * first segment is `wp`, no language matches, and every French and English
     * contact is silently tagged Dutch.
     */
    public function test_the_site_base_path_is_stripped_before_the_language(): void
    {
        $this->assertSame('fr', $this->resolve('https://josworld.ddev.site/wp/fr/outils/', '/wp/'));
        $this->assertSame('en', $this->resolve('https://josworld.ddev.site/wp/en/tools/', '/wp/'));
        $this->assertSame('nl', $this->resolve('https://josworld.ddev.site/wp/tools/een-tool/', '/wp/'));
        $this->assertSame('nl', $this->resolve('https://josworld.ddev.site/wp/', '/wp/'));
    }

    /**
     * A URL that does not carry the base path (another writer of source_url,
     * or a root install) must still resolve rather than lose its first segment.
     */
    public function test_a_url_without_the_base_path_is_unaffected_by_stripping(): void
    {
        $this->assertSame('fr', $this->resolve('https://josworld.com/fr/outils/', '/wp/'));
        $this->assertSame('nl', $this->resolve('https://josworld.com/tools/', '/wp/'));
    }

    public function test_the_tag_slug_is_namespaced_per_language(): void
    {
        $service = new CrmContactLanguageService();

        $this->assertSame('lang-fr', $service->tagSlugFor('fr'));
        $this->assertSame('lang-nl', $service->tagSlugFor('nl'));
    }

    /**
     * FluentBooking fires its own action when it creates or updates the contact,
     * and a booking stores the same `source_url` a form entry does. Without this
     * listener a French visitor who books ends up with an untagged contact.
     */
    public function test_every_way_a_contact_is_created_is_listened_to(): void
    {
        $actions = [
            'fluent_crm/contact_added_by_fluentform',
            'fluent_crm/contact_updated_by_fluentform',
            'fluent_crm/contact_added_by_fluent_booking',
        ];

        foreach ($actions as $action) {
            Actions\expectAdded($action)->once();
        }

        new CrmContactLanguageService();
    }
}
