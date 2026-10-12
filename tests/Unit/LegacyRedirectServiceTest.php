<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use josworld\services\site\LegacyRedirectService;

/**
 * The old josworld.org addresses and where they go (tab Regels of the redirect list, in its priority order).
 * The table is the list's own; the patterns catch what it cannot name.
 */
final class LegacyRedirectServiceTest extends TestCase
{
    private const HOME = 'https://jos.agency/';

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/site/LegacyRedirectService.php';
    }

    private function service(): LegacyRedirectService
    {
        return new LegacyRedirectService([
            'exact' => [
                '' => self::HOME,
                '/aanpak' => 'https://jos.agency/samenwerken/',
                '/contact' => 'https://jos.agency/contact/',
                '/tag/corona-en' => self::HOME,
                '/work/onze-energie' => 'https://jos.agency/ons-werk/onze-energie/',
            ],
            'ids' => [5 => self::HOME, 3675 => 'https://jos.agency/samenwerken/'],
        ]);
    }

    /** @dataProvider moves */
    public function test_an_old_address_goes_where_the_list_says(string $uri, ?string $target): void
    {
        $this->assertSame($target, $this->service()->resolve($uri));
    }

    /** @return array<string, array{string, ?string}> */
    public static function moves(): array
    {
        return [
            'the home page' => ['/', self::HOME],
            'a listed page' => ['/aanpak/', 'https://jos.agency/samenwerken/'],
            'with or without the trailing slash' => ['/contact', 'https://jos.agency/contact/'],
            'any case' => ['/Contact/', 'https://jos.agency/contact/'],
            'tracking parameters are ignored' => ['/aanpak/?utm_source=x&gclid=1', 'https://jos.agency/samenwerken/'],
            'a listed case beats the catch-all' => ['/work/onze-energie/', 'https://jos.agency/ons-werk/onze-energie/'],
            'a listed tag beats the tag catch-all' => ['/tag/corona-en/', self::HOME],
            '10: every French path goes home' => ['/fr/aanpak/', self::HOME],
            '10: and English, listed or not' => ['/en/blog/ooit/', self::HOME],
            '10: only a language segment, not a prefix' => ['/english-lessons/', null],
            '20: an explicit language parameter goes home' => ['/aanpak/?lang=fr', self::HOME],
            '20: in any position' => ['/aanpak/?utm=1&lang=en&x=2', self::HOME],
            '30: a known page id' => ['/?page_id=3675', 'https://jos.agency/samenwerken/'],
            '30: or post id' => ['/?p=5', self::HOME],
            '30: an unknown id is a 404, not home' => ['/?p=999', null],
            '50: an unlisted case: the cases' => ['/work/verwijderd/', 'https://jos.agency/ons-werk/'],
            '50: and the old project overview' => ['/projecten/', 'https://jos.agency/ons-werk/'],
            '60: the blog and its pages' => ['/blog/page/3/', 'https://jos.agency/blog-nieuws/'],
            '70: a dated blog path' => ['/2021/04/12/iets/', 'https://jos.agency/blog-nieuws/'],
            '70: a year archive' => ['/2020/', 'https://jos.agency/blog-nieuws/'],
            '75: a blog slug without a date' => ['/blog-over-iets/', 'https://jos.agency/blog-nieuws/'],
            '80: an author archive' => ['/author/thijs/', 'https://jos.agency/blog-nieuws/'],
            '90: the uncategorised category' => ['/category/geen-categorie/', 'https://jos.agency/blog-nieuws/'],
            '100: a service category' => ['/category/branding/', 'https://jos.agency/ons-werk/'],
            '110: anything else stays a 404' => ['/iets-onbekends/', null],
            'a bare 4-digit slug is not a date archive' => ['/20000-leagues/', null],
        ];
    }

    /** @dataProvider hosts */
    public function test_only_the_old_domain_is_handled(string $host, bool $handled): void
    {
        $this->assertSame($handled, $this->service()->handlesHost($host));
    }

    /** @return array<string, array{string, bool}> */
    public static function hosts(): array
    {
        return [
            'the old domain' => ['josworld.org', true],
            'with www' => ['WWW.JOSWORLD.ORG', true],
            'with a port' => ['josworld.org:443', true],
            'the new domain is left alone' => ['jos.agency', false],
            'staging is left alone' => ['staging.jos.agency', false],
            'a look-alike' => ['josworld.org.evil.test', false],
            'empty' => ['', false],
        ];
    }

    public function test_every_destination_is_on_the_new_domain(): void
    {
        $table = require __DIR__ . '/../../app/content/mu-plugins/josworld-core/config/legacy-redirects.php';

        $this->assertNotEmpty($table['exact']);
        foreach ([...$table['exact'], ...$table['ids']] as $target) {
            $this->assertStringStartsWith('https://jos.agency/', $target);
        }
    }
}
