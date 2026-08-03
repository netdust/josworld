<?php

/**
 * Plugin Name: Josworld Core Loader
 * Description: Loads the Josworld Core plugin (business-logic sectors) from subdirectory.
 * Version: 1.0.0
 * Author: NTDST
 *
 * Mirrors the daan/stride-core loader pattern: mu-plugins can't live in
 * subdirectories on their own, so this thin loader require's the real
 * plugin entrypoint. All Josworld business logic lives in josworld-core/,
 * keeping it out of the theme (per the josworld theme-config convention).
 */

defined('ABSPATH') || exit;

// Load the actual plugin entrypoint (unconditional require — the
// framework-presence check happens inside josworld-core.php's own
// plugins_loaded callback, not here).
require_once __DIR__ . '/josworld-core/josworld-core.php';
