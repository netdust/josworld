<?php

/**
 * The dashboard's booking reader.
 *
 * READ ONLY, and defensive on the three axes the plan's threat model names:
 *
 *  - T4, never fatal. fluent-booking is WP-installed, not Composer-wired, so
 *    "the table is not there" is the DEFAULT state of a fresh clone. Every
 *    failure path returns an empty list, because a fatal here takes the whole
 *    Josworld menu down with it.
 *  - T3, capability BEFORE the query. A booking carries a third party's name,
 *    mail address and phone number. The gate precedes the read, so that data
 *    never leaves the database for a user who may not see it.
 *  - T2, the limit is bound, never interpolated.
 *
 * The availability guard is a TABLE check rather than `class_exists()` on a
 * fluent-booking symbol: `class_exists` is a PHP internal Brain Monkey cannot
 * redefine without a project-wide patchwork.json, and the table check covers
 * the failure that actually matters.
 *
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\admin;

defined('ABSPATH') || exit;

final class BookingRepository implements ActivitySource
{
    /** At most this many rows reach the dashboard panel. */
    public const LIMIT = 5;

    /**
     * Administrators only. An author holds `edit_posts` — which is what opens
     * the Josworld menu — so anything weaker would publish third parties'
     * personal data to every content editor on the site.
     */
    public const CAPABILITY = 'manage_options';

    /** @return list<array{who: string, event: string, when: string, url: string}> */
    public function latest(): array
    {
        if (! current_user_can(self::CAPABILITY)) {
            return [];
        }

        global $wpdb;

        $bookings = $wpdb->prefix . 'fcal_bookings';
        $events = $wpdb->prefix . 'fcal_calendar_events';

        if (! $this->tableExists($wpdb, $bookings)) {
            return [];
        }

        // One JOIN for the whole panel; a per-row event lookup would be eleven
        // queries on every dashboard load. Table and column names are
        // literals — only the limit is bound.
        // Suppressed, not ignored: the table names are $wpdb->prefix plus a literal
        // and the only variable is the bound %d, which the sniff cannot see through the
        // interpolation. Uncached because the read is capability-gated per user and five
        // rows deep — a cache would need invalidating on every write to save one query.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.first_name, b.last_name, b.email, b.start_time, b.status,
                    e.title AS event_title
             FROM {$bookings} AS b
             LEFT JOIN {$events} AS e ON e.id = b.event_id
             ORDER BY b.start_time DESC
             LIMIT %d",
            self::LIMIT,
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return array_map([$this, 'toRow'], is_array($rows) ? $rows : []);
    }

    private function tableExists(object $wpdb, string $table): bool
    {
        // An existence probe, fully prepared. The condition it guards IS a missing table.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /** @return array{who: string, event: string, when: string, url: string} */
    private function toRow(object $row): array
    {
        return [
            'who' => $this->booker($row),
            'event' => (string) ($row->event_title ?? ''),
            'when' => (string) ($row->start_time ?? ''),
            'url' => admin_url('admin.php?page=fluent-booking#/scheduled-events?booking_id=' . (int) ($row->id ?? 0)),
        ];
    }

    /**
     * The booker names themselves, so every field here is optional in
     * practice. Falls back through name → mail → a placeholder rather than
     * rendering an empty cell.
     */
    private function booker(object $row): string
    {
        $name = trim(implode(' ', array_filter([
            (string) ($row->first_name ?? ''),
            (string) ($row->last_name ?? ''),
        ])));

        if ($name !== '') {
            return $name;
        }

        $email = (string) ($row->email ?? '');

        return $email !== '' ? $email : __('Onbekend', JOSWORLD_CORE_TEXTDOMAIN);
    }
}
