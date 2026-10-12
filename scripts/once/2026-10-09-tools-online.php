<?php
/**
 * One-off, per environment, AFTER the deploy that adds ToolAppService (and after `wp rewrite flush --hard`):
 *
 *   wp eval-file scripts/once/2026-10-09-tools-online.php [path/to/JOS_Backlashplanner.xlsx]
 *
 * Puts the three tools in docs/tools online, as tools of the right kind:
 *  - "Faire aanbestedingsscan" and "Campagnebasis" become LINK tools pointing at their tool app (/tool-app/<slug>/);
 *  - "Backlashplanner" becomes a FILE tool: the workbook (path given as the first argument) goes into the media
 *    library and onto the tool.
 * Found by title; idempotent; says what it did. Database content, not code: the staging -> production copy carries it.
 */
$byTitle = static function (string $title): ?int {
    $found = get_posts(['post_type' => 'tool', 'post_status' => 'any', 'numberposts' => 1, 'title' => $title, 'fields' => 'ids']);

    return $found ? (int) $found[0] : null;
};

foreach (['Faire aanbestedingsscan' => 'faire-aanbestedingsscan', 'Campagnebasis' => 'campagnestart'] as $title => $app) {
    $id = $byTitle($title);
    if (! $id) { echo "NOT FOUND (link tool): $title\n"; continue; }
    $url = home_url('/tool-app/' . $app . '/');
    update_post_meta($id, '_jw_delivery', 'url');
    update_post_meta($id, '_jw_url', $url);
    echo "#$id $title -> link $url\n";
}

$id = $byTitle('Backlashplanner');
if (! $id) { echo "NOT FOUND (file tool): Backlashplanner\n"; return; }

$existing = (int) get_post_meta($id, '_jw_file', true);
if ($existing > 0 && get_post($existing)) { echo "#$id Backlashplanner already has file #$existing\n"; update_post_meta($id, '_jw_delivery', 'file'); return; }

$path = $args[0] ?? '';
if ($path === '' || ! is_readable($path)) { echo "#$id Backlashplanner: give the workbook path as the first argument\n"; return; }

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
// Sideloading moves the file it is given: hand it a copy, never the checkout's own.
$copy = wp_tempnam('JOS_Backlashplanner.xlsx');
if (! copy($path, $copy)) { echo "could not copy $path\n"; return; }
$attachmentId = media_handle_sideload(['name' => 'JOS_Backlashplanner.xlsx', 'tmp_name' => $copy], $id);
if (is_wp_error($attachmentId)) { echo 'upload FAILED: ' . $attachmentId->get_error_message() . "\n"; return; }

update_post_meta($id, '_jw_delivery', 'file');
update_post_meta($id, '_jw_file', $attachmentId);
echo "#$id Backlashplanner -> file #$attachmentId " . wp_get_attachment_url($attachmentId) . "\n";
