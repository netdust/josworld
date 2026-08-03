<?php

/**
 * Plugin Name: NTDST Baseline Loader
 * Description: Loads the NTDST Baseline plugin (shared WordPress-gaps
 * baseline: security headers, head cleanup, SEO, schema, maintenance mode,
 * cache headers) from subdirectory.
 * Version: 1.0.0
 * Author: NTDST
 *
 * Mirrors the daan-core loader pattern: mu-plugins can't live in
 * subdirectories on their own, so this thin loader require's the real
 * plugin entrypoint. All baseline logic lives in ntdst-baseline/, keeping
 * it framework-agnostic and free of daan-specific values.
 */

defined('ABSPATH') || exit;

// Load the actual plugin (only if the framework is present).
require_once __DIR__ . '/ntdst-baseline/ntdst-baseline.php';
