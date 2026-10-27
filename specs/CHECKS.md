# Checks registry

What the shake-outs left to run on a deployed site. `make e2e env=staging` runs every `@e2e`
test (it writes, so never production); `make smoke env=<env>` runs every `@smoke` test
(read-only, production too). `verified` is the commit and date the flow was last driven.

| surface | tier | entry | test | first feature | verified |
|---|---|---|---|---|---|
| Tool download notification mail (FluentForms form 3) | e2e | /tools/contentkalender/ | tests/E2E/mail-template.spec.ts | mail-template | 963e64d 2026-10-27 local (6689146 2026-10-21 on staging) |
| Mail logo, shown by every mail the site sends | smoke | /content/themes/josworld/images/logo.png | tests/E2E/smoke/mail-template.spec.ts | mail-template | 46abb5e 2026-10-21 |
| The English language, the site's Polylang languages | smoke | /en/ | tests/E2E/smoke/translation.spec.ts | translation | 2026-10-26 local, staging, production |
| The tool's personal link: form → link, download, mail, repeat, dead link, app at the link, revoke | e2e | /tools/contentkalender/ | tests/E2E/tool-grants.spec.ts | tool-grants | 963e64d 2026-10-27 local |
| The personal link's dead page and its headers (no-referrer, no-store, noindex) | smoke | /toegang/<64 zeros>/ | tests/E2E/smoke/tool-grants.spec.ts | tool-grants | 963e64d 2026-10-27 local |
| Personal links panel on a tool's edit screen, and the gated file in the media library | auth | /wp/wp-admin/post.php (tool) | | tool-grants | 963e64d 2026-10-27 local |

## Notes

- **mail-template, staging.** Outgoing mail is blocked there, so the `@e2e` mail flow proves
  only that the send went through (the form's thank-you); the layout is read back in Mailpit
  locally. Each staging run leaves one FluentForms entry for `e2e-tool-download@example.test` and
  keeps that one CRM contact (list Lead) up to date. The entry is a tool production carries:
  staging runs on a copy of production's database, so a tool that exists only locally 404s there.
- **Lost-password mail — local only, not registered.** `tests/E2E/mail-template.spec.ts` flow 3
  asks a reset for the seeded actor `e2e-reset` (its own: a login clears a pending reset key). `bin/e2e.sh` seeds DDEV only and `commands.e2e`
  seeds nothing, so, like every spec here that needs a seeded user, it runs in the local gate only.
- **Booking mails — local only, not registered.** FluentBooking "Sparmoment" on `/contact/`,
  driven by `tests/E2E/mail-booking.spec.ts`, which carries no tier tag: a booking reaches the
  host's connected Google or Outlook calendar, and Stefan ruled "no, no test boekings in google
  calendar" (2026-10-21). The spec refuses any site but the local DDEV one, refuses while a host
  has a remote calendar connected, and deletes the booking and the CRM contact it made. It runs
  in the local gate only.
- **Translation "+" — local only, not registered.** Polylang's "+" on a page, post, team member,
  case and dienst, the review flag, and unpublishing a Dutch record (specs/translation flows 1-5),
  driven by `tests/E2E/translation.spec.ts`, which carries no tier tag: every "+" sends text to
  the real DeepL key and leaves drafts, so staging would spend the key on every deploy, and its
  edges need the seeded `e2e-author`. The spec refuses any site but the local DDEV one and
  deletes every post and dienst term it made. It runs in the local gate only, about eight DeepL
  calls a run. Verified 22c041b 2026-10-26.
- **tool-grants, staging.** `tests/E2E/tool-grants.spec.ts` runs on staging the form → link
  (the real Contentkalender tool), the download and its headers, the repeat, the tracker check,
  the dead link, the direct app 404 and the tool card. The rest needs DDEV (Mailpit, the English
  tool copy, the link-kind fixture 578, WP-CLI, the seeded e2e-admin and e2e-author) and skips
  there with a reason. Each staging run keeps one grant, one FluentForms entry and one CRM contact
  for `e2e-grant-nl@example.test` (the same address returns the same link). The download needs
  `PRIVATE_FILES_DIR` and `wp ntdst grants gate-files` on staging first. Three tests are RED by
  design until baseline is fixed (the author's REST 500, the panel's UTC time, the PDF preview in
  the media library): specs/tool-grants/shakeout.md rows 16, 20 and 21.
