<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

/**
 * The outgoing-mail block self-disables when it lands on production. That
 * decision used to be a substring test against WP_HOME, which is wrong the
 * moment staging is a SUBDOMAIN of production — `https://staging.jos.agency`
 * contains `jos.agency`, so the block disabled itself on staging and let real
 * mail out. josworld is exactly that shape, and FluentCRM now sends as a real
 * address, so these cases pin the host comparison as exact.
 *
 * The template carries a `__PRODUCTION_HOST__` placeholder the Makefile
 * substitutes with `environments.production.url` minus scheme; the tests
 * substitute it the same way before loading the file.
 */
final class MailBlockGuardTest extends TestCase
{
    private const TEMPLATE = __DIR__ . '/../../scripts/remote/00-block-outgoing-mail.php';

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('ntdst_mail_block_is_production')) {
            $src = file_get_contents(self::TEMPLATE);
            $src = str_replace('__PRODUCTION_HOST__', 'jos.agency', $src);
            $src = preg_replace('/^<\?php/', '', $src, 1);

            eval($src);
        }
    }

    /**
     * @dataProvider blockedHosts
     */
    public function test_non_production_hosts_keep_the_block_active(string $home): void
    {
        $this->assertFalse(
            ntdst_mail_block_is_production($home, 'jos.agency'),
            "{$home} must NOT be treated as production",
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedHosts(): array
    {
        return [
            'staging subdomain'   => ['https://staging.jos.agency'],
            'staging with path'   => ['https://staging.jos.agency/wp'],
            'local ddev'          => ['https://josworld.ddev.site'],
            'unrelated host'      => ['https://example.com'],
            // A look-alike domain must not disable the block either.
            'suffix look-alike'   => ['https://notjos.agency'],
            'empty WP_HOME'       => [''],
        ];
    }

    /**
     * @dataProvider productionHosts
     */
    public function test_the_production_host_disables_the_block(string $home): void
    {
        $this->assertTrue(
            ntdst_mail_block_is_production($home, 'jos.agency'),
            "{$home} must be treated as production",
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function productionHosts(): array
    {
        return [
            'bare https'   => ['https://jos.agency'],
            'trailing path' => ['https://jos.agency/'],
            'http'         => ['http://jos.agency'],
            'mixed case'   => ['https://JOS.Agency'],
        ];
    }
}
