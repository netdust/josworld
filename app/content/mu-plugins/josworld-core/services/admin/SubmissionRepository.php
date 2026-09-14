<?php

/**
 * The dashboard's form-submission reader.
 *
 * READ ONLY, and deliberately defensive on three axes the plan's threat model
 * names:
 *
 *  - T4, never fatal. Fluent Forms is WP-installed, not Composer-wired, so
 *    "the table is not there" is the DEFAULT state of a fresh clone, not an
 *    edge case. Every failure path returns an empty list.
 *  - T3, capability BEFORE the query. Submissions carry visitors' names, mail
 *    addresses and message bodies. The gate precedes the read, so the data
 *    never leaves the database for a user who may not see it.
 *  - T2, the limit is bound, never interpolated.
 *
 * The availability guard is a TABLE check rather than `class_exists()` on a
 * Fluent Forms symbol: `class_exists` is a PHP internal that Brain Monkey
 * cannot redefine without a project-wide patchwork.json, and the table check
 * covers the failure that actually matters.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\admin;

defined('ABSPATH') || exit;

final class SubmissionRepository implements ActivitySource
{
    /** At most this many rows reach the dashboard panel. */
    public const LIMIT = 5;

    /**
     * Administrators only. An author holds `edit_posts` — which is what opens
     * the Josworld menu — so anything weaker would publish third parties'
     * personal data to every content editor on the site.
     */
    public const CAPABILITY = 'manage_options';

    /** @return list<array{form: string, who: string, when: string, url: string}> */
    public function latest(): array
    {
        if (! current_user_can(self::CAPABILITY)) {
            return [];
        }

        global $wpdb;

        $submissions = $wpdb->prefix . 'fluentform_submissions';
        $forms = $wpdb->prefix . 'fluentform_forms';

        if (! $this->tableExists($wpdb, $submissions)) {
            return [];
        }

        // One JOIN for the whole panel: a per-row title lookup would turn a
        // five-row list into eleven queries on every dashboard load. Table and
        // column names are literals; only the limit is bound.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.response, s.created_at, f.title AS form_title
             FROM {$submissions} AS s
             INNER JOIN {$forms} AS f ON f.id = s.form_id
             ORDER BY s.created_at DESC
             LIMIT %d",
            self::LIMIT,
        ));

        return array_map([$this, 'toRow'], is_array($rows) ? $rows : []);
    }

    private function tableExists(object $wpdb, string $table): bool
    {
        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /** @return array{form: string, who: string, when: string, url: string} */
    private function toRow(object $row): array
    {
        return [
            'form' => (string) ($row->form_title ?? ''),
            'who' => $this->submitter((string) ($row->response ?? '')),
            'when' => (string) ($row->created_at ?? ''),
            'url' => admin_url('admin.php?page=fluent_forms&route=entries&form_id=0#/entries/' . (int) ($row->id ?? 0)),
        ];
    }

    /**
     * `response` is visitor-authored JSON whose shape follows whatever fields
     * that form happens to have. Anything unexpected — malformed JSON, a form
     * with no name or mail field — degrades to a placeholder rather than a
     * warning on an admin screen.
     */
    private function submitter(string $response): string
    {
        $data = json_decode($response, true);
        if (! is_array($data)) {
            return __('Onbekend', JOSWORLD_CORE_TEXTDOMAIN);
        }

        if (! empty($data['email']) && is_string($data['email'])) {
            return $data['email'];
        }

        $names = $data['names'] ?? null;
        if (is_array($names)) {
            $full = trim(implode(' ', array_filter([
                is_string($names['first_name'] ?? null) ? $names['first_name'] : '',
                is_string($names['last_name'] ?? null) ? $names['last_name'] : '',
            ])));

            if ($full !== '') {
                return $full;
            }
        }

        return __('Onbekend', JOSWORLD_CORE_TEXTDOMAIN);
    }
}
