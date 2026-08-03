<?php

/**
 * Unit-tier bootstrap.
 *
 * Deliberately does NOT load WordPress and does NOT touch the database.
 * Brain Monkey (loaded via the composer autoloader) stands in for any
 * WordPress function a unit under test calls.
 *
 * A bootstrap by nature both declares symbols (ABSPATH) and executes
 * side effects (requires) — exempt from the PSR-1 side-effects rule.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Every ntdst-core file opens with `defined('ABSPATH') || exit;`.
// Define it BEFORE requiring any ntdst-core file, or the include
// silently exits and the class never loads. The path is never read
// at unit tier — it only needs to be non-empty.
defined('ABSPATH') || define('ABSPATH', '/tmp/');

// mu-plugins have no autoloader — require units under test directly.
// NOTE: Container.php is NOT part of this template repo — it is vendored
// into app/content/mu-plugins/ntdst-core/ by the wp-starter scaffold at
// new-site time. The unit tier (and ContainerTest) only runs after that.
require_once dirname(__DIR__) . '/app/content/mu-plugins/ntdst-core/core/Container.php';

// Shared test-only helpers (no PSR-4 for tests/ — require explicitly).
require_once __DIR__ . '/Support/DetectsTimeoutBinary.php';

require_once __DIR__ . '/Unit/TestCase.php';
