<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Functions;
use josworld\services\admin\BookingRepository;

/**
 * The booking reader, without WordPress.
 *
 * Contract authored from the plan's threat model BEFORE the implementation
 * (specs/josworld-admin-phase-2, T1-T4). The negatives carry the weight: a
 * positive-only suite passes against a repository that queries for every
 * user, interpolates its limit, and takes the dashboard down with it the day
 * fluent-booking goes away.
 *
 * The availability guard is a TABLE check rather than `class_exists()`:
 * `class_exists` is a PHP internal Brain Monkey cannot redefine without a
 * project-wide patchwork.json, and the table check covers the failure that
 * actually matters.
 */
final class BookingRepositoryTest extends TestCase
{
    private object $wpdb;
    private int $getResultsCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        require_once dirname(__DIR__, 2)
            . '/app/content/mu-plugins/josworld-core/services/admin/BookingRepository.php';

        $this->getResultsCalls = 0;
    }

    /** @param list<object> $rows */
    private function stubWordPress(bool $tableExists, bool $canRead, array $rows = []): void
    {
        $calls = &$this->getResultsCalls;

        $wpdb = new class($rows, $calls, $tableExists) {
            public string $prefix = 'ntdst_';
            public ?string $preparedSql = null;
            /** @var list<mixed> */
            public array $preparedArgs = [];

            /** @param list<object> $rows */
            public function __construct(private array $rows, private int &$calls, private bool $tableExists)
            {
            }

            public function prepare(string $sql, mixed ...$args): string
            {
                $this->preparedSql = $sql;
                $this->preparedArgs = $args;

                return $sql;
            }

            /** @return list<object> */
            public function get_results(string $sql): array
            {
                ++$this->calls;

                return $this->rows;
            }

            public function get_var(string $sql): ?string
            {
                return $this->tableExists ? 'ntdst_fcal_bookings' : null;
            }
        };

        $GLOBALS['wpdb'] = $this->wpdb = $wpdb;

        Functions\when('current_user_can')->justReturn($canRead);
        Functions\when('admin_url')->alias(static fn(string $p): string => 'https://josworld.test/wp/wp-admin/' . $p);
        Functions\when('__')->returnArg();
    }

    private function booking(int $id, string $first, string $last, string $event, string $start): object
    {
        return (object) [
            'id' => $id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first) . '@example.be',
            'event_title' => $event,
            'start_time' => $start,
            'status' => 'scheduled',
        ];
    }

    public function test_an_unavailable_source_yields_no_rows_and_issues_no_query(): void
    {
        $this->stubWordPress(tableExists: false, canRead: true);

        $this->assertSame([], (new BookingRepository())->latest());
        $this->assertSame(
            0,
            $this->getResultsCalls,
            'fluent-booking absent or its schema moved: no query, no fatal — a fresh clone starts here',
        );
    }

    public function test_a_user_without_the_capability_never_reaches_the_query(): void
    {
        $this->stubWordPress(tableExists: true, canRead: false, rows: [
            $this->booking(1, 'Marie', 'Peeters', 'Kennismaking', '2026-09-17 10:00:00'),
        ]);

        $this->assertSame([], (new BookingRepository())->latest());
        $this->assertSame(
            0,
            $this->getResultsCalls,
            'bookings carry third parties names, mail addresses and phone numbers: '
            . 'the capability gate must precede the QUERY, not the render',
        );
    }

    public function test_the_row_limit_is_a_bound_parameter_not_an_interpolated_value(): void
    {
        $this->stubWordPress(tableExists: true, canRead: true);

        (new BookingRepository())->latest();

        $this->assertStringContainsString('%d', (string) $this->wpdb->preparedSql);
        $this->assertContains(BookingRepository::LIMIT, $this->wpdb->preparedArgs);
    }

    public function test_it_returns_rows_newest_first_within_the_limit(): void
    {
        $this->stubWordPress(tableExists: true, canRead: true, rows: [
            $this->booking(2, 'Tom', 'Claes', 'Strategiegesprek', '2026-09-18 14:30:00'),
            $this->booking(1, 'Marie', 'Peeters', 'Kennismaking', '2026-09-17 10:00:00'),
        ]);

        $latest = (new BookingRepository())->latest();

        $this->assertCount(2, $latest);
        $this->assertSame('Tom Claes', $latest[0]['who']);
        $this->assertSame('Strategiegesprek', $latest[0]['event']);
        $this->assertStringContainsString('DESC', (string) $this->wpdb->preparedSql);
        $this->assertLessThanOrEqual(5, BookingRepository::LIMIT);
    }

    /** One query for the panel — a per-row event lookup is eleven queries per load. */
    public function test_the_event_title_arrives_from_one_joined_query(): void
    {
        $this->stubWordPress(tableExists: true, canRead: true, rows: [
            $this->booking(1, 'Marie', 'Peeters', 'Kennismaking', '2026-09-17 10:00:00'),
        ]);

        $latest = (new BookingRepository())->latest();

        $this->assertSame('Kennismaking', $latest[0]['event']);
        $this->assertSame(1, $this->getResultsCalls, 'one query for the whole panel');
        $this->assertStringContainsStringIgnoringCase('join', (string) $this->wpdb->preparedSql);
    }

    /**
     * A booker types their own name. A row missing both names must still
     * produce a usable label rather than an empty cell or a PHP warning.
     */
    public function test_a_nameless_booking_degrades_to_a_placeholder(): void
    {
        $this->stubWordPress(tableExists: true, canRead: true, rows: [
            (object) ['id' => 9, 'first_name' => '', 'last_name' => '', 'email' => '',
                      'event_title' => 'Kennismaking', 'start_time' => '2026-09-17 10:00:00', 'status' => 'scheduled'],
        ]);

        $latest = (new BookingRepository())->latest();

        $this->assertCount(1, $latest);
        $this->assertNotSame('', trim($latest[0]['who']));
    }
}
