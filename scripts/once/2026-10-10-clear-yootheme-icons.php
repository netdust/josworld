<?php
/**
 * One-off, per environment, together with the deploy that sets baseline's `site_icon`:
 *
 *   wp eval-file scripts/once/2026-10-10-clear-yootheme-icons.php
 *
 * The icon set now ships with the theme (images/icon/) and WordPress's Site Icon tags print it. YOOtheme's
 * Customizer favicon and touch icon, when filled, replace those tags, so they are emptied. Idempotent.
 * Database content, not code: the staging -> production copy carries it.
 */
$config = json_decode((string) get_theme_mod('config'), true);
if (! is_array($config)) {
    exit("no YOOtheme config found\n");
}

$had = array_intersect_key($config, array_flip(['favicon', 'touchicon', 'favicon_svg']));
if ($had === []) {
    exit("already done, nothing to clear\n");
}

foreach (array_keys($had) as $key) {
    unset($config[$key]);
}
set_theme_mod('config', wp_json_encode($config));
echo 'cleared: ' . json_encode($had) . "\n";
