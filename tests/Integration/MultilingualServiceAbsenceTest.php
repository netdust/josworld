<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use NtdstTests\Support\DetectsTimeoutBinary;
use WP_UnitTestCase;

/**
 * MultilingualService must not fatal when Polylang is absent — the one
 * place this service can take down a live site. Two techniques:
 *  1. behavioral, in a subprocess with no pll_* shim: pll_* cannot be
 *     honestly absent in-process — a sibling test file defines pll_*
 *     stubs, and a declared function never becomes undefined again in
 *     the shared PHP process;
 *  2. static, a scan of the shipped source for any pll_* call outside a
 *     function_exists() guard — catches the regression on every code
 *     path, not only the one the behavioral test happens to exercise.
 */
final class MultilingualServiceAbsenceTest extends WP_UnitTestCase
{
    use DetectsTimeoutBinary;

    private const SERVICE_FILE = __DIR__
        . '/../../app/content/mu-plugins/josworld-core/services/multilingual/MultilingualService.php';

    public function test_site_loads_with_no_fatal_when_polylang_is_absent(): void
    {
        $result = $this->instantiate_multilingual_service_in_a_subprocess_without_polylang();

        $this->assertSame(
            0,
            $result['exit'],
            "subprocess must exit cleanly when Polylang (pll_*) is undefined; stderr:\n{$result['stderr']}",
        );
        $this->assertStringNotContainsString(
            'Fatal error',
            $result['stderr'],
            'MultilingualService must not fatal when Polylang is absent (SC-4) — this is the '
            . 'one place T09 can fatal a live site',
        );
        $this->assertStringContainsString(
            'NO_FATAL_REACHED',
            $result['stdout'],
            'the subprocess must reach the end of its script — a fatal earlier would truncate stdout '
            . 'before this marker is written',
        );
    }

    public function test_the_four_post_types_stay_registered_when_polylang_is_absent(): void
    {
        $result = $this->instantiate_multilingual_service_in_a_subprocess_without_polylang();

        $this->assertSame(
            0,
            $result['exit'],
            'precondition: the subprocess must exit cleanly to trust its '
            . "REGISTERED_POST_TYPES report; stderr:\n{$result['stderr']}",
        );
        $this->assertSame(
            '4',
            trim($result['registeredPostTypes']),
            'all four post types (case, team, vacature, tool) must stay registered when '
            . 'MultilingualService degrades due to Polylang being absent — degrading the '
            . 'multilingual layer must never un-register the content model underneath it',
        );
    }

    /**
     * THE STATIC HALF: every pll_* call must sit behind a
     * function_exists('pll_...') guard. The scanner is proven against a
     * violating and a clean fixture below before being trusted here.
     */
    public function test_the_real_service_file_makes_no_unguarded_pll_call(): void
    {
        $this->assertFileExists(self::SERVICE_FILE, 'MultilingualService.php must exist');
        $source = (string) file_get_contents(self::SERVICE_FILE);

        $unguarded = $this->find_unguarded_pll_calls($source);

        $this->assertSame(
            [],
            $unguarded,
            'every pll_* call in MultilingualService.php must be reached only from inside a '
            . "function_exists('pll_...') guard (or equivalent) — SC-4: an unguarded call "
            . 'fatals the whole site the moment Polylang is deactivated',
        );
    }

    /**
     * An unguarded pll_* call must be flagged.
     */
    public function test_an_unguarded_pll_call_in_a_fixture_is_flagged(): void
    {
        $fixture = <<<'PHP'
            <?php
            function bad(): void {
                pll_set_post_types(['case', 'team']);
            }
            PHP;

        $unguarded = $this->find_unguarded_pll_calls($fixture);

        $this->assertContains('pll_set_post_types', $unguarded);
    }

    /**
     * The same call behind a function_exists() guard must NOT be flagged.
     */
    public function test_a_guarded_pll_call_in_a_fixture_is_not_flagged(): void
    {
        $fixture = <<<'PHP'
            <?php
            function good(): void {
                if (function_exists('pll_set_post_types')) {
                    pll_set_post_types(['case', 'team']);
                }
            }
            PHP;

        $unguarded = $this->find_unguarded_pll_calls($fixture);

        $this->assertSame([], $unguarded);
    }

    /**
     * @return list<string> function names called without a preceding
     *                       function_exists() guard for that same name
     */
    private function find_unguarded_pll_calls(string $source): array
    {
        $tokens = token_get_all($source);
        $guarded = [];
        $unguarded = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            [$id, $text] = $token;

            if ($id === T_STRING && $text === 'function_exists') {
                // Record the guarded name from the string-literal argument.
                for ($lookahead = $index + 1; $lookahead < $index + 6 && isset($tokens[$lookahead]); $lookahead++) {
                    $candidate = $tokens[$lookahead];
                    if (is_array($candidate) && $candidate[0] === T_CONSTANT_ENCAPSED_STRING) {
                        $guarded[trim($candidate[1], "'\"")] = true;
                        break;
                    }
                }

                continue;
            }

            if ($id === T_STRING && str_starts_with($text, 'pll_')) {
                // A call: next non-whitespace token is `(`.
                $next = $tokens[$index + 1] ?? null;
                $isCall = (is_string($next) && $next === '(')
                    || (is_array($next) && $next[0] === T_WHITESPACE
                        && ($tokens[$index + 2] ?? null) === '(');

                if ($isCall && ! isset($guarded[$text])) {
                    $unguarded[] = $text;
                }
            }
        }

        return array_values(array_unique($unguarded));
    }

    /**
     * @return array{exit: int, stdout: string, stderr: string, registeredPostTypes: string}
     */
    private function instantiate_multilingual_service_in_a_subprocess_without_polylang(): array
    {
        $harness = tempnam(sys_get_temp_dir(), 'jw-multilingual-absent-');
        $stdoutFile = tempnam(sys_get_temp_dir(), 'jw-multilingual-absent-out-');
        $stderrFile = tempnam(sys_get_temp_dir(), 'jw-multilingual-absent-err-');
        $this->assertNotFalse($harness);
        $this->assertNotFalse($stdoutFile);
        $this->assertNotFalse($stderrFile);

        $caseFile = dirname(__DIR__, 2)
            . '/app/content/mu-plugins/josworld-core/services/content/CaseService.php';
        $teamFile = dirname(__DIR__, 2)
            . '/app/content/mu-plugins/josworld-core/services/content/TeamService.php';
        $vacatureFile = dirname(__DIR__, 2)
            . '/app/content/mu-plugins/josworld-core/services/content/VacatureService.php';
        $toolFile = dirname(__DIR__, 2)
            . '/app/content/mu-plugins/josworld-core/services/content/ToolService.php';
        $this->assertFileExists($caseFile);
        $this->assertFileExists($teamFile);
        $this->assertFileExists($vacatureFile);
        $this->assertFileExists($toolFile);
        $this->assertFileExists(
            self::SERVICE_FILE,
            'the real MultilingualService.php must exist for this subprocess to require it unmodified',
        );

        file_put_contents(
            $harness,
            $this->degraded_boot_harness_source($caseFile, $teamFile, $vacatureFile, $toolFile, self::SERVICE_FILE),
        );

        $command = sprintf(
            '%sphp %s > %s 2> %s',
            $this->timeout_prefix(),
            escapeshellarg($harness),
            escapeshellarg($stdoutFile),
            escapeshellarg($stderrFile),
        );

        exec($command, $unusedOutput, $exit);

        $stdout = (string) file_get_contents($stdoutFile);
        $stderr = (string) file_get_contents($stderrFile);
        unlink($harness);
        unlink($stdoutFile);
        unlink($stderrFile);

        preg_match('/REGISTERED_POST_TYPES=(\d+)/', $stdout, $matches);

        return [
            'exit' => $exit,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'registeredPostTypes' => $matches[1] ?? '',
        ];
    }

    /**
     * Minimal registration shim — the REAL, unmodified services run with
     * pll_* genuinely absent from the symbol table.
     */
    private function degraded_boot_harness_source(
        string $caseFile,
        string $teamFile,
        string $vacatureFile,
        string $toolFile,
        string $multilingualFile,
    ): string {
        $caseExport = var_export($caseFile, true);
        $teamExport = var_export($teamFile, true);
        $vacatureExport = var_export($vacatureFile, true);
        $toolExport = var_export($toolFile, true);
        $multilingualExport = var_export($multilingualFile, true);

        return <<<PHP
            <?php
            define('ABSPATH', '/tmp/jw-multilingual-absent-fake/');
            define('JOSWORLD_CORE_META_PREFIX', '_jw_');
            define('JOSWORLD_CORE_TEXTDOMAIN', 'josworld-core');

            function __(string \$text, string \$domain = 'default'): string { return \$text; }
            function _x(string \$text, string \$context, string \$domain = 'default'): string { return \$text; }

            \$GLOBALS['__registered_post_types'] = [];
            function register_post_type(string \$postType, array \$args = []): void {
                \$GLOBALS['__registered_post_types'][] = \$postType;
            }
            function register_taxonomy(string \$taxonomy, \$objectType, array \$args = []): void {}
            function add_filter(string \$hook, \$cb, int \$priority = 10, int \$args = 1): bool {
                return true;
            }
            function add_action(string \$hook, \$cb, int \$priority = 10, int \$args = 1): bool {
                return true;
            }

            // TeamService::closeSingleView() registers its redirect route at
            // construction via ntdst_pages()->single(). Registration is all
            // that happens at boot — the callback (and the redirect inside
            // it) only fires on a matched front-end request, which this
            // harness never makes, so single() is the whole surface needed.
            class NTDST_Pages {
                public function single(string \$postType, callable \$callback): self { return \$this; }
            }
            function ntdst_pages(): NTDST_Pages {
                static \$pages = null;
                return \$pages ??= new NTDST_Pages();
            }

            final class NTDST_Data_Manager {
                public function register(string \$name, array \$config = []): void {
                    register_post_type(\$name, \$config);
                }
                public function isRegistered(string \$name): bool {
                    return in_array(\$name, \$GLOBALS['__registered_post_types'], true);
                }
            }

            require {$caseExport};
            require {$teamExport};
            require {$vacatureExport};
            require {$toolExport};
            require {$multilingualExport};

            \$dataManager = new NTDST_Data_Manager();
            new \\josworld\\services\\content\\CaseService(\$dataManager);
            new \\josworld\\services\\content\\TeamService(\$dataManager);
            new \\josworld\\services\\content\\VacatureService(\$dataManager);
            new \\josworld\\services\\content\\ToolService(\$dataManager);

            // pll_* is genuinely undefined here — no shim, no stub. This is
            // the real "Polylang not active" condition the site is in today.
            new \\josworld\\services\\multilingual\\MultilingualService(\$dataManager);

            fwrite(STDOUT, "NO_FATAL_REACHED\\n");
            fwrite(STDOUT, 'REGISTERED_POST_TYPES=' . count(\$GLOBALS['__registered_post_types']) . "\\n");
            PHP;
    }
}
