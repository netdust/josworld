# Checks registry

What the shake-outs left to run on a deployed site. `make e2e env=staging` runs every `@e2e`
test (it writes, so never production); `make smoke env=<env>` runs every `@smoke` test
(read-only, production too). `verified` is the commit and date the flow was last driven.

| surface | tier | entry | test | first feature | verified |
|---|---|---|---|---|---|
| Tool download notification mail (FluentForms form 3) | e2e | /tools/contentkalender/ | tests/E2E/mail-template.spec.ts | mail-template | 6689146 2026-10-21, on staging |
| Mail logo, shown by every mail the site sends | smoke | /content/themes/josworld/images/logo.png | tests/E2E/smoke/mail-template.spec.ts | mail-template | 46abb5e 2026-10-21 |

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
