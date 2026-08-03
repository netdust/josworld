<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

/**
 * Structural contract test for the josworld-core.php boot block (T02).
 *
 * THE CONTRACT (DAAN-documented failure, daan-core.php:348-358): every
 * service class resolved via ntdst_get() must have a matching require_once
 * of its file ABOVE the resolution block. Without it, the class is
 * undefined at request time and the site FATALS with "class not found" on
 * a real request — while the unit suite stays green, because each unit
 * test require_once's its own service file directly and therefore can
 * never observe a missing line in the boot block.
 *
 * A SECOND invariant is asserted in the same pass: admin-only services must
 * be resolved only inside the `is_admin()` branch, never in the
 * unconditional list above it — an admin service booted unconditionally
 * would load its admin-only hooks (metaboxes, admin columns, JS enqueues)
 * on every front-end request too.
 *
 * WHY a fixture, not just the real file: today's real josworld-core.php
 * boot block has zero services wired (they land in T04-T09), so asserting
 * directly against it would pass vacuously — it would keep "passing" even
 * if the checker itself were broken. Each test case below runs the SAME
 * checker used on the real file against small fixture strings that
 * deliberately violate one invariant at a time, proving the checker is
 * behavioral (goes RED on a real violation) before trusting it on the
 * file that matters. The final test runs the checker against the real,
 * live josworld-core.php to lock in the invariant as services are added.
 */
final class JosworldCoreBootBlockTest extends TestCase
{
    private const REAL_BOOT_FILE = __DIR__ . '/../../app/content/mu-plugins/josworld-core/josworld-core.php';

    public function test_every_resolved_service_has_a_matching_require_once(): void
    {
        $fixture = <<<'PHP'
            $dir = __DIR__ . '/services';
            require_once $dir . '/content/CaseService.php';
            require_once $dir . '/admin/AdminUIService.php';

            add_action('after_setup_theme', function (): void {
                ntdst_get(\josworld\services\content\CaseService::class);

                if (is_admin()) {
                    ntdst_get(\josworld\services\admin\AdminUIService::class);
                }
            }, 5);
            PHP;

        $violations = $this->find_boot_block_violations($fixture);

        $this->assertSame(
            [],
            $violations['missing_require'],
            'a fully required boot block must report no missing requires',
        );
    }

    public function test_a_resolved_service_missing_its_require_once_is_flagged(): void
    {
        // The exact DAAN failure mode: CaseService is resolved but never
        // required — would fatal "class not found" on a real request while
        // this fixture, read purely as text, has no way to execute and
        // therefore no way to fail except through the checker's own logic.
        $fixture = <<<'PHP'
            $dir = __DIR__ . '/services';
            require_once $dir . '/admin/AdminUIService.php';

            add_action('after_setup_theme', function (): void {
                ntdst_get(\josworld\services\content\CaseService::class);

                if (is_admin()) {
                    ntdst_get(\josworld\services\admin\AdminUIService::class);
                }
            }, 5);
            PHP;

        $violations = $this->find_boot_block_violations($fixture);

        $this->assertContains(
            '\\josworld\\services\\content\\CaseService',
            $violations['missing_require'],
            'a service resolved without a matching require_once must be flagged',
        );
    }

    public function test_admin_only_service_resolved_outside_is_admin_branch_is_flagged(): void
    {
        // AdminUIService required correctly, but resolved in the
        // unconditional list instead of inside is_admin() — it would then
        // boot its admin-only hooks on every front-end request too.
        $fixture = <<<'PHP'
            $dir = __DIR__ . '/services';
            require_once $dir . '/admin/AdminUIService.php';

            add_action('after_setup_theme', function (): void {
                ntdst_get(\josworld\services\admin\AdminUIService::class);

                if (is_admin()) {
                }
            }, 5);
            PHP;

        $violations = $this->find_boot_block_violations($fixture);

        $this->assertContains(
            '\\josworld\\services\\admin\\AdminUIService',
            $violations['admin_outside_branch'],
            'an admin/-namespaced service resolved outside is_admin() must be flagged',
        );
    }

    public function test_admin_only_service_resolved_inside_is_admin_branch_is_not_flagged(): void
    {
        $fixture = <<<'PHP'
            $dir = __DIR__ . '/services';
            require_once $dir . '/admin/AdminUIService.php';

            add_action('after_setup_theme', function (): void {
                if (is_admin()) {
                    ntdst_get(\josworld\services\admin\AdminUIService::class);
                }
            }, 5);
            PHP;

        $violations = $this->find_boot_block_violations($fixture);

        $this->assertSame([], $violations['admin_outside_branch']);
        $this->assertSame([], $violations['missing_require']);
    }

    public function test_a_commented_out_require_once_does_not_satisfy_a_live_resolve(): void
    {
        // Controller-found regression (mutation test): a scaffolding line
        // like `// require_once $dir . '/content/CaseService.php';` — added
        // as documentation for a not-yet-existing T04-T09 service — must
        // NOT count as satisfying the require_once for that service once
        // its ntdst_get() call goes live and stays commented out by
        // oversight. A naive text scan sees the commented require_once as
        // real and marks the service pre-satisfied for every future task
        // that ever adds it, which is the exact DAAN failure mode this
        // checker exists to catch, silently defeated by its own scaffolding
        // convention.
        $fixture = <<<'PHP'
            $dir = __DIR__ . '/services';
            // require_once $dir . '/content/CaseService.php';

            add_action('after_setup_theme', function (): void {
                ntdst_get(\josworld\services\content\CaseService::class);
            }, 5);
            PHP;

        $violations = $this->find_boot_block_violations($fixture);

        $this->assertContains(
            '\\josworld\\services\\content\\CaseService',
            $violations['missing_require'],
            'a commented-out require_once must not satisfy a live ntdst_get() resolve',
        );
    }

    public function test_the_real_boot_block_has_no_violations(): void
    {
        $this->assertFileExists(self::REAL_BOOT_FILE, 'josworld-core.php must exist');
        $source = (string) file_get_contents(self::REAL_BOOT_FILE);

        $violations = $this->find_boot_block_violations($source);

        $this->assertSame(
            [],
            $violations['missing_require'],
            'every ntdst_get()-resolved service in the real boot block must have a matching require_once',
        );
        $this->assertSame(
            [],
            $violations['admin_outside_branch'],
            'every admin/-namespaced service in the real boot block must be resolved inside is_admin()',
        );
    }

    /**
     * Scan boot-block source text for the two T02 invariants.
     *
     * Deliberately regex-based, not a full PHP parser: the boot block is a
     * flat, line-oriented list by convention (one require_once / ntdst_get
     * per line), and a lightweight scanner is enough to catch the DAAN
     * failure mode without taking a parser dependency.
     *
     * @return array{missing_require: list<string>, admin_outside_branch: list<string>}
     */
    private function find_boot_block_violations(string $source): array
    {
        // Strip comments FIRST so a commented-out require_once/ntdst_get()
        // line — e.g. the scaffolding `// require_once $dir . '/content/
        // CaseService.php';` this file itself ships for not-yet-existing
        // T04-T09 services — can never be mistaken for a live one. Without
        // this, a commented require_once "pre-satisfies" the invariant for
        // any service whose ntdst_get() later goes live with the require
        // left commented by oversight — the exact DAAN failure mode this
        // checker exists to catch, defeated by its own documentation
        // convention. One scanner handles fixtures AND the real file; the
        // real file is never special-cased.
        $source = $this->strip_php_comments($source);

        // Collect every required file's basename (without extension) as a
        // cheap-but-sufficient identifier — the convention is one class per
        // file, filename === class name.
        preg_match_all('/require_once\s+\$dir\s*\.\s*[\'"]([^\'"]+)[\'"]/', $source, $requireMatches);
        $requiredBasenames = [];
        foreach ($requireMatches[1] as $path) {
            $requiredBasenames[basename($path, '.php')] = true;
        }

        // Find the is_admin() branch boundaries so each ntdst_get() call
        // can be classified as inside or outside it. Boot blocks nest a
        // single `if (is_admin()) { ... }` block; track brace depth from
        // the branch's opening `{` to its matching close.
        $adminBranchSpans = $this->find_is_admin_branch_byte_spans($source);

        $missingRequire = [];
        $adminOutsideBranch = [];

        $ntdstGetPattern = '/ntdst_get\(\s*\\\\?([A-Za-z0-9_\\\\]+)::class\s*\)/';
        if (preg_match_all($ntdstGetPattern, $source, $getMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($getMatches[1] as [$fqcn, $offset]) {
                $fqcn = ltrim((string) $fqcn, '\\');
                $shortName = substr((string) $fqcn, (int) strrpos($fqcn, '\\') + 1);

                if (!isset($requiredBasenames[$shortName])) {
                    $missingRequire[] = '\\' . $fqcn;
                }

                $isAdminNamespaced = str_contains($fqcn, '\\admin\\');
                $isInsideAdminBranch = $this->offset_within_any_span((int) $offset, $adminBranchSpans);

                if ($isAdminNamespaced && !$isInsideAdminBranch) {
                    $adminOutsideBranch[] = '\\' . $fqcn;
                }
            }
        }

        return [
            'missing_require' => $missingRequire,
            'admin_outside_branch' => $adminOutsideBranch,
        ];
    }

    /**
     * Strip `//`, `#`, and `/* ... *\/` comments from a PHP source fragment,
     * replacing each with matching whitespace/newlines so downstream byte
     * offsets (used by the is_admin() branch span check) stay aligned with
     * the original source.
     *
     * Uses PHP's own tokenizer rather than a hand-rolled regex — the
     * fixtures/real file are plain top-level statement lists with no
     * enclosing `<?php` tag, so token_get_all() is fed a wrapped copy and
     * the wrapper's own T_OPEN_TAG token is skipped on the way back out.
     */
    private function strip_php_comments(string $source): string
    {
        $tokens = token_get_all('<?php ' . $source);
        $result = '';
        $skippedOpenTag = false;

        foreach ($tokens as $token) {
            if (is_string($token)) {
                $result .= $token;
                continue;
            }

            [$id, $text] = $token;

            if (!$skippedOpenTag && $id === T_OPEN_TAG) {
                $skippedOpenTag = true;
                continue;
            }

            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                // Preserve newlines inside the stripped comment so line-based
                // byte offsets used elsewhere in this file stay meaningful.
                $result .= str_repeat("\n", substr_count($text, "\n"));
                continue;
            }

            $result .= $text;
        }

        return $result;
    }

    /**
     * @return list<array{0: int, 1: int}> byte offset spans [start, end)
     */
    private function find_is_admin_branch_byte_spans(string $source): array
    {
        $spans = [];
        $searchOffset = 0;

        while (($ifPos = strpos($source, 'is_admin()', $searchOffset)) !== false) {
            $bracePos = strpos($source, '{', $ifPos);
            if ($bracePos === false) {
                break;
            }

            $depth = 0;
            $pos = $bracePos;
            $len = strlen($source);
            $branchStart = $bracePos + 1;
            $branchEnd = $len;

            for (; $pos < $len; $pos++) {
                if ($source[$pos] === '{') {
                    $depth++;
                } elseif ($source[$pos] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $branchEnd = $pos;
                        break;
                    }
                }
            }

            $spans[] = [$branchStart, $branchEnd];
            $searchOffset = $bracePos + 1;
        }

        return $spans;
    }

    /**
     * @param list<array{0: int, 1: int}> $spans
     */
    private function offset_within_any_span(int $offset, array $spans): bool
    {
        foreach ($spans as [$start, $end]) {
            if ($offset >= $start && $offset < $end) {
                return true;
            }
        }

        return false;
    }
}
