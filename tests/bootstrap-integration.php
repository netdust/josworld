<?php

/**
 * Integration-tier bootstrap.
 *
 * Counterpart to bootstrap-unit.php: where the unit tier loads NO WordPress
 * and touches NO database, this tier loads the full wp-phpunit test scaffold
 * (real WordPress from app/wp, installed into the dedicated `wptests`
 * database on first run).
 *
 * Tests-config resolution order:
 *   1. WP_PHPUNIT__TESTS_CONFIG env var (also how the dev-DB guard is proven)
 *   2. tests/wp-tests-config.php (canonical)
 *
 * A bootstrap by nature both declares symbols and executes side effects —
 * exempt from the PSR-1 side-effects rule.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$ntdstTestsConfig = getenv('WP_PHPUNIT__TESTS_CONFIG') ?: __DIR__ . '/wp-tests-config.php';

// wp-phpunit reads this constant as its config location. Loading the same
// path here first is safe (require_once dedupes) and keeps $table_prefix in
// scope for wp-phpunit's bootstrap, which runs in this same include chain.
define('WP_TESTS_CONFIG_FILE_PATH', $ntdstTestsConfig);
require_once $ntdstTestsConfig;

// HARD GUARD — the never-touch-dev-db contract, made mechanical (SC-3).
// ALLOW-LIST, not deny-list (review C4 I2): the wp-phpunit scaffold DROPS
// AND RECREATES tables in DB_NAME, so the ONLY acceptable targets are the
// dedicated `wptests` database or a database explicitly named via the
// WP_TESTS_ALLOW_DB escape hatch. Anything else (the dev db, a prod_copy
// import, a typo) dies here, BEFORE WordPress installs.
// Contract test: tests/Unit/IntegrationBootstrapGuardTest.php.
// phpstan folds DB_NAME from the canonical config it analyses; at runtime
// the WP_PHPUNIT__TESTS_CONFIG env override can load a config with ANY value.
// @phpstan-ignore booleanAnd.alwaysFalse, notIdentical.alwaysFalse, notIdentical.alwaysTrue
if (!defined('DB_NAME') || (DB_NAME !== 'wptests' && DB_NAME !== getenv('WP_TESTS_ALLOW_DB'))) {
    fwrite(
        STDERR,
        'FATAL: integration suite refused to run: DB_NAME resolves to '
        . (defined('DB_NAME') ? "'" . DB_NAME . "'" : 'undefined')
        . ', which is not an allowed test database. The integration tier runs'
        . ' ONLY against the dedicated wptests database (or a database'
        . ' explicitly named in the WP_TESTS_ALLOW_DB env var).' . PHP_EOL,
    );
    exit(1);
}

// SC-3: verifiably name the database this suite runs against in the output.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI bootstrap output, not HTML.
echo 'Integration suite DB: ' . DB_NAME . PHP_EOL;

// wp-phpunit exports its location via putenv() in its composer-autoloaded
// __loaded.php — present because the autoloader above ran; a broken install
// fatals loudly on the require itself.
require getenv('WP_PHPUNIT__DIR') . '/includes/bootstrap.php';
