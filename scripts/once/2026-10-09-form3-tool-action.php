<?php
/**
 * One-off, per environment, run AFTER the deploy that adds `{tool_action}` (DownloadFormService):
 *
 *   wp eval-file scripts/once/2026-10-09-form3-tool-action.php
 *
 * Form 3 ("Download tool") confirmation: `{tool_title} downloaden` becomes `{tool_title} {tool_action}`, so the
 * word follows what the tool is (downloaden | openen). Idempotent; prints the old text first so it can be put back.
 * Database content, not code: a deploy does not carry it, the staging -> production copy does.
 */
global $wpdb;

$table = $wpdb->prefix . 'fluentform_form_meta';
$row = $wpdb->get_row("SELECT id, value FROM $table WHERE form_id = 3 AND meta_key = 'formSettings'");

if (! $row) {
    echo "form 3 has no formSettings here — nothing to do\n";
    return;
}

$settings = json_decode((string) $row->value, true);
$message = (string) ($settings['confirmation']['messageToShow'] ?? '');

if (str_contains($message, '{tool_action}')) {
    echo "already done\n";
    return;
}

$new = str_replace('{tool_title} downloaden', '{tool_title} {tool_action}', $message);
if ($new === $message) {
    echo "pattern not found, left alone: $message\n";
    return;
}

echo "was: $message\n";
$settings['confirmation']['messageToShow'] = $new;
$wpdb->update($table, ['value' => wp_json_encode($settings)], ['id' => $row->id]);
echo "now: $new\n";
