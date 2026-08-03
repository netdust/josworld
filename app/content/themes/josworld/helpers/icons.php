<?php
/**
 * Icon Helper Functions
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

/**
 * Render an inline SVG icon from the theme's icons/ directory.
 *
 * SVGs are read once and cached per (name, class) pair. Drop new icons
 * into icons/<name>.svg and reference them by name (no extension).
 *
 * @param string $name  Icon name (without .svg extension)
 * @param string $class Optional CSS classes added to the <svg> element
 * @return string       SVG markup, or '' if the icon file is missing
 */
function josworld_icon(string $name, string $class = ''): string
{
    static $cache = [];
    $key = $name . '|' . $class;

    if (!isset($cache[$key])) {
        $path = get_theme_file_path("icons/{$name}.svg");

        if (!file_exists($path)) {
            $cache[$key] = '';
            return '';
        }

        $svg = file_get_contents($path);

        if ($class) {
            $svg = preg_replace(
                '/<svg/',
                '<svg class="' . esc_attr($class) . '"',
                $svg,
                1
            );
        }

        $cache[$key] = $svg;
    }

    return $cache[$key];
}
