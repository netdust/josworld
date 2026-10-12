<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

use Brain\Monkey\Functions;
use josworld\services\admin\SubmissionRepository;

/**
 * The form-submission reader, without WordPress.
 *
 * The negatives are the substance (plan threat model T2/T3/T4): a
 * positive-only suite passes against a repository that queries for every
 * user, interpolates its limit, and fatals the dashboard the day Fluent
 * Forms is deactivated — which on a fresh clone is the DEFAULT state.
 */
final class SubmissionRepositoryTest extends TestCase
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
            . '/app/content/mu-plugins/josworld-core/services/admin/SubmissionRepository.php';

        $this->getResultsCalls = 0;
    }

    /**
     * The availability guard is a TABLE check, deliberately, not
     * `class_exists()` on a Fluent Forms symbol: `class_exists` is a PHP
     * internal that Brain Monkey cannot redefine without a project-wide
     * patchwork.json, and the table check covers the failure that matters —
     * the dashboard must never fatal because the source went away.
     *
     * @param list<object> $rows
     */
    private function stubWordPress(bool $tableExists, bool $canRead, array $rows = []): void
    {
        $calls = &$this->getResultsCalls;

        $wpdb = new class ($rows, $calls, $tableExists) {
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
                return $this->tableExists ? 'ntdst_fluentform_submissions' : null;
            }
        };

        $GLOBALS['wpdb'] = $this->wpdb = $wpdb;

        Functions\when('current_user_can')->justReturn($canRead);
        Functions\when('esc_url')->returnArg();
        Functions\when('admin_url')->alias(static fn(string $p): string => 'https://josworld.test/wp/wp-admin/' . $p);
        Functions\when('__')->returnArg();
    }

    private function row(int $id, string $title, string $response, string $created, int $formId = 1): object
    {
        return (object) [
            'id' => $id, 'form_id' => $formId, 'form_title' => $title,
            'response' => $response, 'created_at' => $created,
        ];
    }

    public function test_an_unavailable_source_yields_no_rows_and_issues_no_query(): void
    {
        $this->stubWordPress(tableExists: false, canRead: true);

        $this->assertSame([], (new SubmissionRepository())->latest());
        $this->assertSame(
            0,
            $this->getResultsCalls,
            'Fluent Forms absent or its schema moved: no query, no fatal — a fresh clone starts here',
        );
    }

    public function test_a_user_without_the_capability_never_reaches_the_query(): void
    {
        $this->stubWordPress(tableExists: true, canRead: false);

        $this->assertSame([], (new SubmissionRepository())->latest());
        $this->assertSame(
            0,
            $this->getResultsCalls,
            'submissions carry third parties personal data: the capability gate must precede the QUERY, not the render',
        );
    }

    public function test_the_row_limit_is_a_bound_parameter_not_an_interpolated_value(): void
    {
        $this->stubWordPress(tableExists: true, canRead: true);

        (new SubmissionRepository())->latest();

        $this->assertStringContainsString('%d', (string) $this->wpdb->preparedSql);
        $this->assertContains(SubmissionRepository::LIMIT, $this->wpdb->preparedArgs);
    }

    public function test_an_entry_link_opens_the_entry_in_its_own_form(): void
    {
        $this->stubWordPress(
            tableExists: true,
            canRead: true,
            rows: [$this->row(27, 'Download tool', '{"email":"els@example.be"}', '2026-09-13 10:00:00', formId: 3)],
        );

        $url = (new SubmissionRepository())->latest()[0]['url'];

        $this->assertStringContainsString('route=entries&form_id=3#/entries/27', $url, 'form_id=0 is "No form found"');
        $selected = (string) preg_replace('/\s+FROM\b.*$/s', '', (string) $this->wpdb->preparedSql);
        $this->assertStringContainsString('s.form_id', $selected, 'the query must hand back each entry\'s own form id');
    }

    public function test_it_returns_at_most_the_limit_newest_first(): void
    {
        $rows = [
            $this->row(3, 'Download tool', '{"email":"els@example.be"}', '2026-09-13 10:00:00'),
            $this->row(2, 'Contact Form Demo', '{"email":"jan@example.be"}', '2026-09-12 10:00:00'),
        ];
        $this->stubWordPress(tableExists: true, canRead: true, rows: $rows);

        $latest = (new SubmissionRepository())->latest();

        $this->assertCount(2, $latest);
        $this->assertSame('Download tool', $latest[0]['form']);
        $this->assertStringContainsString('DESC', (string) $this->wpdb->preparedSql);
        $this->assertLessThanOrEqual(5, SubmissionRepository::LIMIT);
    }

    /**
     * The form title is joined, not fetched per row: a per-row lookup turns a
     * five-row panel into eleven queries on every dashboard load.
     */
    public function test_the_form_title_arrives_from_one_joined_query(): void
    {
        $rows = [$this->row(1, 'Stageformulier', '{"email":"x@example.be"}', '2026-09-13 10:00:00')];
        $this->stubWordPress(tableExists: true, canRead: true, rows: $rows);

        $latest = (new SubmissionRepository())->latest();

        $this->assertSame('Stageformulier', $latest[0]['form']);
        $this->assertSame(1, $this->getResultsCalls, 'one query for the whole panel');
        $this->assertStringContainsStringIgnoringCase('join', (string) $this->wpdb->preparedSql);
    }

    /**
     * `response` is visitor-authored JSON. Malformed or unexpected shapes must
     * degrade to a usable label, never a PHP warning on an admin screen.
     */
    public function test_an_unparseable_response_degrades_to_a_placeholder(): void
    {
        $rows = [$this->row(1, 'Contact Form Demo', 'not json at all', '2026-09-13 10:00:00')];
        $this->stubWordPress(tableExists: true, canRead: true, rows: $rows);

        $latest = (new SubmissionRepository())->latest();

        $this->assertCount(1, $latest);
        $this->assertNotSame('', $latest[0]['who'], 'a broken payload must still yield a usable label');
    }
}
