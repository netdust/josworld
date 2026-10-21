Reviewed: f90065cc0c0cc490eae438d32628ab66cda577f4
All three reviewers are running in the background. I'll put together the report when they finish.

The drift reviewer stopped before writing its report, so I've asked it to finish one from what it has already found. Two reviewers are still running: the code reviewer and security-sentinel.

While the review was running, the local branch moved one commit past the head being reviewed. That commit, 68f3d84, is not pushed and only narrows a CSS rule in the mail layout's `<style>` block. So the reviewers' file reads are still valid for everything except that rule. I'll note this in the report. The code reviewer and security-sentinel are still running.

Security-sentinel has finished. Only the code reviewer is still running; I'll write the report when it's done.

## Feature review: mail-template

Nothing blocks this feature. Seven findings should be fixed before promote and ten are notes. The most important is a reordering in `wrap()` so plain-text mail always stays escaped.

**What was reviewed**
- **Range:** `0692b5a..f90065c`, which is `origin/main` to `origin/feature/mail-template`. It has two commits: the mail layout (f90065c) and an npm audit fix of the theme's dev lockfile (992b5b6).
- **Plan:** none. `specs/mail-template/plan.md` doesn't exist, so the reviewers worked from the diff and the commit messages.
- **Reviewers:** the superpowers code reviewer, security-sentinel (the change parses mail HTML and handles password-reset mail) and ntdst-drift-reviewer. invariant-auditor didn't run because the project has no `ARCHITECTURE-INVARIANTS.md`.

**Limits on this run**
- I couldn't read `REVIEW_NAME` or `REVIEW_LEVEL`, so I took the name from the checked-out branch and used the default level, `full`.
- Creating a separate detached checkout of the head was denied, so the reviewers read the main checkout.
- That checkout was at f90065c when they started. While they ran, the local branch moved to 68f3d84, an unpushed commit that only narrows one CSS rule in `layout.php`.
  - Security-sentinel reviewed f90065c directly; the drift reviewer read the files at 68f3d84.
  - Every finding below applies to both commits, except the table rule, which only applies to f90065c.
- The drift reviewer couldn't run any grep, so its framework checks were done by reading the four files. Project-wide searches (other `addPath()` callers, other `wp_mail` filters, whether `netdust-mail` is installed elsewhere) were not checked.
- Security-sentinel couldn't run PHP, so its exploit path was traced through the source, not executed.

### Blocking
None.

### Should fix

1. **A plain-text mail can be steered into the FluentBooking unwrap.** `app/content/mu-plugins/josworld-core/services/site/MailLayoutService.php:48` and `:132`. Raised by security-sentinel (L1, I1) and the code reviewer (#10).
   - `wrap()` looks for `<html` in the body before it checks the headers, and the XPath matches any class that merely contains "content" or "success".
   - **Attack:** a commenter's escaped markup is decoded by WP core in the moderation and post-author notification mails. The attacker's `div` then replaces the whole notification, dropping the approve/spam links and the commenter's IP and email. It still goes out as text/plain, so this is not XSS.
   - **Same cause, other mails:** FluentCRM's unsubscribe-request and manage-subscription mails and FluentSMTP's test mail also get unwrapped.
   - **Fix:** check `isHtml()` first. Anchor the XPath on exact class tokens under FluentBooking's `td[@class="body-inner"]`; FluentCRM and FluentSMTP use `"body-inner fluent-crm"` and `"body-inner fluent-smtp"`, so the anchor excludes them. Add a regression test for a plain-text mail containing that structure.

2. **FluentBooking mails come out grey (#444), not brown.** `MailLayoutService.php:120`, `app/content/mu-plugins/josworld-core/templates/mail/layout.php:22`. Raised by the code reviewer (#1).
   - FluentBooking runs its page through Emogrifier, which writes its own `p` colour and font onto every paragraph inside `div.success`. Those inline styles survive the unwrap.
   - The admin-set `email_footer` is also dropped silently.
   - The test fixture is hand-written and never emogrified, so it can't catch this.
   - **Fix:** override `p`/`li` colour and font in the layout, or strip them from the unwrapped nodes. Use a real emogrified page as the fixture.

3. **The pushed head stretches FluentBooking's add-to-calendar and confirm/reject tables to full width.** `layout.php:24` at f90065c. Raised by the code reviewer (#2).
   - The narrowing to `table.ff_all_data` exists locally in 68f3d84 but isn't pushed.
   - **Fix:** push it. For a sturdier fix, consider the `fluentform/all_data_shortcode_html` filter, because Gmail with non-Google accounts strips `<style>`.

4. **Claimed behaviours have no tests.** `tests/Integration/MailLayoutTest.php`. Raised by the code reviewer (#3) and security-sentinel.
   - The most important gap: nothing proves From, Reply-To and Bcc survive the header rewrite. Reply-To is how Jos answers form submitters and booking guests.
   - Also untested:
     - the `fluentform/send_plain_html_email` switch;
     - the `wp_mail_content_type` branch of `isHtml()`;
     - headers passed as a string;
     - an explicit `Content-Type: text/plain` being replaced rather than duplicated;
     - plain text containing `<html`;
     - a password-reset URL in WP core's real four-parameter shape.

5. **This duplicates a job the framework gave to `netdust-mail`.** `MailLayoutService.php:31-76` together with `layout.php`. Raised by ntdst-drift-reviewer (D1).
   - The framework skill names `netdust-mail` as the owner of "wrap every outgoing mail in a branded layout".
   - This diff builds that, plus the generic Fluent* handling, inside josworld-core.
   - `netdust-mail` isn't installed here, so this is your call. Either adopt it with josworld supplying only the layout, move the Fluent* handling upstream into it, or record in CODE-MAP why josworld keeps its own.

6. **The logo path is hardcoded.** `MailLayoutService.php:24`, `:64`. Raised by ntdst-drift-reviewer (D2).
   - `content_url('themes/josworld/images/logo.png')` hardcodes the theme folder. `SiteMetaService.php:98` already builds the same URL with `get_stylesheet_directory_uri() . '/images/logo.png'`, so the one asset now has two spellings.
   - **Fix:** use the `SiteMetaService` form, ideally through one shared accessor.

7. **CODE-MAP.md has no entry for outgoing mail.** `docs/architecture/CODE-MAP.md:59`. Raised by ntdst-drift-reviewer (D3) and the code reviewer.
   - It should record:
     - the site-wide `wp_mail` filter at priority 99;
     - the global FluentForms switch;
     - the FluentBooking selector and what happens when it misses;
     - full `<html>` pages from other plugins passing through untouched;
     - that `josworld-core/templates/` holds non-page templates only;
     - the decision from item 5.

### Note

1. **Invalid UTF-8 empties the message.** `MailLayoutService.php:53`. `esc_html()` returns `''`, so an empty card goes out. No visitor can trigger it today. Fix with `wp_check_invalid_utf8()`, or return the mail unchanged when escaping empties it. Raised by security-sentinel (L2) and the code reviewer (#5).
2. **The filter fails closed.** `MailLayoutService.php:61`. An exception from `html()` escapes the filter and blocks the mail, including WP's recovery-mode mail. A FluentBooking selector miss also falls back to FluentBooking's own layout without logging anything. Fix with a `try/catch (\Throwable)` that returns `$mail`, plus a log line on the miss. Raised by ntdst-drift-reviewer (B2) and security-sentinel (I2).
3. **The added HTML Content-Type can be overridden.** `MailLayoutService.php:53`. A `wp_mail_content_type` callback returning `text/plain` wins, as Matomo's text-only mails do, and the recipient gets raw HTML source. Raised by the code reviewer (#4).
4. **Registering the templates directory has site-wide reach.** `MailLayoutService.php:33`.
   - The directory is searched before the theme for WordPress's page hierarchy too, so a future `templates/page.php` would take over the theme's rendering.
   - Better registered once at plugin boot. The framework gap is that `addPath()` can't be limited to string rendering.
   - Raised by ntdst-drift-reviewer (B1) and the code reviewer (#9).
5. **WP core's password-reset and notification mails are now HTML-only.** They have no text alternative unless FluentSMTP's `send_as_text` option is on. Raised by security-sentinel (I3) and the code reviewer.
6. **Quoted or `<…>`-wrapped URLs in plain text get broken links.** `MailLayoutService.php:53`, because linking runs after escaping. No WP 7.1 core mail does this. Raised by the code reviewer (#6).
7. **Outlook desktop ignores `max-width`, so the card spans the whole reading pane.** `layout.php:32`. Raised by the code reviewer (#7).
8. **`lang="nl"` is hardcoded on a NL/FR/EN site.** `layout.php:17`. Raised by the code reviewer (#8).
9. **Brand values are copied into the template.** `layout.php:13`, `:28`, `:38`, `:56`. Lemon, brown, `alt="JOS"` and the literal footer label `jos.agency` duplicate existing values, and the label links to `home_url()`, so on staging it shows jos.agency but points at staging. Raised by ntdst-drift-reviewer (B3).
10. **Logo URL outside a web request (unverified).** The logo is a remote image on the site's own host, so opens show up in its access logs. Under WP-CLI or system cron without `WP_HOME` in production `.env`, the logo and footer link may point at localhost; the `.env` couldn't be read to check. Raised by security-sentinel (I4) and the code reviewer.

**Checked and clean:**
- **Security:** no XXE or entity-expansion risk in the DOMDocument parsing, and no header injection. Reset URLs are preserved, the raw content echo is justified, and none of the WP sanitize/escape/nonce/capability checks are affected.
- **Framework fit:** the boot shape, `ntdst_response()->html()` and `addPath()` all follow the framework.
- **Lockfile:** version bumps only, all from registry.npmjs.org.

The detached checkout was never created, so there was nothing to remove.

Findings: 0 Blocking · 7 Should fix · 10 Note
