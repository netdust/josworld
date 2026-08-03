<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * Integration tier: T12 — the meta-key table in docs/content-model.md is
 * MECHANICALLY tied to the live model config, not hand-copied.
 *
 * THE PROBLEM THIS TEST EXISTS TO PREVENT: a hand-copied doc table is
 * correct on the day it is written and silently wrong the day a field gets
 * renamed, added, or removed on any of the four services — with nothing
 * anywhere flagging the drift. This test parses the doc's own
 * machine-readable table (between the META-KEY-TABLE markers) and asserts
 * it is byte-for-byte the SAME {model, field, meta_key} triple set the real
 * `NTDST_Data_Manager` registry reports, using the identical registry-scope
 * boundary `ModelConventionsTest` already established (registered through
 * `ntdst_data()` AND carrying `JOSWORLD_CORE_META_PREFIX`) — so `dienst`
 * (a taxonomy, not a Data model) is correctly excluded on both sides, and a
 * future fifth model needs no edit to this test file to be swept into the
 * check, only an update to the doc table.
 *
 * RED PROOF (recorded in the task report): with the doc table's `_jw_team`
 * row for `case.team` mutated to `_jw_team_members`, this test fails with a
 * diff naming exactly that row — proving the parser is comparing real
 * content, not passing vacuously.
 */
final class DocumentedMetaKeysConsistencyTest extends WP_UnitTestCase
{
    private const DOC_PATH = __DIR__ . '/../../docs/content-model.md';

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/TeamService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/VacatureService.php';
        require_once __DIR__ . '/../../app/content/mu-plugins/josworld-core/services/content/ToolService.php';

        if (! defined('JOSWORLD_CORE_META_PREFIX')) {
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
        }
        if (! defined('JOSWORLD_CORE_TEXTDOMAIN')) {
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');
        }

        new CaseService(ntdst_data());
        new TeamService(ntdst_data());
        new VacatureService(ntdst_data());
        new ToolService(ntdst_data());
    }

    /**
     * The SECOND half of the drift guard (added at T12 close, after the
     * controller mutation-tested the first).
     *
     * The doc carries meta keys in TWO places: the machine-checked block
     * between the META-KEY-TABLE markers, and the human-readable per-model
     * tables (`| field | meta key | type | notes |`) that a developer
     * actually reads when building a template. Only the first was guarded.
     *
     * Proven, not assumed: renaming `_jw_client` -> `_jw_klant` in the
     * per-model table at line 66 left the suite GREEN, while the same rename
     * inside the marker block correctly failed. So 25 documented meta keys —
     * the ones humans read — could drift from the code silently, which is
     * precisely the failure this task exists to prevent.
     *
     * This asserts the two tables agree with each other. Combined with the
     * marker block already being diffed against the live registry, that
     * transitively pins the human-readable tables to the code too.
     */
    public function test_the_human_readable_tables_agree_with_the_machine_checked_table(): void
    {
        $markerKeys = array_map(
            static fn (string $triple): string => substr($triple, (int) strrpos($triple, '=') + 1),
            $this->parse_documented_triples(),
        );

        $markdown = (string) file_get_contents(self::DOC_PATH);

        // Strip the marker block so its rows are not counted twice.
        $start = strpos($markdown, '<!-- META-KEY-TABLE:START -->');
        $end = strpos($markdown, '<!-- META-KEY-TABLE:END -->');
        if ($start !== false && $end !== false) {
            $markdown = substr($markdown, 0, $start) . substr($markdown, $end);
        }

        preg_match_all('/^\|\s*`[a-z0-9_]+`\s*\|\s*`(_jw_[a-z0-9_]+)`\s*\|/mi', $markdown, $matches);
        $proseKeys = array_values(array_unique($matches[1]));

        $this->assertNotEmpty(
            $proseKeys,
            'precondition: the per-model tables must document at least one `_jw_` meta key, '
            . 'or this comparison passes vacuously — if the doc format changed, this parser '
            . 'must change with it rather than silently matching nothing',
        );

        $undocumentedInMarkerTable = array_values(array_diff($proseKeys, $markerKeys));

        $this->assertSame(
            [],
            $undocumentedInMarkerTable,
            'these meta keys appear in the human-readable per-model tables but NOT in the '
            . 'machine-checked META-KEY-TABLE block, so nothing pins them to the live '
            . 'registry and they can drift silently: ' . implode(', ', $undocumentedInMarkerTable),
        );
    }

    public function test_the_documented_meta_key_table_matches_the_live_model_registry_exactly(): void
    {
        $documented = $this->parse_documented_triples();
        $actual = $this->live_registry_triples();

        $this->assertNotEmpty(
            $documented,
            'precondition: the doc must contain at least one documented row, or this '
            . 'comparison would pass vacuously over an empty set',
        );
        $this->assertNotEmpty(
            $actual,
            'precondition: at least one josworld model must be registered, or this '
            . 'comparison would pass vacuously over an empty set',
        );

        $missingFromDoc = array_values(array_diff($actual, $documented));
        $staleInDoc = array_values(array_diff($documented, $actual));

        $this->assertSame(
            [],
            $missingFromDoc,
            'docs/content-model.md is missing (or has renamed) these real meta keys — the '
            . 'doc has drifted BEHIND the code: ' . implode(', ', $missingFromDoc),
        );

        $this->assertSame(
            [],
            $staleInDoc,
            'docs/content-model.md documents these meta keys but the live registry no longer '
            . 'reports them — the doc has drifted AHEAD of (or diverged from) the code: '
            . implode(', ', $staleInDoc),
        );
    }

    /**
     * Mutation-proof companion: this file's OWN parser must reject an
     * obviously malformed table row rather than silently skipping it — a
     * parser that swallows malformed rows could mask real drift as "row
     * just didn't parse". Proven against a fixture with a short row.
     */
    public function test_the_table_parser_rejects_a_malformed_row_rather_than_silently_skipping_it(): void
    {
        $malformed = "| model | field | meta_key |\n|---|---|---|\n| case | client |\n";

        $this->expectException(\RuntimeException::class);
        $this->parse_triples_from_markdown($malformed);
    }

    /**
     * @return list<string>
     */
    private function parse_documented_triples(): array
    {
        $this->assertFileExists(self::DOC_PATH, 'precondition: docs/content-model.md must exist');

        $markdown = (string) file_get_contents(self::DOC_PATH);

        $start = strpos($markdown, '<!-- META-KEY-TABLE:START -->');
        $end = strpos($markdown, '<!-- META-KEY-TABLE:END -->');

        $this->assertNotFalse($start, 'precondition: doc must contain a META-KEY-TABLE:START marker');
        $this->assertNotFalse($end, 'precondition: doc must contain a META-KEY-TABLE:END marker');

        $tableBlock = substr($markdown, $start, $end - $start);

        return $this->parse_triples_from_markdown($tableBlock);
    }

    /**
     * Parses `| model | field | meta_key |` markdown table rows into
     * `"model.field=meta_key"` triples. Skips the header separator row
     * (`|---|---|---|`) and the header row itself. Throws on any DATA row
     * that does not resolve to exactly 3 cells, rather than silently
     * dropping it (see the malformed-row test above).
     *
     * @return list<string>
     */
    private function parse_triples_from_markdown(string $markdown): array
    {
        $triples = [];

        foreach (explode("\n", $markdown) as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '|') !== 0) {
                continue;
            }

            // Header row and header/body separator are both skipped by name/shape.
            $cells = array_map('trim', explode('|', trim($line, '|')));

            if ($cells === ['model', 'field', 'meta_key']) {
                continue;
            }

            if (preg_match('/^-+$/', $cells[0])) {
                continue;
            }

            if (count($cells) !== 3) {
                throw new \RuntimeException(sprintf(
                    'malformed meta-key table row (expected exactly 3 cells): %s',
                    esc_html($line),
                ));
            }

            [$model, $field, $metaKey] = $cells;
            $triples[] = "{$model}.{$field}={$metaKey}";
        }

        return $triples;
    }

    /**
     * Same registry-scope boundary as ModelConventionsTest::
     * josworld_owned_model_slugs() — registered through ntdst_data() AND
     * carrying JOSWORLD_CORE_META_PREFIX as meta_prefix. `dienst` (a
     * taxonomy) is excluded on the same terms as every model: it fails
     * registry membership, not by a special case here.
     *
     * @return list<string>
     */
    private function live_registry_triples(): array
    {
        $slugs = array_values(array_filter(
            get_post_types([], 'names'),
            static function (string $slug): bool {
                if (! ntdst_data()->isRegistered($slug)) {
                    return false;
                }

                return ntdst_data()->get($slug)->getMetaPrefix() === JOSWORLD_CORE_META_PREFIX;
            },
        ));

        $triples = [];

        foreach ($slugs as $slug) {
            $model = ntdst_data()->get($slug);
            $fields = $model->getSchema();

            $reflection = new \ReflectionClass($model);
            $prefixMethod = $reflection->getMethod('prefixMetaKey');
            $prefixMethod->setAccessible(true);

            foreach (array_keys($fields) as $fieldName) {
                $metaKey = $prefixMethod->invoke($model, $fieldName);
                $triples[] = "{$slug}.{$fieldName}={$metaKey}";
            }
        }

        return $triples;
    }
}
