<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use josworld\services\content\CaseService;
use josworld\services\content\TeamService;
use josworld\services\content\ToolService;
use josworld\services\content\VacatureService;
use WP_UnitTestCase;

/**
 * The meta-key table in docs/content-model.md is mechanically tied to the
 * live model registry: the block between the META-KEY-TABLE markers must
 * equal the {model, field, meta_key} triple set the real registry reports,
 * scoped by the same boundary as ModelConventionsTest (registered through
 * ntdst_data() AND carrying JOSWORLD_CORE_META_PREFIX) — so `dienst`, a
 * taxonomy, is excluded on both sides and a future fifth model is swept in
 * with no edit to this file.
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
     * The doc carries meta keys in TWO places: the marker block and the
     * human-readable per-model tables. Pinning the tables to the marker
     * block (itself diffed against the registry) transitively pins them to
     * the code.
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
     * The parser must throw on a malformed row rather than silently skip it
     * — a swallowed row could mask real drift.
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
     * Parses `| model | field | meta_key |` rows into "model.field=meta_key"
     * triples; skips header rows, throws on any data row without exactly
     * 3 cells.
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
     * josworld_owned_model_slugs(); `dienst` fails registry membership,
     * not a special case here.
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
