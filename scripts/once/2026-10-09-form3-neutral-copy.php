<?php
/**
 * One-off, per environment (any time; independent of the code deploy):
 *
 *   wp eval-file scripts/once/2026-10-09-form3-neutral-copy.php
 *
 * A tool can be a file OR a link, so form 3 ("Download tool") stops promising a download: the confirmation says
 * "Je tool staat klaar" and the submit button "Ontvang de tool". Idempotent; prints the old texts first.
 * Database content, not code: a deploy does not carry it, the staging -> production copy does.
 */
global $wpdb;

$changes = 0;

// 1. The confirmation sentence.
$metaTable = $wpdb->prefix . 'fluentform_form_meta';
$row = $wpdb->get_row("SELECT id, value FROM $metaTable WHERE form_id = 3 AND meta_key = 'formSettings'");
if ($row) {
    $settings = json_decode((string) $row->value, true);
    $message = (string) ($settings['confirmation']['messageToShow'] ?? '');
    $new = str_replace('Je download staat klaar', 'Je tool staat klaar', $message);
    if ($new !== $message) {
        echo "confirmation was: $message\n";
        $settings['confirmation']['messageToShow'] = $new;
        $wpdb->update($metaTable, ['value' => wp_json_encode($settings)], ['id' => $row->id]);
        echo "confirmation now: $new\n";
        $changes++;
    }
}

// 2. The submit button.
$formTable = $wpdb->prefix . 'fluentform_forms';
$form = $wpdb->get_row("SELECT id, form_fields FROM $formTable WHERE id = 3");
if ($form) {
    $fields = json_decode((string) $form->form_fields, true);
    $text = $fields['submitButton']['settings']['button_ui']['text'] ?? null;
    if ($text === 'Download') {
        echo "submit button was: $text\n";
        $fields['submitButton']['settings']['button_ui']['text'] = 'Ontvang de tool';
        $wpdb->update($formTable, ['form_fields' => wp_json_encode($fields)], ['id' => $form->id]);
        echo "submit button now: Ontvang de tool\n";
        $changes++;
    }
}

echo $changes ? "done ($changes changed)\n" : "already done, nothing to change\n";
