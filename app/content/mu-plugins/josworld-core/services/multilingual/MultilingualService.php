<?php

/**
 * Multilingual Service — signature shell (T09, authored by the test-author,
 * NOT the implementer).
 *
 * THIS FILE IS A CONTRACT SHELL, NOT AN IMPLEMENTATION. It exists only so
 * the T09 RED test fails for a BEHAVIORAL reason (a real contract mismatch)
 * rather than "class not found" — per the test-author/implementer split
 * (tasks.md T09: `Test-author: split`). The implementer fills init() (and
 * whatever private helpers it needs) to satisfy MultilingualServiceTest and
 * MultilingualServiceAbsenceTest WITHOUT changing this class's public
 * shape unless a genuine defect in the shell is found (escalate, don't
 * silently redesign).
 *
 * CONTRACT THIS SHELL MUST HONOUR (tasks.md T09 + plan.md FR-10/SC-3/SC-4):
 *  - Constructor takes NTDST_Data_Manager (same shape as every sibling
 *    content service — CaseService, TeamService, VacatureService,
 *    ToolService — so the boot block in josworld-core.php resolves it via
 *    ntdst_get() identically to the rest, no special-casing).
 *  - init() is where the implementer wires Polylang integration: language
 *    configuration (NL default + EN + FR) and declaring the four post
 *    types (`case`, `team`, `vacature`, `tool`) plus the `dienst` taxonomy
 *    translatable.
 *  - EVERY call into a `pll_*` function, and every `add_filter('pll_get_...')`
 *    registration, MUST be guarded by `function_exists('pll_...')` (or
 *    equivalent) — Polylang is WP-CLI-installed, untracked in git
 *    (.gitignore:128-129), NOT wired through composer.json, and is not
 *    installed in this environment today. The site must not fatal when it
 *    is absent (SC-4) — this is the load-bearing half of the contract, and
 *    is the reason this task is split from its own implementer.
 *
 * A service file by nature both declares a symbol (the class below) and
 * executes a side effect (the ABSPATH guard) — exempt from the PSR-1
 * side-effects rule, same posture as CaseService.php / AdminUIService.php.
 * phpcs:disable PSR1.Files.SideEffects
 */

declare(strict_types=1);

namespace josworld\services\multilingual;

use NTDST_Data_Manager;

defined('ABSPATH') || exit;

final class MultilingualService
{
    public function __construct(private readonly NTDST_Data_Manager $dataManager)
    {
        $this->init();
    }

    /**
     * SENTINEL — deliberately unimplemented. The implementer replaces this
     * body with real Polylang wiring (language config + translatable-type
     * declaration), guarded per the class docblock's contract. Do not
     * relax, remove, or work around this sentinel from a test — a test
     * that needs it gone is testing the wrong layer.
     */
    private function init(): void
    {
        // Sentinel-only reference to $dataManager (no real logic): keeps
        // the property genuinely read, matching every sibling service's
        // constructor-injected shape, until the implementer's real init()
        // reads it for its actual purpose (declaring translatable types).
        // This is an exception message, not rendered output — the standard
        // project idiom for this well-known WPCS false-positive (see e.g.
        // app/wp/wp-includes/ai-client/class-wp-ai-client-prompt-builder.php:459-460).
        throw new \RuntimeException(
            sprintf(
                'MultilingualService::init() is not implemented (received %s) — this is the '
                . 'T09 test-author signature shell (tasks.md: Test-author: split). The '
                . 'implementer must replace this sentinel with real, guarded Polylang wiring.',
                $this->dataManager::class, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            ),
        );
    }
}
