# Checks registry

What the shake-outs left to run on a deployed site. `make e2e env=staging` runs every `@e2e`
test (it writes, so never production); `make smoke env=<env>` runs every `@smoke` test
(read-only, production too). `verified` is the commit and date the flow was last driven.

| surface | tier | entry | test | first feature | verified |
|---|---|---|---|---|---|
| Tool download notification mail (FluentForms form 3) | e2e | /tools/canvas-gedrag/ | tests/E2E/mail-template.spec.ts | mail-template | 134d5fc 2026-10-21 |
| Lost-password mail (WordPress core) | e2e | /wp/wp-login.php, "Wachtwoord vergeten" | tests/E2E/mail-template.spec.ts | mail-template | 134d5fc 2026-10-21 |
| Mail logo, shown by every mail the site sends | smoke | /content/themes/josworld/images/logo.png | tests/E2E/smoke/mail-template.spec.ts | mail-template | 134d5fc 2026-10-21 |

## Notes

- **mail-template, staging.** Outgoing mail is blocked there, so the two `@e2e` mail flows prove
  only that the send went through (the form's thank-you, WordPress's "check your email" screen);
  the layout is read back in Mailpit locally. Each staging run leaves one FluentForms entry for
  `e2e-tool-download@example.test` and keeps that one CRM contact (list Lead) up to date. The
  lost-password flow asks a reset for the seeded actor `e2e-author`, which must exist on the
  environment — `bin/e2e.sh` seeds DDEV only, and `commands.e2e` does not run it.
- **Booking mails — local only, not registered.** FluentBooking "Sparmoment" on `/contact/`,
  driven by `tests/E2E/mail-booking.spec.ts`, which carries no tier tag: a booking reaches the
  host's connected Google or Outlook calendar, and Stefan ruled "no, no test boekings in google
  calendar" (2026-10-21). The spec refuses any site but the local DDEV one, refuses while a host
  has a remote calendar connected, and deletes the booking and the CRM contact it made. It runs
  in the local gate only.
