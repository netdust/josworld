# Spec — tool grants (josworld)

**Stakes: high** — the site's lead form becomes the door to every tool. It configures baseline's
`grants` module (ntdst-baseline `specs/grants/spec.md`, which carries the threat model) and wires
the download form to it. Requested by Stefan on 2026-10-26.

## Requirements

### J-1 — Tools are gated
The `tool` type is gated; its `file` field's attachments are private. A tool of kind "link" that
points at one of the site's own tool apps (`/tool-app/<slug>/`) is gated too: the app opens only
at a personal link, and `/tool-app/<slug>/` itself answers 404.
Source: "a file linked to a tool can not be public, you'll need a personal link to access it. same
for a tool page, only with a valid personal link."

### J-2 — The form sends the visitor to their link
A valid submission of the download form (FluentForm 3) issues the grant for the submitted address
and the hidden `tool_id`, then redirects to the personal link instead of showing the download in
the thank-you message.
Source: "if they fill in the form, they get send to THAT link and the link is in the email".

### J-3 — The confirmation mail
The visitor gets a mail with the link, in Jos's voice, in the language of the page they used, in
the JOS mail layout.
Source: "the mail is confirmaiton and pick later on instead of filing the form again."

The form today sends only the admin notification (checked 2026-10-26), so this is the visitor's
only mail.

### J-4 — The tool page stays public
`/tools/<slug>/` keeps its text and its form; only the file and the app behind it are gated.
Source: "same for a tool page, only with a valid personal link" read with the playback "the tool
page itself stays public, it carries the form" — confirmed "yes".

### J-5 — Existing tools gated at deploy
Every tool file already uploaded is moved private by baseline's command, run once per environment.
Earlier thank-you links and uploads URLs stop working; earlier visitors fill in the form once more.
Source: playback "links already handed out stop working" — confirmed "yes".

### J-6 — What stays
FluentCRM tagging per tool (CrmDownloadedToolService), the admin notification, the CRM contact.
A tool of kind "link" to another domain cannot be gated and keeps working as today, now reached
through the personal link page: josworld's renderer answers it with an "Open" button.
Source: invented — approved 2026-10-26 ("ok, continue").

### J-7 — The builder's `target` field
`target` (ToolService) answers the tool's own page, where the form is: the raw file and app URLs
404 once gated. Every layout binding it is found and checked in the plan (Claude's call, approved "ok, continue").

### J-8 — A revoked visitor stays out
An address whose link was revoked and fills in the form again lands on the dead-link page; no mail.
Source: baseline FR-8, approved "ok, continue" (2026-10-26).

## Texts

Dutch first, English and French through the translation flow: the link page, the Download button,
the dead-link page, the mail. Jos's wording is Stefan's to approve on the first working version.
