Reviewed: 38fdfa41d752cc11c0b4eb61235d54a664839c4b
I've started three reviewers in parallel: the code reviewer, a security reviewer and a framework drift reviewer. The project has no `ARCHITECTURE-INVARIANTS.md`, so the invariant auditor doesn't apply. I'll write the summary once all three report back.

The drift reviewer stopped before writing its report; it says it is waiting on a search of its own. The code and security reviewers are still running. I'll wait for all three before I write the summary.

The drift reviewer's search finished, so it can write its report now. The code and security reviewers are still running. I'll report when all three are in.

The security review is back. The code reviewer and the drift reviewer are still running, and I'll write the full summary once both have reported.

The code and security reviews are both in. The drift reviewer had stopped without writing its report, so I've resumed it. The summary follows as soon as it reports.

# Review of feature/matomo-events

There is no plan (`specs/matomo-events/plan.md` doesn't exist), so the reviewers worked from the diff and the commit messages. The range is `5b1da56..38fdfa4`, from the merge-base with origin/main to origin/feature/matomo-events. That is 190 commits across 159 files, because main is behind: the branch also carries seo-launch, tool-url, taxonomy-archives and devops-core. Those four already have reviews in `specs/*/review.md`, and findings recorded there are left out below.

Three reviewers ran: the code reviewer, the security reviewer (the diff touches input parsing, capability checks and credentials) and the drift reviewer. The invariant auditor didn't run because the project has no `ARCHITECTURE-INVARIANTS.md`.

The drift reviewer had no shell access, so it could not run core's own invariant checks (INV-1…10). Its findings come from reading the files.

## Blocking

1. **The outgoing-mail block does nothing in www-next.** `scripts/remote/00-block-outgoing-mail.php:22-33`, `scripts/once/2026-10-10-go-live.sh:31,63,68-72`
   - `make block-mail` set the production host to `jos.agency`, and `build-prod.sh` sets `WP_HOME=https://jos.agency`, so the block decides it is on production and adds no filter.
   - From `swap` on, cron runs over the staging database copy while FluentSMTP has no connection. Queued FluentCRM emails, booking reminders and form notifications then go to real addresses through plain PHP `mail()`, before the `mail` step that was meant to control them.
   - Reported by the security reviewer.
2. **Running `prepare` again wipes production's Matomo setup.** `scripts/once/2026-10-10-go-live.sh:27-32,36-40`
   - The rsync runs with `--delete` and doesn't exclude `content/plugins/matomo.wpload_dir.php`. Production's copy is deleted, or replaced by staging's, which sends production hits into staging's database.
   - The staging database import then resets Matomo to staging's settings: tracking off, no goals.
   - Following the script's own day-of order, the site goes live with no analytics and no error.
   - Fix: add `--exclude=/plugins/matomo.wpload_dir.php`, and re-run `2026-10-12-matomo-go-live.php apply` at the end of `prepare` or name it in the order line.
   - Reported by the code reviewer.

## Should fix

1. **`prepare` after `rollback` overwrites the live production database with no backup.** `scripts/once/2026-10-10-go-live.sh:23-24,36-37,74-78`
   - Every booking, submission and CRM contact made while the site was live would be lost.
   - Fix: refuse when `$OLD` exists, or export a backup before the import.
   - Reported by the security reviewer.
2. **Dashboard submission links all open "No form found".** `services/admin/SubmissionRepository.php:95`
   - `form_id=0` is hardcoded. Select `s.form_id` instead.
   - Reported by the code reviewer.
3. **Booking links open the plain bookings list, not the booking.** `services/admin/BookingRepository.php:96`
   - FluentBooking's deep link is `#/scheduled-events?booking_id=<id>`.
   - Reported by the code reviewer.
4. **The team-layout repair overwrites team members that were never broken.** `scripts/once/2026-10-12-content-fixes.php:99-102`
   - `! $ok` is also true for content with no layout comment at all. Such a member gets another member's `post_content` through a raw `$wpdb->update`, with no revision and no backup.
   - Reported by the code reviewer.
5. **`ToolAppService` builds its own page route.** `services/content/ToolAppService.php:28-35,52-73`
   - It hand-rolls a rewrite rule, a query var, a `template_redirect` handler that ends in `exit`, and a manual 404. That is what `ntdst_pages()->path('/tool-app/:slug', …)` is for, and it would also drop the need to flush rewrites on every environment.
   - `:71` sets `Cache-Control` itself, which baseline's `cache_headers` module already owns.
   - Reported by the drift reviewer.
6. **The same four post-type slugs are listed in two places.** `services/admin/AdminUIService.php:34` and `services/admin/AdminAppService.php:41`
   - Nothing ties the two lists together. Keep one and have the other read it.
   - Reported by the drift reviewer.

## Note

**Matomo and analytics**
- **Goal pattern too broad.** `scripts/once/2026-10-12-matomo-go-live.php:31,92`: the "Tool opgevraagd" goal is a case-insensitive "contains download", so a call-to-action labelled "…downloaden" also converts. A regex `^download: ` would match only the form event. (Code reviewer)
- **Contradictory docblock.** `scripts/once/2026-10-12-matomo-go-live.php:15-16`: line 15 says the goals are created, line 16 says they are left alone. STATE.md:29 repeats the stale line. (Code reviewer)
- **No 100% scroll on short pages.** `src/analytics.js:64-71`: a page that fits the screen never fires a scroll event, so it never reports 100%. (Code reviewer)

**Redirects**
- **Unknown old post IDs reach the new site.** `services/site/LegacyRedirectService.php:86-89`: a `?p=` or `?page_id=` not in the table falls through, and WordPress resolves that number against jos.agency's own posts. (Code reviewer)
- **Array query parameters cause a PHP warning.** `services/site/LegacyRedirectService.php:82`: `?lang[]=x` triggers "Array to string conversion". It is log noise only; check `is_string()` first. (Security reviewer)
- **Raw redirect.** `services/site/LegacyRedirectService.php:64-65` sends a raw `header('Location: …')` and `exit`; `wp_safe_redirect` is the WordPress way. (Drift reviewer)

**CRM and forms**
- **Language tags pile up.** `services/forms/CrmContactLanguageService.php:47-51,78`: a contact who switches language keeps both tags, so nothing shows which is current. (Code reviewer)
- **Stale docblock.** `services/forms/CrmContactLanguageService.php:18` refers to the deleted `MultilingualService`. (Drift reviewer)
- **Visitors can tag CRM contacts.** `services/forms/CrmDownloadedToolService.php:55-71`: anyone can tag any address they submit with any published tool. It pollutes CRM data but grants nothing. (Security reviewer)

**Tools**
- **Tool apps can be opened without the form.** `services/content/ToolAppService.php:35,45-50`: anyone who knows the slug can open `/tool-app/<slug>/`. The lead form in `campagnestart.html:753-764` is a stub that sends nothing. Path handling is safe. (Security reviewer)
- **The mu-plugin depends on the theme folder.** `services/content/ToolAppService.php:89` reads `josworld/tools/` by literal name, so plugin code depends on the theme. (Drift reviewer)
- **The tools one-off deletes its input file.** `scripts/once/2026-10-09-tools-online.php:40`: sideloading removes the source, so passing `docs/tools/JOS_Backlashplanner.xlsx` deletes it from the checkout. (Code reviewer)

**Admin**
- **Workaround for baseline.** `services/admin/AdminChromeService.php:91-105` writes `$GLOBALS['menu']` directly because baseline's `admin_ui` doesn't keep the menu open; worth fixing upstream. (Drift reviewer)
- **Possibly unused method.** `AdminAppService::useSources()` (`:67`) may only be called by tests; this wasn't confirmed. (Drift reviewer)

**Framework and conventions**
- **Renaming a domain can still hide a case.** `services/content/CaseService.php:61,177`: renaming an existing domain's slug (`wp_update_term`) to a case's slug isn't guarded. (Code reviewer)
- **CODE-MAP contradiction.** `services/site/SiteMetaService.php:153` calls `pll_get_post_translations()`, but `docs/architecture/CODE-MAP.md:55` says `CrmContactLanguageService` is the last josworld code that touches Polylang. One of the two needs updating. (Drift reviewer)
- **Load-order guards around core.** `josworld-core.php:48`, theme `functions.php:29,42`: these turn a missing core into a silent no-op. The `AdminUIService` guard was already recorded in the taxonomy-archives review. (Drift reviewer)

**Go-live leftovers**
- **Database dump and DB password left on the server after go-live.** `$BASE/tmp/staging-dump.sql` (the full database, including personal data) and `$BASE/tmp/prod-db.env` (the plain-text production DB password) stay behind. Neither is web-served. (Security reviewer)

Findings: 2 Blocking · 6 Should fix · 18 Note

---
## Fix pass (2026-10-12)
**Blocking — fixed** (`scripts/once/2026-10-10-go-live.sh`):
- B1 the mail block is inert on production (it switches itself off on the production host, which www-next already is). Said so in the script; `swap` now refuses while the copied database holds CRM contacts, queued campaign mail or upcoming bookings (FORCE=1 to override) and while `matomo.wpload_dir.php` is missing; `mail` reports whether FluentSMTP has a connection. Production's mail queue was checked read-only: 0 contacts, 0 campaign mails, 0 bookings, 0 submissions.
- B2 `prepare` excludes `/plugins/matomo.wpload_dir.php`, exports the production database before importing, re-applies the Matomo settings at the end, and refuses once the site has been swapped in (marker file / `www-placeholder`; FORCE_PREPARE=1 to override).
**Should fix** — S1 covered by B2; S2 dashboard entry links carry the real form id; S3 booking links use `#/scheduled-events?booking_id=`; S4 the team repair only touches a layout comment that is broken, not one that is absent; S6 a consistency test now ties the two post-type lists together. Each has a unit/integration test.
Ruling: S5 (ToolAppService on ntdst_pages) deferred — it works, is tested and live; moving it changes a route the tools' link records point at, a day after launch. Cost if wrong: one `wp rewrite flush --hard` per environment and a hand-set Cache-Control header remain.
**Notes fixed:** the "Tool opgevraagd" goal matches `^download: ` (and the script now updates a goal whose pattern changed); the contradictory docblock; 100% scroll on a page that fits the screen; unknown `?p=` / `?page_id=` old IDs 404 instead of opening a jos.agency post; an array `lang` parameter; the tools one-off sideloads a copy; the stale `MultilingualService` docblock. Left: the remaining notes (admin menu workaround, `useSources`, case-slug rename guard, CODE-MAP wording, language-tag pile-up, tool apps reachable without the form, dump and DB password still on the server — delete them when launch is stable).
