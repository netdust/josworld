# Go-live runbook — jos.agency (Thursday 2026-10-08)

Production (`/data/sites/web/josagency/www`) is an EMPTY webroot (a placeholder `index.html`): this is a **first
bring-up**, not a deploy. The `make` verbs never move data into production, so the copy of staging is manual,
in this order. Staging path: `/data/sites/web/josagency/subsites/staging.jos.agency` (WP core in `wp/`, content in `content/`).
Tick as you go; items marked **Stefan** need a decision or credentials only he has.

## A. Before the copy — on staging
- [ ] **Delete/unpublish test content** (list = what staging holds today; **Stefan: confirm keep/delete**):
      `/sample-page/`, `/hello-world/`, `/e2e-fixture-post/`, `/test-secties/` (19 h1s), `/tools/testdraft/`,
      `/fr/homepage-fr/`, `/en/homepage-en/`, `/en/ons-werk/biggest/`; the vacature "Position name"; the seven
      cases titled "Lorem ipsum title…"; the blog posts that still use `placeholder-landscape.png`.
- [ ] **Users:** decide about `admin` (id 1) and `jw-build-bot` (id 4), both administrators; no `e2e-*` user may exist.
- [x] `blogname` = `Jos` (done on local + staging 2026-10-05; titles and `og:site_name` follow).
- [ ] **Privacy page** (id 23): only the Matomo "Statistieken" section exists — **Stefan** writes the rest.
- [ ] The e2e check green on the final content (`make e2e env=staging`), then freeze staging.

## B. Production bring-up (order from netdust-devops: not a deploy)
1. **`.env`** with production DB credentials (Combell), environment = production. **Stefan** (credentials). Never commit.
2. **WP core** `wp core download` into `www/wp/` at the SAME version as staging; `wp-config.php`, `index.php`, `.htaccess`
   copied from staging and adjusted (DB, salts, environment type). Remove Combell's placeholder `index.html`.
3. **Code + vendor:** the payload is `content/mu-plugins` + `content/themes/josworld`; `ntdst-core`/`ntdst-baseline`
   are gitignored composer packages that ship from the working tree; the YOOtheme parent theme and the third-party
   plugins (FluentCRM, FluentForm, FluentBooking (+Pro), FluentSMTP, Polylang, Matomo) are not in git: **copy them from staging**.
4. **MAIL — the trap.** Do NOT copy `content/mu-plugins/00-block-outgoing-mail.php` (it exists only on the staging
   server). After the DB import set FluentSMTP's `simulate_emails` back to **off** (it lives in the DB) and send one
   real test mail. Otherwise no booking confirmation or form notification ever leaves.
5. **Database:** dump staging, import to the production DB, then
   `wp search-replace 'https://staging.jos.agency' 'https://jos.agency' --all-tables-with-prefix --skip-columns=guid`
   (and the bare host `staging.jos.agency`), then `wp rewrite flush --hard` and a cache flush that is NOT a global Redis flush.
6. **Options that must differ on production:** `blog_public` = **1** (staging is 0: copying it makes production
   noindex, robots `Disallow`, and removes `/wp-sitemap.xml`); the SEO noindex follows it. Check Polylang's
   domain/URL settings and FluentBooking/FluentCRM URLs after the search-replace.
7. **Uploads** (`content/uploads/`) and licensed assets (YOOtheme parent) rsync'd from staging.
8. **DNS + TLS** for `jos.agency` (Combell). **Stefan.**
9. **Matomo** (see `memory/STATE.md`, "Matomo — go-live checklist"): site record URL → `https://jos.agency`,
   `trusted_hosts` in `content/uploads/matomo/config/config.ini.php`, tracking mode **Auto**, cookieless stays,
   a system cron hitting `wp-cron.php`, utf8mb4 (incl. `ntdst_matomo_log_bot_request`), exclusion for HeadlessChrome,
   then goals on the Form / Booking / Contact events.

## C. Verify (read-only first)
- [ ] `curl -I https://jos.agency/` 200; `/robots.txt` has no `Disallow: /`; `/wp-sitemap.xml` 200;
      no `<meta name="robots" content="noindex">` on the homepage.
- [ ] The read-only smoke check against production (`make smoke env=production`). **Never run the e2e check against
      production** (it writes).
- [ ] One real booking and one newsletter signup by a human: mail arrives, the FluentCRM contact appears, Matomo
      records a pageview and the `Booking | Booked` event.
- [ ] Share preview of the homepage and one case (LinkedIn/Facebook debugger, WhatsApp).
- [ ] After go-live, shipping goes through the normal verbs again; record the first production commit
      (`deployed/production`) so `make deployed` and `make ship` have a baseline.

## D. After launch (baseline SEO work — see memory `seo-belongs-in-baseline`)
Canonicals on archives, og:image 1200×630 crop + width/height/alt + og:locale, icon set + manifest, hreflang
once EN/FR have real translations. Content: descriptions for the 12 pages without one, h1 on `/blog-nieuws/`
and the tools, alt text.
