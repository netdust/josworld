<?php

/**
 * Integration-tier WordPress test-suite configuration.
 *
 * Points the wp-phpunit scaffold at the DEDICATED `wptests` database
 * (provisioned by the .ddev/config.gates.yaml post-start hook) and resolves
 * the stack layout (rendered by scaffold_wp_starter): core in app/wp, content in app/content.
 *
 * Fully portable (review C4 I6): the scaffold installs its OWN WordPress
 * into wptests, so the domain/email/title are the wp-phpunit canonical
 * placeholders, not this project's URL.
 *
 * NEVER point DB_NAME at a real database — the scaffold drops and recreates
 * tables in its target DB. tests/bootstrap-integration.php enforces an
 * ALLOW-LIST (wptests, or WP_TESTS_ALLOW_DB) before WordPress installs.
 *
 * A config file by nature only executes side effects (defines) —
 * exempt from the PSR-1 side-effects rule.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

// Stack layout (rendered by scaffold_wp_starter): WordPress core lives in app/wp (trailing slash required).
define('ABSPATH', dirname(__DIR__) . '/app/wp/');
define('WP_CONTENT_DIR', dirname(__DIR__) . '/app/content');
define('WP_CONTENT_URL', 'http://example.org/app');

// roots/wordpress ships no bundled themes (app/wp/wp-content/themes does not
// exist) — pin the default theme to the composer-installed one in app/content.
define('WP_DEFAULT_THEME', 'twentytwentyfive');

// Dedicated integration database on the DDEV `db` service. Same db/db
// credentials as the app, DIFFERENT database and table prefix.
define('DB_NAME', 'wptests');
define('DB_USER', 'db');
define('DB_PASSWORD', 'db');
define('DB_HOST', 'db');
define('DB_CHARSET', 'utf8mb4');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'example.org');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'Test Site');
define('WP_PHP_BINARY', 'php');
define('WP_DEBUG', true);
