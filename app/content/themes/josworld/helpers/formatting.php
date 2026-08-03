<?php
/**
 * Formatting Helper Functions
 *
 * Generic, brand-agnostic formatters. Add project-specific formatters here
 * as the site grows — keep them pure (no WP queries, no side effects).
 *
 * @package josworld
 */

defined('ABSPATH') || exit;

/**
 * Format a date in Dutch (month + day names localised).
 *
 * @param string $date   Date string parseable by strtotime()
 * @param string $format PHP date() format
 * @return string        Dutch-formatted date, or '' if unparseable
 */
function josworld_format_date(string $date, string $format = 'j F Y'): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return '';
    }

    $months_en = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];
    $months_nl = [
        'januari', 'februari', 'maart', 'april', 'mei', 'juni',
        'juli', 'augustus', 'september', 'oktober', 'november', 'december',
    ];
    $days_en = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $days_nl = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];

    $formatted = date($format, $timestamp);
    $formatted = str_replace($months_en, $months_nl, $formatted);
    $formatted = str_replace($days_en, $days_nl, $formatted);

    return $formatted;
}

/**
 * Format an amount in cents as a Euro string (e.g. 1995 → "€ 19,95").
 *
 * @param int $cents Amount in cents
 * @return string    Formatted price
 */
function josworld_format_money(int $cents): string
{
    return '€ ' . number_format($cents / 100, 2, ',', '.');
}
