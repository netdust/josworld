<?php

declare(strict_types=1);

namespace NtdstTests\Unit;

/**
 * Structural contract test for the josworld-core.php boot block: every
 * service resolved via ntdst_get() needs a matching require_once (a
 * missing one fatals on a real request while the unit suite stays green,
 * since each unit test requires its own service file), and admin-only
 * services must be resolved only inside the is_admin() branch.
 *
 * Fixtures first, then the real file: the checker is proven behavioral on
 * fixtures that each violate one invariant — asserting on the real file
 * alone would pass vacuously whenever it has no services wired.
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
        // CaseService is resolved but never required — the "class not
        // found" fatal-on-request failure mode.
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
        // A commented-out require_once must not satisfy a live
        // ntdst_get(): a naive text scan sees the commented line as real
        // and marks the service pre-satisfied.
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
     * Scan boot-block source text for the two invariants. Regex-based by
     * design: the boot block is a flat line-oriented list by convention,
     * so no parser dependency is needed.
     *
     * @return array{missing_require: list<string>, admin_outside_branch: list<string>}
     */
    private function find_boot_block_violations(string $source): array
    {
        // Strip comments FIRST so a commented-out require_once/ntdst_get()
        // can never be mistaken for a live one. One scanner handles
        // fixtures AND the real file; the real file is never special-cased.
        $source = $this->strip_php_comments($source);

        // Basename as identifier — convention is one class per file,
        // filename === class name.
        preg_match_all('/require_once\s+\$dir\s*\.\s*[\'"]([^\'"]+)[\'"]/', $source, $requireMatches);
        $requiredBasenames = [];
        foreach ($requireMatches[1] as $path) {
            $requiredBasenames[basename($path, '.php')] = true;
        }

        // Classify each ntdst_get() as inside/outside the is_admin()
        // branch via brace-depth-tracked byte spans.
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
     * Strip comments via PHP's own tokenizer, replacing each with matching
     * newlines so downstream byte offsets stay aligned. token_get_all()
     * needs a `<?php` wrapper; its T_OPEN_TAG is skipped on the way out.
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
