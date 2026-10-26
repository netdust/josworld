Reviewed: 7b92afa1a34d30c021c267186f59e1c220695055
# Review — feature/tool-grants (josworld)

Range d696286..7b92afa, plan `specs/tool-grants/plan.md` with its threat model. Reviewed 2026-10-26 from a
detached checkout by three reviewers in parallel: superpowers' code reviewer, security-sentinel and
ntdst-drift-reviewer (no project ARCHITECTURE-INVARIANTS.md, so no invariant-auditor; core's INV-1..10 ran
clean against the changed files). The `make review` run could not use Bash headless and reviewed nothing;
this review replaces its report.

## Blocking

1. **The tool apps are public static files** — `app/content/themes/josworld/tools/*.html`, read by
   `ToolAppService::render()` (sentinel B1). `/content/themes/josworld/tools/campagnestart.html` answers
   200 on DDEV, staging and production; the grant (threat 2) is bypassed without form, address or token.
   Fix: the apps become `tools/<slug>.html.php` opening with `<?php defined('ABSPATH') || exit; ?>`, included
   by `render()` under output buffering (works on Apache and nginx); test: the old static path no longer
   serves the app's content.

## Should fix

2. **A spam-flagged entry sees the stored confirmation's raw `{tool_title} {tool_action}` / `{tool_file_url}`**
   — `DownloadFormService.php:62-64` (code 2, drift Q3). Fix: the spam path returns a neutral message too;
   assert `$out['message']` in `test_spam_entry_issues_nothing`.
3. **The threat-4 headers are unproven on the wire** — no e2e asserts `Referrer-Policy`, `no-store`,
   `X-Robots-Tag`; no `@smoke` for a dead token; no tool-grants line in `specs/CHECKS.md` (sentinel S2).
   Fix: in the `/shakeout` flows download and app-at-link plus the `@smoke`.
4. **A database copied between environments leaves the original public in the target's uploads** —
   `josworld-core.php:54` with baseline `PrivateFiles` (sentinel S1). `gate-files` skips an attachment already
   private; its stored path lies outside the target's store, so the link shows the dead page while the uploads
   original stays public. Fix lives in baseline (`gate-files` repairs a private attachment whose file is not in
   this store); josworld's handover names the order: refresh → `gate-files` → old-url-404 check.
5. **`.env.example` lacks `# PRIVATE_FILES_DIR=`** (code 1). Stefan's file; his step in the hand-over.

## Note — fix in the same pass (cheap)

- Drop the `class_exists(LinkRoute::class)` guards in `ToolGrantsMatomo.php:39` and `ToolLinkPage.php:41` (drift M3).
- `ToolAppService::render()` private, tested through `renderForGrant()` (drift M4).
- `asCurrentPost()` resets the postdata globals when there was no previous post (code 4).
- `built()` raw-echo: one docblock line on why (baseline kses strips `uk-*`) (drift B1).

## Note — deferred

- Baseline gaps, a baseline follow-up: the file page args carry no content id (D1: `grantedTool()` re-parses the
  download URL and verifies twice); no public default template / per-kind seam (D2: reflection fallback in
  `link.php`); no trusted-HTML return from `render` (B1); `render` gets no labels/lang (M1).
- `ToolLinkPage::section()` takes the first `single-tool` template, ignoring YOOtheme's language conditions
  (drift Q1); today there is one template.
- `ToolService::link()` reads the model, no repository (drift B2, predates the branch); the url field selects an
  app by path (B3); the builder-less fallback copies baseline's markup (M2).
- `DownloadFormService` constructs GrantService even with the module switched off (code N2); the refused message
  promises a send nobody does (code N3).
- Mail abuse: no per-IP cap, honeypot only — Turnstile on form 3 is Stefan's call (sentinel N2). External link
  tools hand out a permanent URL (N3). Old `/tool-app/` responses cached up to an hour (N4). FluentSMTP log holds
  the token (N5).

## Accepted by Stefan

- The link goes to whoever types the address (sentinel N1): his FR-7 wording "they get send to THAT link".

Findings: 1 Blocking · 4 Should fix · 15 Note
