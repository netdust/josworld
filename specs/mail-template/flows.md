# mail-template — flows

The shake-out flows of a branch with no spec. Approved by Stefan, 2026-10-21, with flow 2 kept
off staging: "no, no test boekings in google calendar".

Every mail is read back in Mailpit (DDEV). Staging blocks outgoing mail (`pre_wp_mail`), so there
a flow proves only that the send goes through; the layout itself is proven locally.

After the first staging run (2026-10-21): flow 1 moved from `/tools/canvas-gedrag/`, a tool only
the local site has, to `/tools/contentkalender/`, which production and staging carry; flow 3 runs
locally only, because staging has no seeded `e2e-author`.

| # | Surface | What a person does | What they should see | Edges |
|---|---|---|---|---|
| 1 | Tool download — FluentForms form 3 on `/tools/contentkalender/` | A visitor fills in the download form and submits it | The notification "Tool gedownload: …" reaches the admin address in the JOS layout: lemon background, the logo linking to the site, the submission table inside the white card, footer "JOS · jos.agency" | 7 the mail arrives · 5 a name with `<b>` and an emoji reads as text · 5 at 375px the card stays inside the screen |
| 2 | Booking — FluentBooking "Sparmoment" on `/contact/` | A visitor books a slot | The guest confirmation and the host notification arrive in the JOS layout: brown text, no FluentBooking blue header or footer; the reschedule/cancel link in the mail opens FluentBooking's page | 7 both mails arrive · 8 text colour and background match the design (#35070c, #fff794) · 5 at 375px |
| 3 | Lost password — `/wp/wp-login.php?action=lostpassword` | Someone asks for a new password | The mail arrives in the JOS layout as readable text; the reset link is clickable and opens WordPress's "new password" form | 7 the mail arrives · 8 the link works when clicked, not just present |

**Smoke (read-only, production too):** the logo every mail points to,
`/content/themes/josworld/images/logo.png`, loads on the environment.

## Not driven

- **Stageformulier (form 4)** cannot be submitted locally: FluentForms free drops the Pro upload
  field but still requires it. It sends through the same FluentForms path as flow 1.
- **Footer newsletter (form 2 / FluentCRM)** sends no mail: double opt-in is off and the form's
  admin notification is disabled. FluentCRM newsletters keep their own design.

## Flow 2 runs locally only

A booking must never reach a real calendar (FluentBooking Pro pushes to a connected Google or
Outlook calendar on every new booking). So flow 2:

- is not tagged `@e2e` or `@smoke`, so `make e2e env=staging` and `make smoke` never run it;
- refuses to book against anything but the local DDEV site;
- refuses to book while the host has a remote calendar connected
  (`RemoteCalendarHelper::getRemoteCalendarConfig()`), so a database pulled from production with
  a live connection stops it; none is connected locally on 2026-10-21;
- deletes the booking and the CRM contact it created.
