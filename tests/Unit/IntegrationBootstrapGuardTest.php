<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use NtdstTests\Support\DetectsTimeoutBinary;

/**
 * Contract test for the tests/bootstrap-integration.php dev-DB guard: an
 * allow-list — only the dedicated `wptests` DB, or one named via the
 * WP_TESTS_ALLOW_DB escape hatch, may run. wp-phpunit DROPS AND RECREATES
 * tables in DB_NAME, so a deny-list would destroy a `prod_copy` import.
 *
 * Each case runs the real bootstrap in a `php` subprocess against a
 * throwaway fixture config. A config that passes the guard proceeds toward
 * wp-phpunit and fails later on the missing WP env (expected, unasserted);
 * `timeout` where available and the fixture's closed-port DB_HOST bound
 * those runs.
 */
final class IntegrationBootstrapGuardTest extends TestCase
{
    use DetectsTimeoutBinary;

    private const GUARD_MESSAGE = 'FATAL: integration suite refused to run';

    public function test_guard_refuses_the_dev_database(): void
    {
        $result = $this->run_bootstrap_against(['db_name' => 'db']);

        $this->assertSame(1, $result['exit'], 'guard must exit 1 for the dev database');
        $this->assertStringContainsString(self::GUARD_MESSAGE, $result['stderr']);
        $this->assertStringNotContainsString('Integration suite DB:', $result['stdout']);
    }

    public function test_guard_refuses_any_database_not_on_the_allow_list(): void
    {
        // THE allow-list contract: a prod_copy import is NOT the dev db `db`,
        // and the old deny-list let it straight through to table destruction.
        $result = $this->run_bootstrap_against(['db_name' => 'prod_copy']);

        $this->assertSame(1, $result['exit'], 'guard must exit 1 for any unlisted database');
        $this->assertStringContainsString(self::GUARD_MESSAGE, $result['stderr']);
        $this->assertStringNotContainsString('Integration suite DB:', $result['stdout']);
    }

    public function test_guard_passes_the_dedicated_wptests_database(): void
    {
        $result = $this->run_bootstrap_against(['db_name' => 'wptests']);

        $this->assertStringNotContainsString(self::GUARD_MESSAGE, $result['stderr']);
        // The echo prints AFTER the guard — its presence proves the
        // process got past it.
        $this->assertStringContainsString('Integration suite DB: wptests', $result['stdout']);
    }

    public function test_escape_hatch_env_allows_an_explicitly_named_test_database(): void
    {
        $result = $this->run_bootstrap_against([
            'db_name' => 'custom_tests',
            'env' => ['WP_TESTS_ALLOW_DB' => 'custom_tests'],
        ]);

        $this->assertStringNotContainsString(self::GUARD_MESSAGE, $result['stderr']);
        $this->assertStringContainsString('Integration suite DB: custom_tests', $result['stdout']);
    }

    /**
     * Run tests/bootstrap-integration.php in a real subprocess against a
     * throwaway fixture config defining the given DB_NAME.
     *
     * @param array{db_name: string, env?: array<string, string>} $case
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function run_bootstrap_against(array $case): array
    {
        $config = tempnam(sys_get_temp_dir(), 'wp-guard-config-');
        $stdoutFile = tempnam(sys_get_temp_dir(), 'wp-guard-out-');
        $stderrFile = tempnam(sys_get_temp_dir(), 'wp-guard-err-');
        $this->assertNotFalse($config);
        $this->assertNotFalse($stdoutFile);
        $this->assertNotFalse($stderrFile);

        file_put_contents($config, $this->fixture_config($case['db_name']));

        $envPrefix = 'WP_PHPUNIT__TESTS_CONFIG=' . escapeshellarg($config);
        foreach ($case['env'] ?? [] as $name => $value) {
            $envPrefix .= ' ' . $name . '=' . escapeshellarg($value);
        }

        $bootstrap = dirname(__DIR__) . '/bootstrap-integration.php';
        // `timeout` bounds pass-guard runs where available; otherwise the
        // closed-port DB_HOST fails them fast.
        $command = sprintf(
            '%s %sphp %s > %s 2> %s',
            $envPrefix,
            $this->timeout_prefix(),
            escapeshellarg($bootstrap),
            escapeshellarg($stdoutFile),
            escapeshellarg($stderrFile),
        );

        exec($command, $unusedOutput, $exit);

        $stdout = (string) file_get_contents($stdoutFile);
        $stderr = (string) file_get_contents($stderrFile);
        unlink($config);
        unlink($stdoutFile);
        unlink($stderrFile);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function fixture_config(string $dbName): string
    {
        $abspath = dirname(__DIR__, 2) . '/app/wp/';
        $contentDir = dirname(__DIR__, 2) . '/app/content';

        return <<<PHP
            <?php

            declare(strict_types=1);

            define('ABSPATH', '{$abspath}');
            define('WP_CONTENT_DIR', '{$contentDir}');
            define('WP_DEFAULT_THEME', 'twentytwentyfive');

            define('DB_NAME', '{$dbName}');
            define('DB_USER', 'db');
            define('DB_PASSWORD', 'db');
            // Closed local port: anything that gets past the guard and tries
            // to connect fails FAST instead of hanging until `timeout`.
            define('DB_HOST', '127.0.0.1:9');
            define('DB_CHARSET', 'utf8mb4');

            \$table_prefix = 'wptests_';

            define('WP_TESTS_DOMAIN', 'example.org');
            define('WP_TESTS_EMAIL', 'admin@example.org');
            define('WP_TESTS_TITLE', 'Test Site');
            define('WP_PHP_BINARY', 'php');
            define('WP_DEBUG', true);
            PHP;
    }
}
