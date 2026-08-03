<?php

declare(strict_types=1);

namespace ntdst\baseline\support;

defined('ABSPATH') || exit;

/**
 * JSON-LD (Schema.org) structured-data builders (task-13-brief.md, T13 —
 * plan.md §SchemaService lines 588-612 "Interfaces produced (pure,
 * support/JsonLd.php)"; port source
 * /home/ntdst/Sites/ludoluykx/web/app/themes/ntdstheme/services/SchemaService.php
 * — schema SHAPES only).
 *
 * Pure static builders: every method takes plain, pre-derived arrays (no
 * WP calls) so this class unit-tests WP-free. T14 wires WordPress values
 * (bloginfo, permalinks, post data) into the arrays these builders accept.
 *
 * THE CORE CONTRACT — M8 empty-safe: the ludoluykx source shipped
 * placeholder data (a fake phone, a fake Brussels address, a fake person
 * name, dummy social URLs) as LIVE JSON-LD whenever a site set a schema
 * `type` without filling the rest — Google would index fabricated
 * business data.
 * This port emits NOTHING unless explicitly configured:
 *   - mainEntity() returns null unless type + name are both present.
 *   - Every optional sub-structure (address, geo, job title, social
 *     profiles, ...) is included ONLY when every one of its required
 *     sub-keys is present and non-empty — never a partial shape with
 *     blank fields.
 *   - No default/config value anywhere in this file looks like real data
 *     (no placeholder phone/address/name/URL literals) — enforced by a
 *     source-level grep test (tests/Unit/JsonLdTest.php).
 *
 * Dropped vs. the ludoluykx source (plan.md lines 604-609):
 *   - getDeveloperCorporation() (source :347-355) and the `creator` key
 *     it fed into WebSite schema — it injected Netdust branding into
 *     every page's structured data. Not ported.
 *   - get_option('netdust_*') reads — the config array passed in is the
 *     single source of truth; no split-brain with wp_options.
 *   - The slug-list page-type detection (source :224-231) — replaced by
 *     T14's `pages => ['contact' => <id>, 'about' => <id>, 'faq' => <id>]`
 *     config mapping, which this class does not need to know about (the
 *     caller decides WHICH builder to call).
 *
 * A file that both declares a symbol and executes a side effect (the
 * ABSPATH guard) is exempt from the PSR-1 side-effects rule, the same
 * posture josworld-core's own service files take.
 * phpcs:disable PSR1.Files.SideEffects
 */
final class JsonLd
{
    private const SCHEMA_CONTEXT = 'https://schema.org';

    /**
     * Main entity (Organization / LocalBusiness / Person) — THE M8
     * denial path. Returns null unless `type` is a known value AND
     * `name` is non-empty; auto-fill of real site values (name, url,
     * description, logo) is the caller's job, auto-fill of contact/
     * address data is never done here.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>|null
     */
    public static function mainEntity(array $config): ?array
    {
        $type = $config['type'] ?? null;
        $name = trim((string) ($config['name'] ?? ''));

        if ($name === '' || !is_string($type)) {
            return null;
        }

        return match ($type) {
            'organization' => self::organizationEntity($config, $name),
            'localbusiness' => self::localBusinessEntity($config, $name),
            'person' => self::personEntity($config, $name),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function organizationEntity(array $config, string $name): array
    {
        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'Organization',
            'name' => $name,
        ];

        self::addIfNonEmpty($schema, 'url', $config['url'] ?? null);
        self::addIfNonEmpty($schema, 'logo', $config['logo'] ?? null);
        self::addIfNonEmpty($schema, 'description', $config['description'] ?? null);
        self::addSameAs($schema, $config['social_profiles'] ?? []);

        return $schema;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function localBusinessEntity(array $config, string $name): array
    {
        $business = (array) ($config['business'] ?? []);
        $businessType = trim((string) ($business['type'] ?? ''));

        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => $businessType !== '' ? $businessType : 'LocalBusiness',
            'name' => $name,
        ];

        self::addIfNonEmpty($schema, 'url', $config['url'] ?? null);
        self::addIfNonEmpty($schema, 'logo', $config['logo'] ?? null);
        self::addIfNonEmpty($schema, 'description', $config['description'] ?? null);
        self::addSameAs($schema, $config['social_profiles'] ?? []);
        self::addIfNonEmpty($schema, 'telephone', $business['telephone'] ?? null);
        self::addIfNonEmpty($schema, 'priceRange', $business['price_range'] ?? null);
        self::addIfNonEmpty($schema, 'email', $business['email'] ?? null);

        $address = self::buildBusinessAddress((array) ($business['address'] ?? []));
        if ($address !== null) {
            $schema['address'] = $address;
        }

        $geo = self::buildGeo((array) ($business['geo'] ?? []));
        if ($geo !== null) {
            $schema['geo'] = $geo;
        }

        $hours = array_values(array_filter(
            (array) ($business['opening_hours'] ?? []),
            static fn($v): bool => is_string($v) && trim($v) !== '',
        ));
        if ($hours !== []) {
            $schema['openingHours'] = $hours;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function personEntity(array $config, string $name): array
    {
        $person = (array) ($config['person'] ?? []);

        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'Person',
            'name' => $name,
        ];

        self::addIfNonEmpty($schema, 'givenName', $person['given_name'] ?? null);
        self::addIfNonEmpty($schema, 'familyName', $person['family_name'] ?? null);
        self::addIfNonEmpty($schema, 'url', $config['url'] ?? null);
        self::addIfNonEmpty($schema, 'image', $config['logo'] ?? null);
        self::addIfNonEmpty($schema, 'description', $config['description'] ?? null);
        self::addSameAs($schema, $config['social_profiles'] ?? []);
        self::addIfNonEmpty($schema, 'jobTitle', $person['job_title'] ?? null);
        self::addIfNonEmpty($schema, 'telephone', $person['telephone'] ?? null);
        self::addIfNonEmpty($schema, 'email', $person['email'] ?? null);
        self::addIfNonEmpty($schema, 'birthDate', $person['birthdate'] ?? null);
        self::addIfNonEmpty($schema, 'nationality', $person['nationality'] ?? null);

        $address = (array) ($person['address'] ?? []);
        $city = trim((string) ($address['city'] ?? ''));
        if ($city !== '') {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $city,
                'addressCountry' => (string) ($address['country'] ?? ''),
            ];
        }

        $knowsAbout = array_values(array_filter(
            (array) ($person['knows_about'] ?? []),
            static fn($v): bool => is_string($v) && trim($v) !== '',
        ));
        if ($knowsAbout !== []) {
            $schema['knowsAbout'] = $knowsAbout;
        }

        $awards = array_values(array_filter(
            (array) ($person['awards'] ?? []),
            static fn($v): bool => is_string($v) && trim($v) !== '',
        ));
        if ($awards !== []) {
            $schema['award'] = $awards;
        }

        $alumniOf = trim((string) ($person['alumni_of'] ?? ''));
        if ($alumniOf !== '') {
            $schema['alumniOf'] = [
                '@type' => 'EducationalOrganization',
                'name' => $alumniOf,
            ];
        }

        return $schema;
    }

    /**
     * WebSite schema. Dropped vs. source: no `creator` key, no
     * getDeveloperCorporation() Corporation block — Netdust branding is
     * never injected into a site's structured data.
     *
     * @param array<string, mixed> $config
     * @param array<string, mixed>|null $entity mainEntity() output, used
     *        for `author`/`copyrightHolder` when present.
     * @return array<string, mixed>
     */
    public static function website(array $config, ?array $entity): array
    {
        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'WebSite',
        ];

        self::addIfNonEmpty($schema, 'url', $config['url'] ?? null);
        self::addIfNonEmpty($schema, 'name', $config['name'] ?? null);
        self::addIfNonEmpty($schema, 'description', $config['description'] ?? null);
        self::addIfNonEmpty($schema, 'image', $config['logo'] ?? null);
        self::addSameAs($schema, $config['social_profiles'] ?? []);

        if ($entity !== null) {
            $schema['author'] = $entity;
            $schema['copyrightHolder'] = $entity;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $ctx pre-derived: title, url,
     *        date_published, date_modified, description?, image?,
     *        author_name?, author_url?, publisher?
     * @return array<string, mixed>|null
     */
    public static function article(array $ctx): ?array
    {
        $title = trim((string) ($ctx['title'] ?? ''));
        $url = trim((string) ($ctx['url'] ?? ''));

        if ($title === '' || $url === '') {
            return null;
        }

        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'Article',
            'headline' => $title,
            'url' => $url,
            'mainEntityOfPage' => $url,
        ];

        self::addIfNonEmpty($schema, 'description', $ctx['description'] ?? null);
        self::addIfNonEmpty($schema, 'image', $ctx['image'] ?? null);
        self::addIfNonEmpty($schema, 'datePublished', $ctx['date_published'] ?? null);
        self::addIfNonEmpty($schema, 'dateModified', $ctx['date_modified'] ?? null);

        $authorName = trim((string) ($ctx['author_name'] ?? ''));
        if ($authorName !== '') {
            $author = ['@type' => 'Person', 'name' => $authorName];
            self::addIfNonEmpty($author, 'url', $ctx['author_url'] ?? null);
            $schema['author'] = $author;
        }

        if (isset($ctx['publisher']) && is_array($ctx['publisher'])) {
            $schema['publisher'] = $ctx['publisher'];
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $ctx pre-derived: title, url,
     *        date_published?, date_modified?, description?, site_url?,
     *        site_name?
     * @return array<string, mixed>|null
     */
    public static function webpage(array $ctx): ?array
    {
        $title = trim((string) ($ctx['title'] ?? ''));
        $url = trim((string) ($ctx['url'] ?? ''));

        if ($title === '' || $url === '') {
            return null;
        }

        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'WebPage',
            'name' => $title,
            'url' => $url,
        ];

        self::addIfNonEmpty($schema, 'description', $ctx['description'] ?? null);
        self::addIfNonEmpty($schema, 'datePublished', $ctx['date_published'] ?? null);
        self::addIfNonEmpty($schema, 'dateModified', $ctx['date_modified'] ?? null);

        $isPartOf = self::buildIsPartOf($ctx);
        if ($isPartOf !== null) {
            $schema['isPartOf'] = $isPartOf;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $ctx pre-derived: name, url,
     *        description?, site_url?, site_name?
     * @return array<string, mixed>|null
     */
    public static function collection(array $ctx): ?array
    {
        $name = trim((string) ($ctx['name'] ?? ''));
        $url = trim((string) ($ctx['url'] ?? ''));

        if ($name === '' || $url === '') {
            return null;
        }

        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $url,
        ];

        self::addIfNonEmpty($schema, 'description', $ctx['description'] ?? null);

        $isPartOf = self::buildIsPartOf($ctx);
        if ($isPartOf !== null) {
            $schema['isPartOf'] = $isPartOf;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $ctx pre-derived: title, url,
     *        description?
     * @return array<string, mixed>|null
     */
    public static function contactPage(array $ctx): ?array
    {
        $title = trim((string) ($ctx['title'] ?? ''));
        $url = trim((string) ($ctx['url'] ?? ''));

        if ($title === '' || $url === '') {
            return null;
        }

        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'ContactPage',
            'name' => $title,
            'url' => $url,
        ];

        self::addIfNonEmpty($schema, 'description', $ctx['description'] ?? null);

        return $schema;
    }

    /**
     * @param array<string, mixed> $ctx pre-derived: title, url,
     *        description?, main_entity?
     * @return array<string, mixed>|null
     */
    public static function aboutPage(array $ctx): ?array
    {
        $title = trim((string) ($ctx['title'] ?? ''));
        $url = trim((string) ($ctx['url'] ?? ''));

        if ($title === '' || $url === '') {
            return null;
        }

        $schema = [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'AboutPage',
            'name' => $title,
            'url' => $url,
        ];

        self::addIfNonEmpty($schema, 'description', $ctx['description'] ?? null);

        if (isset($ctx['main_entity']) && is_array($ctx['main_entity'])) {
            $schema['mainEntity'] = $ctx['main_entity'];
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $ctx pre-derived: title, url,
     *        items: array<int, array{question?: string, answer?: string}>
     * @return array<string, mixed>|null
     */
    public static function faqPage(array $ctx): ?array
    {
        $title = trim((string) ($ctx['title'] ?? ''));
        $url = trim((string) ($ctx['url'] ?? ''));
        $items = (array) ($ctx['items'] ?? []);

        $mainEntity = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $mainEntity[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($title === '' || $url === '' || $mainEntity === []) {
            return null;
        }

        return [
            '@context' => self::SCHEMA_CONTEXT,
            '@type' => 'FAQPage',
            'name' => $title,
            'url' => $url,
            'mainEntity' => $mainEntity,
        ];
    }

    // -----------------------------------------------------------------
    // Internal helpers — empty-safe assembly
    // -----------------------------------------------------------------

    /**
     * Adds $key => $value to $schema only when $value is a non-empty
     * string. Never emits a blank-field key (M8: no partial shapes).
     *
     * @param array<string, mixed> $schema
     */
    private static function addIfNonEmpty(array &$schema, string $key, mixed $value): void
    {
        if (!is_string($value)) {
            return;
        }

        $value = trim($value);
        if ($value === '') {
            return;
        }

        $schema[$key] = $value;
    }

    /**
     * Adds `sameAs` only when the social-profiles list is non-empty —
     * an empty config array must never produce `"sameAs": []`.
     *
     * @param array<string, mixed> $schema
     * @param mixed $socialProfiles
     */
    private static function addSameAs(array &$schema, mixed $socialProfiles): void
    {
        $profiles = array_values(array_filter(
            (array) $socialProfiles,
            static fn($v): bool => is_string($v) && trim($v) !== '',
        ));

        if ($profiles !== []) {
            $schema['sameAs'] = $profiles;
        }
    }

    /**
     * PostalAddress sub-structure — included ONLY when every required
     * sub-key (street, city, region, postal_code, country) is present
     * and non-empty. A partial address is omitted entirely, never
     * emitted with blank fields.
     *
     * @param array<string, mixed> $address
     * @return array<string, mixed>|null
     */
    private static function buildBusinessAddress(array $address): ?array
    {
        $required = ['street', 'city', 'region', 'postal_code', 'country'];
        foreach ($required as $key) {
            if (trim((string) ($address[$key] ?? '')) === '') {
                return null;
            }
        }

        return [
            '@type' => 'PostalAddress',
            'streetAddress' => (string) $address['street'],
            'addressLocality' => (string) $address['city'],
            'addressRegion' => (string) $address['region'],
            'postalCode' => (string) $address['postal_code'],
            'addressCountry' => (string) $address['country'],
        ];
    }

    /**
     * GeoCoordinates sub-structure — included ONLY when both latitude
     * and longitude are present and non-empty.
     *
     * @param array<string, mixed> $geo
     * @return array<string, mixed>|null
     */
    private static function buildGeo(array $geo): ?array
    {
        $lat = trim((string) ($geo['latitude'] ?? ''));
        $lng = trim((string) ($geo['longitude'] ?? ''));

        if ($lat === '' || $lng === '') {
            return null;
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    /**
     * `isPartOf` sub-structure for WebPage/CollectionPage — included
     * only when both site_url and site_name are present.
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    private static function buildIsPartOf(array $ctx): ?array
    {
        $siteUrl = trim((string) ($ctx['site_url'] ?? ''));
        $siteName = trim((string) ($ctx['site_name'] ?? ''));

        if ($siteUrl === '' || $siteName === '') {
            return null;
        }

        return [
            '@type' => 'WebSite',
            'url' => $siteUrl,
            'name' => $siteName,
        ];
    }
}
