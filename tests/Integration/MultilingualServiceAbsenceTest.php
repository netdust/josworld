<?php

declare(strict_types=1);

namespace NtdstTests\Integration;

use NtdstTests\Support\DetectsTimeoutBinary;
use WP_UnitTestCase;

/**
 * Integration tier: proves the T09 CONTRACT's ABSENCE half (SC-4) — the
 * LOAD-BEARING half of this task, and the reason it is the only `split`
 * task in the whole plan (tasks.md: "the failure mode is a fatal on a live
 * site when the plugin is absent, so the denial/absence path is authored
 * independently of the code that satisfies it").
 *
 * WHY A NAIVE VERSION OF THIS TEST WOULD BE VACUOUS: Polylang is not
 * installed in ANY environment this suite runs in today (ground-truthed —
 * `app/content/plugins/` contains only `index.php`; composer.json has no
 * wpackagist/plugin-installer wiring; plugins are WP-CLI-installed and
 * untracked per .gitignore:128-129). "The site loads with no fatal when
 * Polylang is absent" is therefore the CURRENT, DEFAULT state of every test
 * run — asserting it while MultilingualService does not exist yet would
 * pass for a reason that has nothing to do with the contract. What makes
 * this test real is exercising it AFTER MultilingualService is required
 * and instantiated (so a fatal it introduces has somewhere to happen), and
 * pairing the behavioral proof with a STATIC scan of the shipped source for
 * the actual regression class named in the contract: an unguarded pll_*
 * call.
 *
 * TWO PROOF TECHNIQUES, TWO DIFFERENT REASONS (a single technique cannot
 * honestly cover both):
 *
 * 1) BEHAVIORAL, subprocess-based (real WordPress, no fatal, no wp_die,
 *    exit 0): mirrors this project's own established pattern
 *    (JosworldCoreBootTest::boot_the_real_plugin_in_a_subprocess_without_ntdst_core(),
 *    tests/Unit/IntegrationBootstrapGuardTest.php) — `pll_*` functions ARE
 *    genuinely undefined in every environment this suite runs in, but this
 *    in-process test class cannot prove that safely: WP_UnitTestCase shares
 *    one long-lived PHP process across every test in the whole integration
 *    suite (confirmed by MultilingualServiceTest.php in this same
 *    directory, which DOES define pll_languages_list() etc. for its own
 *    active-path tests — once PHP declares a function it cannot become
 *    undefined again for the rest of the process, and PHPUnit does not
 *    guarantee file/test execution order). A real, separate `php`
 *    subprocess that requires the REAL, unmodified service file with NO
 *    pll_* shim at all is the only way to observe "genuinely absent" rather
 *    than "not yet defined in this file, so far".
 *
 * 2) STATIC, source-scan-based, permanent and free at every future edit:
 *    the actual regression this contract exists to catch is a future
 *    edit adding `pll_set_post_types(...)` or similar OUTSIDE a
 *    `function_exists('pll_...')` guard. A behavioral test only catches
 *    that regression if the exact code path it exercises happens to reach
 *    the new unguarded line; a static scan of every `pll_` call in the
 *    file catches it regardless of which code path introduced it. This is
 *    the SAME rationale T10's "static scan of the plugin directory finds
 *    zero direct register_post_type/register_taxonomy calls" clause uses
 *    for its architecture-invariant half.
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
     * THE STATIC HALF (see class docblock, technique 2): scans the real,
     * shipped MultilingualService.php source for any `pll_` call that is
     * NOT guarded by a preceding `function_exists('pll_` check in the same
     * file. Deliberately regex/token-based, not a full data-flow analysis —
     * matches T10's own "static scan of the plugin directory" precedent and
     * JosworldCoreBootBlockTest's precedent of a lightweight, behaviorally
     * self-proving scanner (proven against BOTH a violating fixture and a
     * clean fixture before being trusted against the real file, immediately
     * below and in the two tests that follow).
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
     * Mutation proof (violating case): a fixture calling `pll_set_post_types()`
     * with NO guard anywhere in the file must be flagged. Proves the scanner
     * is behavioral before the real-file test above is trusted.
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
     * Mutation proof (clean case): the SAME call, this time behind a
     * `function_exists('pll_set_post_types')` guard, must NOT be flagged.
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
                // Find the string literal argument, e.g.
                // function_exists('pll_set_post_types').
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
                // Only count it as a CALL if the next non-whitespace token
                // is an opening paren.
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
     * Minimal, real-hook-faithful shim: enough of NTDST_Data_Manager +
     * WordPress's registration surface for the REAL, unmodified content
     * services AND the REAL, unmodified MultilingualService to run
     * end-to-end with `pll_*` genuinely absent from the symbol table — not
     * a reimplementation of any of their logic.
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

            class NTDST_Theme {
                public function taxonomy(string \$taxonomy, \$objectType, array \$args = []): void {}
            }
            \$GLOBALS['__ntdst_theme'] = new NTDST_Theme();
            function ntdst_get(string \$class) {
                if (\$class === NTDST_Theme::class) {
                    return \$GLOBALS['__ntdst_theme'];
                }
                throw new RuntimeException("unexpected ntdst_get(\$class) in harness");
            }

            final class NTDST_Data_Manager {
                public function register(string \$name, array \$config = []): void {
                    register_post_type(\$name, \$config);
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
