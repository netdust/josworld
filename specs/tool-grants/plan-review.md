# Plan review: tool-grants set (baseline `grants` and josworld `tool-grants`), third pass
Reviewed-plan: 26426b93f69895104e13f7b40c518afe9aeaf3fa /home/ntdst/Sites/worktrees/baseline-grants/specs/grants/plan.md
Reviewed-plan: 73b21c50e6d73ad56cc9e40b3dab2a15f2f1533e /home/ntdst/Sites/josworld/specs/tool-grants/plan.md
Verdict: ready with fixes

I checked both blob shas against the files on disk, and both match. Every finding from the second review (`/home/ntdst/Sites/josworld/specs/tool-grants/plan-review.md`) is fixed, and each fix holds in the source.

There are no Blocking findings. Three new Should-fix findings remain, and each needs one line in a plan:
- **S-1** blocks the spam test.
- **S-2** makes a Test map command miss its own test.
- **S-3** leaves the one interface between the two plans undefined and untested.

## Status of the second review's findings

| Finding | Status | Evidence |
|---|---|---|
| B-1 | Holds | Review Focus 3 is now the shake-out flow form-to-link-en (josworld l.98, l.122). Spam goes through the `entryStatus()` seam, but see S-1. The Matomo test now uses stub `TrackingCode`/`AIBotTracking` instances (l.88). Store points at a temp dir (l.122) |
| S-A | Holds | `renderForGrant(...): string\|false\|null` (josworld l.129). `ToolAppService.php:17` is strict_types |
| S-B | Holds | `render` is added in Task 2 (l.131). Task 1 says "no `render` yet" (l.108) |
| S-C | Holds, with a file mismatch (S-2) | The wiring lines are in Task 1's Modify list (l.108). The test is in Task 1 Step 2. Boot block confirmed at `josworld-core.php:74,94,104,132` |
| S-D | Holds | The `endBuffers()` protected seam is overridden by the test (baseline l.172, l.175) |
| S-E | Holds | Threat 1 (baseline l.93) now matches Task 3 Behaviour (l.187) |
| S-F | Holds | josworld Interfaces (l.117) consume `LinkRoute::isGrantRequest()`, which baseline produces (l.186). See S-3 for what is still missing |
| S-G | Holds | `send(WP_Post $grant, WP_Post $content): bool` appears in both the Test map (l.129) and Task 4 (l.200) |
| N-1 | Holds | `AIBotTracking::do_ai_bot_tracking` is now in threat 3. Source confirms it on `wp_footer` at 999999 (`plugins/matomo/classes/WpMatomo/AIBotTracking.php:73`) |
| N-2 | Holds | The tool-app URL is matched by path; the host is ignored (josworld l.129) |
| N-3 | Holds | josworld l.122 |
| N-4 | Holds | The fixture uses `wp ntdst polylang translation create` instead of the editor's "+". The command exists in josworld's installed baseline (`ntdst-baseline/services/polylang/Cli/Commands.php:393-397`) |
| N-5 | Holds | "Before Task 1: composer install" (baseline l.9). The worktree still has no `vendor/` |
| N-6 | Holds, with one leftover | Fixed: the Test map filter, the base snippet, the threat numbering, and the `downloadTool()` helper. Leftover: baseline Task 1 Step 3 still runs `--filter GrantRepository`, so `GrantModelTest` is not in the per-task run (see Note) |

## Blocking

None.

## Should fix

**S-1. The `entryStatus()` seam cannot be overridden: `DownloadFormService` is `final`.**
- **Plan:** josworld threat 5 (l.76) says "read through one protected seam `entryStatus(int $insertId): string`". Task 1 Step 2 has `test_spam_entry_issues_nothing` with "the `entryStatus()` seam overridden".
- **Source:** `app/content/mu-plugins/josworld-core/services/forms/DownloadFormService.php:24` declares `final class DownloadFormService`. A test cannot subclass a final class, so the RED test for threat 5 cannot be written as planned.
- **A second problem:** the other ToolGrantForm tests call the real `entryStatus()`. In the wptests database there is no `fluentform_submissions` table (B-1 of the second review). `phpunit.integration.xml:7` sets `failOnRisky="true"`, so a wpdb error printed by that query can fail those tests.
- **Fix:** in Task 1's Modify line for `DownloadFormService.php`, add "drop `final`".
  - The real `entryStatus()` should return `''` when FluentForm's API or table is absent.
  - Alternatively, every ToolGrantForm test can use the subclass.
  - Another option is to inject the status reader as a constructor callable that the test replaces.

**S-2. The Matomo test lives in one file in the Test map and in another in Task 1.**
- **Plan:** josworld Test map (l.88) puts `test_matomo_callbacks_are_removed_on_a_grant_request` in `tests/Integration/ToolGrantAccessTest.php`, run with `--filter ToolGrantAccess`.
- Task 1 Step 2 (l.122) lists that test, but Task 1's only test file is `ToolGrantFormTest.php` (l.114). `ToolGrantAccessTest.php` is created in Task 2 (l.133).
- The executor will put the test in `ToolGrantFormTest.php`, and the row's command will then not run it.
- **Fix:** either change the row to `ToolGrantFormTest` · `--filter ToolGrantForm`, or move the test to Task 2 Step 1. The file under test, `ToolGrantsMatomo`, is created in Task 1, so the first option is the smaller change.

**S-3. `LinkRoute::isGrantRequest()` is the one name josworld consumes, but baseline never says what it means and has no test for it.**
- **Plan:** baseline Task 3 Interfaces (l.186) produce `LinkRoute::isGrantRequest(): bool` "the one name sites consume". None of the eleven tests in Task 3 Step 1 (l.189) cover it, and the LinkRoute Test map row (l.127) does not name it.
- josworld threat 3 depends on it being true by `wp_head` priority 0, and josworld's Matomo test has to make it true somehow.
- **Source:** ntdst-core dispatches routes from `get_query_var('ntdst_page')` plus `$wp->matched_rule` on `template_redirect` (`ntdst-core/core/Pages.php:206-233`). `ntdst_page` is a public query var, so `?ntdst_page=N` reaches any URL (`Pages.php:220-228`).
- The two possible implementations behave differently:
  - A flag set inside `serve()` is true only while a grant route is answering.
  - A raw query-var check can be spoofed onto any URL.
- If the executor decides this alone, the cross-repo contract is fixed by accident.
- **Fix, in baseline Task 3 Interfaces:**
  - Define it as "true from the moment `serve()` runs for a matched grant route; false otherwise, including on `?ntdst_page=` alone".
  - Add `test_is_grant_request_only_while_a_grant_route_answers` to Step 1.
- **Fix, in josworld:** the Matomo test drives the request through `go_to(LinkRoute::url($grant))`, the way `tests/Integration/ToolAppServiceTest.php:35` already does for `/tool-app/`.

## Note

- **Baseline Task 1 Step 3** (l.161) runs `--filter GrantRepository`, but the Test map now uses `'GrantRepository|GrantModel'`. Use the Test map's filter so `GrantModelTest` runs in the task's own evidence. `composer gate` covers it anyway.
- **`downloadHeaders()` returns the header lines and sends only nosniff** (`ntdst-core/api/Response.php:309-349`). `PrivateFiles::stream()` must `header()` each returned line itself, and the plan's "headers via" wording hides that. Its own `nosniff` is already sent by core, so `stream()` must not send it a second time.
- **The josworld `@smoke` and flow headers** are the only wire proof of the final `Cache-Control` after CacheHeadersService has run. Baseline's unit test (Review Focus 1) uses the `send()` seam. That split is deliberate and stated, and I note it only so the review knows where the wire proof lives.

## Premises checked

- `DownloadFormService` can be subclassed for a protected seam → `services/forms/DownloadFormService.php:24` (`final class`) → does not hold (S-1)
- `injectToolDownload()`, `withoutDownload()`, the ToolService dependency, and the "nog niet beschikbaar" copy → `DownloadFormService.php:31,38,52,91-94` → holds
- `ToolAppService` is strict_types, with `render($slug)` separate from `serve()` and `/tool-app/:slug` as its route → `services/content/ToolAppService.php:17,32,42,54` → holds
- The boot block's `require_once`/`ntdst_get` pattern for the new ToolGrantsMatomo wiring → `josworld-core.php:74,94,104,132` → holds
- josworld integration tier: no plugins loaded, and `failOnRisky` → `tests/bootstrap-integration.php`, `phpunit.integration.xml:7` → holds (the plan now designs around this)
- `go_to()` drives an ntdst-core route in josworld's integration tier → `tests/Integration/ToolAppServiceTest.php:35-39` → holds
- ntdst-core route dispatch through `ntdst_page` plus `matched_rule` on `template_redirect` → `ntdst-core/core/Pages.php:115,206-233` → holds (relevant to S-3)
- `NTDST_Response::downloadHeaders(int, string, ?string, string)` returns a list and sends nosniff → `ntdst-core/api/Response.php:309-349` → holds
- `api/Response.php` loads under the baseline unit bootstrap: its ABSPATH guard is satisfied → `Response.php:21`, `baseline tests/bootstrap.php:4` → holds
- Baseline bootstrap loads the core files from the Composer path → `tests/bootstrap.php:72-100` → holds
- Baseline requires `ntdst-core ^5.4` → `composer.json:14` → holds
- The `duplicate` module-map pattern to copy → `ntdst-baseline.php:90,107,163-164` → holds
- `wp ntdst polylang translation create <id> --lang=` exists in josworld's installed baseline → `ntdst-baseline/services/polylang/Cli/Commands.php:388-397` → holds
- Matomo `AIBotTracking` on `wp_footer` at 999999 → `plugins/matomo/classes/WpMatomo/AIBotTracking.php:73` → holds
- The josworld test files named in Task 2 exist → `tests/Integration/ToolAppServiceTest.php`, `ToolTargetTest.php` → holds
- The baseline worktree has no `vendor/` → `ls vendor` fails → holds (the plan says so)
- `LinkRoute::isGrantRequest()` has defined semantics and a test → baseline l.186-189 → does not hold (S-3)

## The cut

- `/home/ntdst/Sites/worktrees/baseline-grants/specs/grants/plan.md` is one feature: the general module, testable on its own unit tier. Its real-WordPress proof is placed in josworld by name.
- `/home/ntdst/Sites/josworld/specs/tool-grants/plan.md` is one feature: the site configuration and the form wiring. It builds against `dev-feature/grants` and is promoted after the `^2.21` pin.
- The interface between the two plans is `GrantService::issue()`, `LinkRoute::isGrantRequest()` and the `render` contract. It will be settled once S-3 defines `isGrantRequest()`.