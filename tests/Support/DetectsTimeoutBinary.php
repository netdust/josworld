<?php

declare(strict_types=1);

namespace NtdstTests\Support;

/**
 * Shared `timeout(1)` detection for tests that shell out to a subprocess.
 *
 * Two suites spawn real subprocesses to prove things an in-process test
 * cannot — `IntegrationBootstrapGuardTest` (the dev-DB allow-list guard) and
 * `JosworldCoreBootTest` (the framework-absent degradation path, which needs
 * `ntdst_get` genuinely undefined, impossible in-process once ntdst-core is
 * loaded). Both wrap the child in GNU `timeout` so a hang fails the suite
 * instead of blocking CI forever.
 *
 * GNU `timeout` is not on stock macOS, so its absence must degrade to running
 * the child unwrapped rather than failing. That detection was copy-pasted
 * verbatim into both files; extracted here at the josworld-core Cluster A
 * review gate (SIMP-1) so there is one definition to correct.
 *
 * Cached per-process: `command -v` costs a fork, and the answer cannot change
 * during a run.
 */
trait DetectsTimeoutBinary
{
    private static ?string $timeoutPrefixCache = null;

    /**
     * @return string `'timeout 20 '` when GNU timeout exists, `''` otherwise.
     */
    private function timeout_prefix(): string
    {
        if (self::$timeoutPrefixCache === null) {
            exec('command -v timeout >/dev/null 2>&1', $unusedOutput, $exit);
            self::$timeoutPrefixCache = $exit === 0 ? 'timeout 20 ' : '';
        }

        return self::$timeoutPrefixCache;
    }
}
