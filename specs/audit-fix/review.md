Reviewed: 364d8ff25bc611aa296c0d8eb020a84b144698e1
The three reviewers are running in the background. I'll put together the report once they've all finished.

The drift reviewer says it's still waiting on its own background sweep, so it has no report yet. The code and security reviewers are also still running. I'll put the summary together when all three are in.

Waiting on the code reviewer and the drift reviewer.

The code review is in: no blocking issues and four should-fix items. Still waiting on the drift reviewer.

# Review of feature/audit-fix (5b1da569 → 364d8ff)

No reviewer found anything blocking. They raised 10 should-fix items: 6 in the code and 4 that are framework drift. Two of the 10 are server leftovers rather than code. The rest are notes.

**What was reviewed.** The range runs from 5b1da569, where the branch splits from origin/main, to origin/feature/audit-fix at 364d8ff. That is 163 files, about +15.9k/−7.6k lines. Production's main is far behind, so the range bundles several features that were already reviewed and promoted (devops-core, duplicate-pages, taxonomy-archives, tool-url, seo-launch, matomo-events). There is no `specs/audit-fix/plan.md`, so the reviewers judged the diff against the commit messages.

**Who reviewed.** The superpowers code reviewer, `security-sentinel` (the diff touches redirects, form input, admin, outbound links and the mail block) and `ntdst-drift-reviewer`. `invariant-auditor` was not run because the project has no `ARCHITECTURE-INVARIANTS.md`.

**Gaps in coverage:**
- Some git and Bash calls were denied, for me and for the reviewers. I couldn't write the diff to a file, so each reviewer read the files on disk at head instead of receiving the diff.
- The drift reviewer could not run core's scripted checks (Part 1, invariants 1–8 and 10). Some of its findings may come from before this branch.
- The security reviewer did not finish the DOM-XSS read of the two tool HTML apps in `themes/josworld/tools/*.html`.
- These got only a light pass: `CaseService`, `case-term-limits.js`, the admin services, `mk/*.mk` and `flow-test.sh`.

## Blocking
None.

## Should fix
1. **The submission-link test can't catch the bug it was written for.** `tests/Unit/SubmissionRepositoryTest.php:133-145` (code reviewer)
   - The test checks that the SQL contains `form_id`, but the JOIN at `SubmissionRepository.php:70` always contains it.
   - Dropping `s.form_id` from the SELECT at :68 keeps the test green while every dashboard link goes back to `form_id=0`.
2. **The hero swap can pick the wrong image.** `services/site/HeroImageService.php:55-62` (code reviewer)
   - It takes the first `data-sources` div in the first 40 KB without skipping `uk-hidden@*`, `uk-visible@*` or the header and dropdown markup.
   - On a page with a desktop-only hero first, phones preload the hidden image at high priority, and the visible phone hero still waits for UIkit.
3. **Existing inline styles get escaped twice.** `HeroImageService.php:103-106` (code reviewer)
   - The current `style` value is read without decoding and then run through `htmlspecialchars` again.
   - A style that already contains an entity (`&quot;`, `&amp;`) breaks, and the section loses that style.
4. **The "Tool opgevraagd" goal misses downloads on pages without an `<h1>`.** `scripts/once/2026-10-12-matomo-go-live.php:32` with `src/analytics.js:14-17` (code reviewer)
   - The goal pattern `^download: ` only matches when the page has an `<h1>`; without one the event is plain `download`.
   - Fix: change the pattern to `^download(: |$)`.
5. **The tool apps bypass the framework's page router.** `services/content/ToolAppService.php:28-36, 52-74` (drift reviewer, INV-6)
   - It adds its own rewrite rule and query var, then echoes and exits inside `template_redirect`.
   - It needs a hand-run rewrite flush on every environment. If that's forgotten, or after a Permalinks save, `/tool-app/<slug>/` returns 404 with no error.
   - Fix: register it through `ntdst_pages()->path()`. Line 71 also sends its own `Cache-Control` header, which may clash with baseline's `cache_headers` module.
6. **Icon tags are printed by josworld instead of baseline.** `services/site/SiteMetaService.php:51, 167-178` (drift reviewer)
   - The favicon, manifest and `theme-color` tags are written straight into `wp_head`.
   - That breaks the rule that SEO belongs in baseline's SeoService. Pass the values to baseline, or for now add them through `ntdst/baseline/seo/meta`.
7. **Sitemap rules and a Polylang call live in josworld.** `SiteMetaService.php:49-50, 133-165` (drift reviewer)
   - The sitemap exclude and taxonomy logic belongs with baseline's SEO settings.
   - Line 153 calls `pll_get_post_translations`. That makes `CODE-MAP.md:55` wrong, since it says `CrmContactLanguageService` is the last josworld code that touches Polylang.
8. **A failed term link would crash the page render.** `services/content/CaseService.php:414` (drift reviewer)
   - `(string) get_term_link(...)` is never checked with `is_wp_error()`, and casting a `WP_Error` to string is a fatal error.
   - This is unlikely, because the term comes from `get_the_terms` just before.
9. **The staging database dump and the production DB password are still on the server.** `scripts/once/2026-10-10-go-live.sh:48` (security-sentinel)
   - The script writes `$BASE/tmp/staging-dump.sql`, which contains personal data, and never deletes it. `$BASE/tmp/prod-db.env` holds the production DB password in plain text.
   - Neither is web-served, but anyone with shell access can read them. Delete both once the launch is stable. This is server state, not code.
10. **The YOOtheme API key may need rotating.** (security-sentinel)
   - A lesson added in this range records that `yoo-*-backup-*.json` files with `yootheme_apikey` sat in the staging webroot, and nginx kept serving cached copies after they were moved.
   - Assume the key leaked until it is rotated.

## Note
**Security (security-sentinel):**
- `ToolAppService.php:45-50`: a tool app is served whether or not its tool post is published, and the HTML can also be fetched directly from the theme folder. There is no path traversal. The earlier review already accepted this.
- `go-live.sh:54-58`: `prepare` copies every staging user to production; only the build bot is removed. Check the printed user list on the day.
- `2026-10-12-content-fixes.php:62`: the backup, which includes the API key, falls back to `sys_get_temp_dir()` if `tmp/backups` isn't writable. It should exit instead.
- `scripts/remote/00-block-outgoing-mail.php:45`: the `fluent_crm/disable_email_sending` hook doesn't exist. Nothing leaks, because the `pre_wp_mail` filter already blocks all of it.

**Code (code reviewer):**
- `tests/Unit/BookingRepositoryTest.php:154-155`: a docblock now sits above the wrong test.
- `go-live.sh:32, 78, 84, 96-101`: after `rollback`, `prepare` refuses unless `FORCE_PREPARE=1` is set, and the usage text doesn't say so. The mail check in `swap` counts every CRM contact, so make sure `FORCE=1` doesn't become a habit.
- `go-live.sh:91-93`: production has no FluentSMTP connection, so live notifications go out through plain PHP `mail()`. This is an operational gap.
- `2026-10-09-tools-online.php`: the result of `copy()` isn't checked, so a failed copy sideloads an empty file.
- `2026-10-12-content-fixes.php:29`: confirm the "Onze Energie" case post actually changed. If YOOtheme stored the text escaped, the replace silently did nothing.
- `HeroImageService.php:40`: the builder preview also gets the swapped markup. Probably harmless, but untested.

**Drift (drift reviewer):**
- Guards around core helpers at `josworld-core.php:48`, `AdminUIService.php:127` and `functions.php:29, 42`. If core is missing, the site boots silently without its content types instead of failing loudly. These may predate this branch.
- Data is read straight from the data manager instead of through a repository at `AdminUIService.php:131`, `CaseService.php:384` and `ToolService.php:55-61`. This matches the project's "one service per content type" convention.
- `LegacyRedirectService.php:64-72` sets a raw `Location` header and exits. It is safe because every target is a fixed address; `ntdst_response()->redirect()` is the framework helper for this.
- `HeroImageService.php:44` rewrites the page's HTML with a regex. If YOOtheme changes its markup, the page is returned unchanged.
- `AdminAppService.php:67, 254, 264`: `useSources` is probably used only in tests (unverified).

Findings: 0 Blocking · 10 Should fix · 15 Note

---
## Fix pass (2026-10-12)
**Fixed** — 1 the submission-link test now fails if `s.form_id` leaves the SELECT (checked by removing it); 2 `HeroImageService` only swaps a *section* background that every screen shows (`uk-hidden@*` / `uk-visible@*` and non-sections such as dropdown images are skipped, the next candidate is tried); 3 an existing inline style is decoded before it is escaped again (test with `&quot;`); 4 the goal matches `^download(: |$)` (the script updates an existing goal, applied on local; **run `matomo-go-live.php apply` on production after the ship**); 5 `ToolAppService` is a `ntdst_pages()->path('/tool-app/:slug', …)` route (the router flushes its own rules; callback returns null/false per its contract; integration tests cover the claimed URL and the 404); 6 the icon, manifest and theme-colour tags join baseline's own head tags through `ntdst/baseline/seo/meta` (passes baseline's kses allow-list; verified in the served head); 8 a failed `get_term_link` no longer fatals; 9 `prod-db.env` and `staging-dump.sql` are deleted from the server. Notes fixed: the content-fixes backup no longer falls back to a temp folder, the tools one-off checks its `copy()`, CODE-MAP wording.
Ruling: 7 (sitemap rules and the translation call out of josworld into baseline) deferred — it is a baseline release (`sitemap_exclude` / `sitemap_taxonomies` config, Polylang-aware) plus a josworld bump, the right shape, not a launch-day change. Cost if wrong: two small filters stay in `SiteMetaService`, both tested.
Not code: 10 the YOOtheme API key — rotate it in the YOOtheme account (Stefan); the staging copies were reachable for weeks.
