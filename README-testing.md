# Testing & quality gates — reference card

For a developer who just cloned a project scaffolded from this template.
One rule above all: **the gate's exit code is the truth.** Every check an agent
or CI loop runs is a tier of `composer gate` — no check runs anywhere that
`gate` doesn't run.

## 1. The gates

`composer gate` (mirror: `ddev gate`) runs all nine tiers cheapest-first,
fail-fast, from the **host**: the first red tier stops the run, names itself on
stderr, and propagates its real exit code (`bin/gate.sh`).

| Tier | Command | DDEV mirror | What it checks | Where it runs | Typical time |
|---|---|---|---|---|---|
| 1 | `composer lint` | `ddev lint` | phpcs: PSR-12 + WordPress Security/DB sniffs + PHP-compat (8.3) — currently scoped to `tests/` (see phpcs.xml); widen as mu-plugin/theme debt is paid | either | < 1 s |
| 2 | `composer lint:js` | `ddev lint-js` | ESLint + Stylelint, per theme package | either | ~1 s |
| 3 | `composer analyse` | `ddev analyse` | PHPStan static analysis | **host only** — the phpstan cache is context-coupled; a container run poisons the mounted cache for the host-orchestrated gate | ~1 s |
| 4 | `composer audit:deps` | `ddev audit` | `composer audit` + `npm audit --audit-level=high` (theme) | either | ~3 s |
| 5 | `composer test:unit` | `ddev test-unit` | PHP unit tier: Brain Monkey, no WordPress, no DB | either | ~1 s |
| 6 | `composer test:js` | `ddev test-js` | Vitest: theme JS unit tests | either | ~1.5 s |
| 7 | `composer build:check` | `ddev build-check` | Vite production build resolves | either | ~1 s |
| 8 | `ddev composer test:int` | `ddev test-int` | Real WordPress against the dedicated `wptests` DB | **in-container only** | ~1.5 s |
| 9 | `composer test:e2e` | `ddev test-e2e` | Playwright against the live DDEV URL, wp-cli-seeded fixtures (after a `tsc --noEmit` preflight) | **host only** | ~7 s |
| all | `composer gate` | `ddev gate` | tiers 1–9 in order | host (hops into the container for tier 8) | ~15 s warm |

- Tier 8 is in-container because `tests/wp-tests-config.php` resolves DB host
  `db`, which only exists on the container network; the gate hops in via
  `ddev composer test:int` automatically.
- Tier 9 is host-side because the Playwright browsers live on the host;
  `bin/e2e.sh` derives the site URL from DDEV — never hardcode it.
- `composer format` (Pint) is **deliberately outside the gate**: formatting is
  editor-side. Run `composer format` to check, `composer format:fix` to apply;
  a red format never blocks a green gate.
- "Either" assumes matching platforms for the JS tiers: `node_modules` are
  host-installed with platform-native binaries, and the in-container mirrors
  reuse them — true on Linux/WSL; macOS hosts should run those tiers host-side.

## 2. How to add a test, per tier

**PHP unit** — `tests/Unit/YourThingTest.php`, namespace `NtdstTests\Unit`,
extend the tier's `TestCase` (it wires Brain Monkey + Mockery per test). Stub
WordPress functions with `Functions\when('esc_html')->returnArg()` or verify
calls with `Functions\expect(...)`. No WordPress loads, no DB is touched.
Run: `composer test:unit`.

**PHP integration** — `tests/Integration/YourThingTest.php`, namespace
`NtdstTests\Integration`, extend `WP_UnitTestCase`. Real WordPress (from
`app/wp`) installs itself into the dedicated `wptests` database on each run —
**never the dev DB**: the bootstrap enforces an allow-list and dies before
install unless `DB_NAME` is `wptests` (or a DB you explicitly name in the
`WP_TESTS_ALLOW_DB` env var — the escape hatch, for when you knowingly point
at another throwaway DB). Run: `ddev composer test:int`.

**JS unit** — `app/content/themes/<theme>/src/your-module.test.js`, next to the
code, Vitest. Follow the pattern in the theme's `src/main.test.js`: `vi.mock`
side-effectful imports, and stub `fetch` with a deterministic queue of
responses per endpoint (FIFO, throws on unexpected calls) so ordering can
never vary between runs. Run: `composer test:js`.

**E2E** — `tests/E2E/your-flow.spec.ts`, plain `@playwright/test`. Fixtures
(users, posts) are seeded in `bin/e2e.sh` via wp-cli **before** the suite runs
— never create fixtures through the UI. Credentials and fixture URLs arrive
via env (`E2E_PASS`, `E2E_FIXTURE_URL`); add new fixtures to `bin/e2e.sh` and
export them the same way. Note the Bedrock layout: admin is at `/wp/wp-admin`.
Run: `composer test:e2e` or `ddev test-e2e`.

## 3. Which tier does my bug belong to?

Mocked PHP logic → **unit**; hooks/CPT/DB behaviour → **integration**; theme
JS logic → **JS unit**; user-visible flows → **e2e**.

- **Pure PHP logic whose WP calls can be stubbed** → unit. Example:
  `tests/Unit/BrainMonkeyFunctionsTest.php` proves a renderer calling
  `esc_html()` is testable with zero WordPress loaded.
- **Behaviour that only exists with real WordPress** (hooks firing, CPT/meta
  persistence, queries) → integration. Example:
  `tests/Integration/DataLayerRoundTripTest.php` asserts a model `create()`
  really persists a post + prefixed meta and fires its lifecycle action.
- **Theme JavaScript logic** → JS unit. Example: the theme's
  `src/main.test.js` proves nonce caching and the bounded invalid-nonce retry
  against a mocked `fetch`.
- **A flow the user sees in the browser** → e2e. Example:
  `tests/E2E/smoke.spec.ts` logs `e2e-admin` in at `/wp/wp-login.php` and
  asserts the seeded fixture post renders at its real URL.

## 4. Known limitations & operational notes

a. **Run ONE gate at a time.** Concurrent runs collide at `test:e2e`: each run
   rotates the `e2e-admin` password to a fresh random value, so a parallel
   run's login fails mid-flight (observed: docs/gate-falsifiability.md, A1
   edge run 2). Not a defect — documented behaviour (and `test:int` shares
   one `wptests` install — observed-safe once, not guaranteed).
b. **The root `package.json` (e2e tooling) is dev-only and outside the
   `audit:js` loop** (which scans theme packages only). It currently carries
   3 high transitive advisories in the `@wordpress/e2e-test-utils-playwright`
   tree — zero runtime exposure, deliberately bypassed layer. Revisit if that
   dependency is dropped.
c. **Adopting the gate on an already-running project:** `wptests` is created
   by a post-start hook, so a project that was running before the gate layer
   arrived needs one `ddev restart` before `test:int` works.
d. **When `test:int` is red,** `ddev composer test:int` shows only the exit
   code on the host. For the failing assertion detail run:
   `ddev exec vendor/bin/phpunit -c phpunit.integration.xml`.
e. **A stopped DDEV project self-heals:** `composer gate` auto-starts the
   containers at `test:int` (adds ~18 s; observed cold run ~31 s vs ~13–15 s
   warm — docs/gate-falsifiability.md, A1 edge run 1).
f. **PHPUnit is pinned `^9.6` project-wide** — wp-phpunit's ceiling (no
   release supports PHPUnit ≥ 10). Do not bump PHPUnit without checking
   wp-phpunit first.
g. **PHP-compat sniffs run PHPCompatibility 9.3.5, which predates PHP 8** —
   functions removed in PHP 8.x are not yet flagged; adopt the v10 line when
   released.

## 5. Falsifiability

Every gate tier has a recorded red demonstration — a deliberate violation, the
non-zero exit, and the green re-run — in `docs/gate-falsifiability.md`.
If you add a gate, prove it can fail before you trust it green.
