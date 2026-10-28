# Tool Grants Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tool files and tool apps open only through a visitor's personal link; the download form sends the visitor to that link and mails it.

**Architecture:** josworld switches on baseline's `grants` module (ntdst-baseline `specs/grants/plan.md`) for the `tool` type, with its `file` field gated and a renderer for link-kind tools. `DownloadFormService` stops filling the thank-you message and instead calls `GrantService::issue()` and hands FluentForm a `redirectUrl`. `ToolAppService` stops answering `/tool-app/<slug>/` publicly; the renderer serves the app at the personal link.

**Tech Stack:** ntdst-baseline `dev-feature/grants` → 2.21, ntdst-core 5.4, FluentForms, YOOtheme, Polylang, Matomo (WP plugin).

**Spec:** `specs/tool-grants/spec.md` (with ntdst-baseline `specs/grants/spec.md`, which carries the threat model)

**First working version:** Task 1 — on DDEV, filling in the form on `/tools/contentkalender/` lands on the personal link page with a working Download button. Stefan looks at that page and the mail before anything else is built.

**Simplest design:** Keep josworld's message template and swap `{tool_file_url}` for the personal link. Rejected: the spec says the visitor is "send to THAT link" — a redirect, not a message — and the message path would keep the file URL logic alive beside the grant. The plan uses FluentForm's own `redirectUrl` return from the filter josworld already hooks, so no form setting changes in the database.

**Golden path:** `netdust-wp:ntdst-patterns` → `golden-paths/form-data-flow.md` (FluentForm submission → service → response). Departures: none.

## Global Constraints

- Evidence: no task claims a line below without its evidence in the task report — the
  command as run with its output quoted, or the review that owns the line. No quote, not met.
- Base: ntdst-core is the framework base and its layering applies to every new class —
  `netdust-wp:ntdst-framework`; where files live — `netdust-wp:ntdst-patterns`. Owned by the
  whole-branch review, joined on WordPress by `netdust-wp:ntdst-drift-reviewer` on the changed
  paths (Part 1 runs core's invariants live); its `## Findings:` header is quoted.
- Golden path: the plan names the slice this feature is built to (`netdust-wp:ntdst-patterns`
  → `golden-paths/`); every departure from it is named and justified, or `none`. Judged, not
  grepped: `/plan-review` checks the slice is named, the whole-branch review that code keeps it.
- Security: every data flow (AJAX action, REST route, form post, shortcode attribute,
  settings save, custom query) states its four pillars — validate, sanitize, escape,
  authorize — as `netdust-wp:wp-security` defines them; a pillar that does not apply is
  written `n/a — <why>`, never omitted. Evidence per flow: each pillar cited `file:line`; the
  output of `grep -nE "current_user_can|wp_verify_nonce|check_ajax_referer|check_admin_referer|permission_callback" <changed files>`;
  the denial test's name, RED then GREEN. `security-sentinel` owns the review when the plan
  carries a threat model.
- Drift: nothing `netdust-wp:ntdst-drift-reviewer` reports — a Critical or Drift finding is
  fixed or is a deviation the plan names; the review quotes the agent's findings, never "looks clean".
- Tests: the close is `make gate`. Unit = Brain Monkey, integration = wp-phpunit through
  DDEV, e2e = Playwright; commands and setup are `netdust-wp:wp-testing`'s. Evidence: the exit
  code and the summary lines of a run after the task's last edit. A tier that did not run is
  reported as not run, never as a pass. A site's tests cover its code and its configuration of
  ntdst-core and ntdst-baseline; the packages' own behaviour is tested in their repos.
- Local and ship: DDEV always; the Makefile is the only route to a server
  (`netdust-devops:devops`); anything that must ship names its `deploy.payload` path, and
  `make deploy-test env=staging` output listing it is quoted. An rsync, scp or SFTP to a server
  outside the Makefile is a defect the review reports.

| Red flag — the excuse | The answer |
|---|---|
| "Admin-only screen, so no nonce" | Capability says who; the nonce says they meant it. Both. |
| "The value comes from the database" | Stored input is input. Escape it on output. |
| "The reviewer will catch it" | The reviewer checks the evidence you quoted. No quote, not met. |
| "`make gate` was green earlier" | Earlier is not after your last edit. Run it, quote it. |
| "The integration tier needs DDEV; skip it" | `ddev start`. A tier not run is not a pass. |
| "Plain WordPress is simpler here" | On ntdst-core that is drift. Use the framework's door or name the deviation. |
| "Too small for the golden path" | Then the departure is `none` or one named line. Silence is the defect. |
| "The denial is obvious, no test needed" | Obvious is untested. Write it RED first. |
| "Just rsync this one file" | A hand-deploy is drift nobody sees. The Makefile, or nothing. |

Spec constraints:

- Composer runs in `app/composer.json`; inside DDEV it needs `auth.json` (`docker cp ~/.config/composer/auth.json ddev-josworld-web:/home/ntdst/.composer/auth.json`). Until baseline 2.21 is tagged the branch requires `dev-feature/grants`; the last task pins `^2.21` — a branch with a `dev-` constraint is never promoted.
- The private store on DDEV is `<project root>/private-files`: gitignored.
- Copy is Dutch, Jos's voice; Stefan approves it on the first working version. English and French labels go in `labels_i18n`.
- `specs/` is allow-list gitignored: spec, plan and review files need `git add -f`.

## Threat model

Baseline's (`ntdst-baseline/specs/grants/plan.md` § Threat model) covers the module. josworld adds:

1. **The hidden `tool_id` is tampered** to a draft tool, a page id or another type → `GrantService::issue()` refuses (`refused`); `DownloadFormService` then shows the existing "nog niet beschikbaar" message and redirects nowhere — `DownloadFormService::injectToolDownload()`.
2. **`/tool-app/<slug>/` is opened directly** → the public route is gone; the app is served only by the grant renderer — `ToolAppService::renderForGrant()`; `/tool-app/<slug>/` answers 404.
3. **Matomo records the personal link's URL** (the token) → on a grant request (`LinkRoute::isGrantRequest()`, checked on `wp_head` priority 0) the Matomo plugin's `WpMatomo\TrackingCode` callbacks are removed from `wp_head` and `wp_footer` (found in `$wp_filter` by class), and so is `WpMatomo\AIBotTracking::do_ai_bot_tracking` on `wp_footer` (it sends the current URL when `track_ai_bots` is on). Matomo's `matomo_tracking_code_*` filters run only when the stored code is regenerated, never per request — `ToolGrantsMatomo::dropTracker()`.
4. **A tool app page links out or loads something third-party** and leaks the token through `Referer` → baseline's `Referrer-Policy: no-referrer` on every grant answer; the renderer echoes the app only and sends no header of its own (ToolAppService's `public, max-age=3600` would undo `no-store`) — asserted on the wire by the `@smoke` and flow app-at-link.
5. **A submission FluentForm flags as spam issues a grant and mails the typed address** (its spam path still runs the confirmation filter) → no grant when the entry's status is `spam`, read through a status reader injected as a constructor callable (`?callable $entryStatus`, default: FluentForm's submission row, `''` when FluentForm is absent) — `DownloadFormService::injectToolDownload()`.
6. **The thank-you message still names a file URL** cached in a browser or a CRM mail from before → the files moved; old URLs 404 (flow old-url-404).

**Deferrals:** spam detection itself is FluentForm's (honeypot, as today); a revoked visitor learns their link was revoked — the address is theirs.

## Test map

| Unit | Seam | Tests: tier · file · command | E2E flows |
|---|---|---|---|
| Grants config + DownloadFormService | FluentForm's `fluentform/submission_confirmation` filter on form 3 | integration · `tests/Integration/ToolGrantFormTest.php` · `ddev composer test:int -- --filter ToolGrantForm` | form-to-link, mail-arrives, revoked-stays-out |
| ToolAppService (renderer) + J-int | the grant link for a link-kind tool; `/tool-app/<slug>/` | integration · `tests/Integration/ToolGrantAccessTest.php` · `ddev composer test:int -- --filter ToolGrantAccess` | app-at-link, app-direct-404 |
| ToolService `target` | YOOtheme `target` field; `ToolService::target()` | integration · in `ToolGrantAccessTest` · same command | tool-card-links-to-form |
| ToolGrantsMatomo | `dropTracker()` on `wp_head` 0 | integration · `tests/Integration/ToolGrantFormTest.php::test_matomo_callbacks_are_removed_on_a_grant_request` (stub `WpMatomo\TrackingCode` and `AIBotTracking` instances registered on the hooks, the request driven by `go_to(LinkRoute::url($grant))`; the wptests DB runs no plugins) · `ddev composer test:int -- --filter ToolGrantForm` | no-tracker-on-link |
| Copy (page, button, dead page, mail; NL/EN/FR) | config labels | none — proven by flow | form-to-link, dead-link, mail-arrives |
| Existing e2e that used the form | `tests/E2E/mail-template.spec.ts` flow 1 (`@e2e` on staging) | e2e · that spec · `sh bin/e2e.sh tests/E2E/mail-template.spec.ts` | (registered) |

Flows for the shake-out (`/shakeout` drives these, browser unless noted): **form-to-link-en** (the same on `/en/tools/<slug>/` → `/en/access/<token>/`, English labels and mail), **form-to-link** (fill in the form on `/tools/contentkalender/` → lands on `/toegang/<token>/`, title and Download button), **download** (the button downloads the PDF), **mail-arrives** (Mailpit: the JOS layout, the same link), **repeat-same-link** (same address again → same URL, no second mail inside a day), **old-url-404** (the old uploads URL → 404, wire), **app-at-link** (a link-kind tool → the app at the personal link), **app-direct-404** (`/tool-app/<slug>/` → 404, wire), **dead-link** (a made-up token → the Jos dead page, 404), **editor-revokes** (an editor revokes on the tool's screen; the link turns dead), **revoked-stays-out** (that address fills in the form again → the dead page, no mail), **editor-opens-file** (an editor opens the file from the media library), **no-tracker-on-link** (no Matomo script on the link page, wire), **tool-card-links-to-form** (a tool card's button goes to the tool page). `@smoke`: `/toegang/<64 zeros>/` answers 404 with the dead page, `X-Robots-Tag: noindex`, `Cache-Control: private, no-store` and `Referrer-Policy: no-referrer` (the header proof the integration tier cannot see). Flows download and app-at-link assert the same three headers on a live link (wire).

## Review Focus

1. **The staging `@e2e` flow 1 of mail-template** submits the download form and expects "Bedankt!" — after this branch it redirects. → Task 3 rewrites it to follow the redirect; `make e2e env=staging` stays green.
2. **Denied actor through the form** (edge 2): a tampered `tool_id` of a draft tool → no grant, no mail, the "nog niet beschikbaar" message. → `ToolGrantFormTest::test_draft_tool_id_issues_nothing` RED first (Task 1).
3. **Language** (edge 5): the form on `/en/tools/<slug>/` → `/en/access/<token>/`, English labels, English mail. → shake-out flow form-to-link-en on DDEV (Task 1 Step 1 seeds the English copy).
4. **Existing tools at deploy** (edge 1): a tool whose file was uploaded before — `gate-files` moves it; the old URL 404s. → `ToolGrantAccessTest::test_gate_files_moves_existing_tool_files` (Task 2); flow old-url-404.
5. **External link-kind tool** (edge 1): a tool linking to another domain → the link page with an "Open" button, `no-referrer`. → `ToolGrantAccessTest::test_external_link_tool_shows_an_open_button` (Task 2).

---

### Task 1: Gate tools and send the form to the link (first working version)

**Files:**
- Modify: `app/composer.json` (`"netdust/ntdst-baseline": "dev-feature/grants"`), `app/composer.lock`
- Modify: `app/content/mu-plugins/josworld-core/josworld-core.php` (the boot block's `require_once` and `ntdst_get` for ToolGrantsMatomo — `JosworldCoreBootBlockTest` guards them; enable `grants`; config `types => ['tool' => ['file_fields' => ['file']]]`, `base => ['nl' => 'toegang', 'en' => 'access', 'fr' => 'acces']`, no `render` yet (Task 2 adds it; until then the file page answers), `store => defined('PRIVATE_FILES_DIR') ? PRIVATE_FILES_DIR : null`, Dutch labels, `labels_i18n` en/fr; polylang `route_languages => true` — Ruling: the only other ntdst route, `/tool-app/:slug`, goes in Task 2, so nothing else moves — cost if wrong: a route gains a language prefix)
- Create: `app/content/mu-plugins/josworld-core/services/site/ToolGrantsMatomo.php` (threat 3)
- Modify: `.env.example` (`# PRIVATE_FILES_DIR=` — per server, Stefan's: production `/data/sites/web/josagency/private-files/production`, staging `/data/sites/web/josagency/private-files/staging`; both outside every served folder)
- Modify / delete: `tests/Unit/DownloadFormServiceTest.php` (the message tokens go; its refusal cases move to ToolGrantFormTest)
- Modify: `app/content/mu-plugins/josworld-core/services/forms/DownloadFormService.php` (`injectToolDownload()` skips a `spam` entry, then calls `GrantService::issue($email, $toolId)` — baseline reads the tool's language; an admin-ajax submission carries none of its own (CrmContactLanguageService); `issued`/`revoked` → `$returnData['redirectUrl'] = $url`; `refused` → `withoutDownload()`; the message tokens and `ToolService` dependency go)
- Modify: `.gitignore` (`/private-files/`)
- Test: `tests/Integration/ToolGrantFormTest.php`

**Interfaces:**
- Consumes: baseline `GrantService::issue(string $email, int $postId, ?string $lang = null): array{status, url}`, `LinkRoute::isGrantRequest(): bool`.
- Produces: form 3's confirmation returns `['redirectUrl' => <link>, 'message' => '']` for a published tool.
- Four pillars of the form flow: validate — `is_email()` in baseline, `absint` + published-and-gated for `tool_id`; sanitize — `sanitize_email()`; escape — `esc_url_raw()` on the redirect (FluentForm's own) and baseline's template escaping; authorize — n/a: a public lead form, the grant is the authorization it produces.

- [ ] **Step 1:** Baseline `feature/grants` is pushed (baseline Task 8); `ddev exec -d /var/www/html/app composer require netdust/ntdst-baseline:dev-feature/grants`. Fixtures: locally `contentkalender` (161) has no file and every tool is `nl` only — attach a sample PDF to 161 and create its English copy with `wp ntdst polylang translation create` (not the editor's "+", which has the LayoutCopy defect on block-editor screens) before the browser step; the integration tests build their own tools.
- [ ] **Step 2: Tests** — `test_published_tool_redirects_to_its_link_and_mails_it`; `test_draft_tool_id_issues_nothing` (RED first); `test_same_address_twice_gets_the_same_link_and_one_mail`; `test_revoked_address_redirects_to_the_dead_page_without_mail`; `test_spam_entry_issues_nothing` (a reader answering `spam` injected); `test_matomo_callbacks_are_removed_on_a_grant_request`. Integration tests set `store` to a temp directory, never DDEV's real `private-files`. The English path (Review Focus 3) is the shake-out flow **form-to-link-en**: the wptests DB has no Polylang.
- [ ] **Step 3:** Implement; `wp ntdst grants gate-files` on DDEV; fill in the form on `/tools/contentkalender/` in a browser and screenshot the link page and the Mailpit mail for Stefan. Run the filter, `make gate`; quote. Commit `feat(tools): the download form sends the visitor to their personal link`.
- [ ] **Mail text:** the grant mail's Dutch subject and body are Jos's existing visitor mail (Stefan, 2026-10-26: "Hey, / Bedankt om onze tool te downloaden. …"), with `{link}` where it reads `Link naar de tool:{tool_title} {tool_action}`. That old mail is a FluentForm notification or FluentCRM automation on production only (not in the local or staging database); after deploy it is switched off in wp-admin, or the visitor gets two mails. That is Stefan's or Jos's step, named in the hand-over.
- [ ] **Step 4: Stop.** Stefan sees the link page, the mail and the Dutch copy; his wording changes land before Task 2.

### Task 2: Tool apps behind the link; `target` points at the form

**Files:**
- Modify: `services/content/ToolAppService.php` (drop the public `/tool-app/:slug` route; add `renderForGrant(WP_Post $tool, WP_Post $grant): string|false|null` — a link-kind tool whose `url` path (host ignored: staging runs on production's database) is `/tool-app/<slug>/` echoes that app and returns null; a link to another domain returns the "Open" page body; a file-kind tool returns `false` so baseline's file page answers; the renderer echoes `render($slug)` and sends no header)
- Modify: `services/content/ToolService.php` (`target()` → the tool's permalink; `resolve_tool_target()` with it; docblock)
- Modify: `josworld-core.php` grants config — `'render' => [ntdst_get(ToolAppService::class), 'renderForGrant']`
- Modify / delete: `tests/Integration/ToolAppServiceTest.php` (the public route is gone; the render cases stay), `tests/Integration/ToolTargetTest.php` (`target()` is the permalink)
- Test: `tests/Integration/ToolGrantAccessTest.php`

- [ ] **Step 1: Tests** — `test_tool_app_opens_at_the_personal_link`; `test_tool_app_direct_url_is_404` (RED first); `test_external_link_tool_shows_an_open_button`; `test_target_is_the_tool_page`; `test_gate_files_moves_existing_tool_files`; `test_file_kind_tool_returns_false`.
- [ ] **Step 2:** Find every builder layout binding `target` or a tool's `file`/`url` (`ddev wp db query` over `post_content` and the `yootheme` option for `"target"` and `field.file`) and list them in the task report; a layout that linked straight to a file now links to the tool page.
- [ ] **Step 3:** Implement. Run the filter, `make gate`; quote. Commit `feat(tools): tool apps open only at the personal link; cards link to the form`.

### Task 3: The existing e2e, docs, and the release pin

**Files:**
- Modify: `tests/E2E/mail-template.spec.ts` (the shared `downloadTool()` helper, used by three tests, follows the redirect to `/toegang/` instead of expecting "Bedankt!"; the admin mail assertions stay; `afterAll` also erases the run's grants through WordPress's personal-data eraser for `e2e-tool-download@example.test`)
- Modify: `docs/content-model.md` (the tool: gated file, the link, the store), `docs/architecture/CODE-MAP.md` (grants are baseline's, configured in `josworld-core.php`)
- Modify: `app/composer.json` → `"netdust/ntdst-baseline": "^2.21"` once Stefan has tagged 2.21.0

- [ ] **Step 1:** Rewrite flow 1; run `sh bin/e2e.sh tests/E2E/mail-template.spec.ts`; quote.
- [ ] **Step 2:** Docs. After the tag: `composer update netdust/ntdst-baseline` in DDEV, `make gate`; quote. Commit `build(tools): baseline 2.21, grants`.
- [ ] **Step 3 (after deploy, Stefan's verbs):** per environment, `PRIVATE_FILES_DIR` in `app/.env`, then `wp ntdst grants gate-files` over SSH, then flow download on staging — the proof that the web pool reads the store; `curl -sI` on the store path from the web answers nothing.
