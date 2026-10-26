# josworld — CODE-MAP

Decisions and traps about how this codebase works. No inventories: a wrong map gets
trusted, so only what a session must not re-derive lands here.

## The content concept (2026-09-03)

- **A model is its card.** A CPT declares only the fields another page needs to show it
  (`docs/content-model.md` carries the table; `CardFieldsModelTest` pins it). The page
  itself is built in the YOOtheme builder on the post.
- **A post with a builder layout bypasses its single template.** YOOtheme renders such a
  post through `page.php`; the trimmed `single-<type>` templates are the fallback for a
  post without a layout. Never bind YOOtheme's `content` field on a builder page: it is
  the layout's own introtext.
- **Four starter layouts live in the builder library**, `Single · Case/Tool/Team/Vacature`,
  seeded from `specs/cpt-builder-singles/layouts/` (gitignored — the DB is the artifact;
  re-seed after a DB refresh). A relation that moved into the builder is a grid whose
  items bind one manually selected post through the singular `custom<Type>` query.
- **The ntdst-baseline `yootheme` module is the main builder bridge.** josworld-core opts
  in by assignment on `ntdst/baseline/modules`. The one exception is a COMPUTED builder
  field baseline has no seam for: `CaseService` (`labels`) and `ToolService` (`target`) each
  hook YOOtheme's `source.init` themselves (priority -5, a by-name resolver function in the
  service file — never the `Service@method` form, which would construct a second service).
  Take a third one upstream to baseline instead of copying the pattern. A
  `relation` to `attachment` resolves empty through it — media fields use `file`,
  `image`, `gallery`.
- **Every layout write goes through `.yoo-tools/yoo-content.php` as an admin.** KSES
  destroys a layout written any other way; a layout needs a root `version`.

## The CRM surface (2026-09-05)

- **A FluentForms CRM feed is ONE DATABASE ROW PER FEED.**
  `GlobalNotificationManager` reads every `fluentcrm_feeds` row for the form and
  json_decodes each into a single feed, so an array of feeds in one row is silently
  ignored — no error, nothing runs. `Helper::setFormMeta` cannot write a set either
  (`FormMeta::persist` is update-or-create per meta_key and collapses them); delete the
  form's rows and insert one per feed. Booking feeds are the same shape in `fcal_meta`
  with `object_type` = `calendar_event`.
- **Consent is expressed as two feeds, not one.** Every lead form carries `privacy`
  (required — the contact may exist) and `nieuwsbrief` (optional, value `ja` — marketing
  consent). So each form gets an unconditional feed onto the operational **Leads** list
  and a conditional one onto **Nieuwsbrief**. Never send a campaign to Leads, and keep
  `pref_list_type` on `filtered_only` — `all` publishes every list, Leads included, on
  the public preference page.
- **A checkbox condition uses `=`, not `contains`.** ConditionAssesor's `=` branch does
  `in_array($value, flatten($input))` for array inputs; `contains` calls `Str::contains`
  on the array and misbehaves.
- **`source_url` carries the `/wp` prefix.** FluentForms stores it as
  `site_url($referer)`, and this is a `custom-app` structure, so every submission arrives
  as `/wp/fr/…`. `CrmContactLanguageService` strips the site base path before reading the
  language segment — without that the first segment is `wp`, no language matches, and
  every French and English contact is silently tagged Dutch.
- **FluentCRM and Polylang are both gitignored**, so "neither is installed" is the
  default state of a fresh clone. Every call out of `CrmContactLanguageService` is
  guarded for that reason — with `SiteMetaService`'s sitemap rule (it also lists a page's translations through `pll_get_post_translations`) it is one of two josworld-owned places that touch Polylang.
  The multilingual layer itself is ntdst-baseline's `polylang` module (opted into in
  `josworld-core.php`), which carries the same absence contract in the package.
- **Translation is baseline's, configured in `josworld-core.php`** (2026-10-26): "+" is a DeepL
  draft with the Dutch facts copied once (`field_sync => 'create'`), copies follow the Dutch
  record out of publish, and a `text` field holding a fact declares `'translate' => false`.
  josworld writes no translation code of its own. Runbook: `docs/content-model.md`, "Translating a record".

## Outgoing mail (2026-10-21)

- **Every mail leaves in the JOS layout through ONE `wp_mail` filter** —
  `MailLayoutService` at priority 99, rendering `josworld-core/templates/mail/layout.php`
  through `ntdst_response()->html()`. FluentSMTP's own `wp_mail()` applies the filter too,
  so WP core, FluentForms, FluentBooking and anything added later pass through it.
- **What it does per message:** plain text (no HTML Content-Type, and the
  `wp_mail_content_type` filter not saying HTML) is escaped, linked and sent as HTML; an
  HTML fragment is taken as markup; a complete `<html>` page is another plugin's layout and
  leaves untouched — except FluentBooking's, which has no setting to send its message bare.
  Its message is lifted out of `td.body-inner … td.content > div.success` (exact classes:
  FluentCRM and FluentSMTP share the skeleton as `body-inner fluent-crm|fluent-smtp`) and
  its inlined grey text and font are stripped. FluentBooking's `email_footer` setting is
  dropped with its page (unset today). If the selector ever misses, the booking mail goes
  out in FluentBooking's own layout — nothing is lost.
- **FluentForms is switched site-wide** to send its message instead of its page
  (`fluentform/send_plain_html_email`). FluentCRM newsletters keep FluentCRM's own design
  and unsubscribe footer; a campaign on the "Raw HTML" design gets the JOS layout.
- **josworld keeps its own wrapper** rather than adopting `netdust-mail` (Stride's mailer,
  not installed here): Stefan, 2026-10-21, "hou het eenvoudig", on placing it in
  josworld-core. Revisit when a second site wants the same layout.

## Boundaries

- `app/content/mu-plugins/josworld-core/` is the business logic (services, one per CPT,
  explicitly required and resolved through the container). The theme carries no
  templates and no data code; `josworld-core/templates/` holds rendered-to-string
  templates only (the mail layout) — a page template there would take over the theme's.
- The team single is public and `exclude_from_search`; `email` and `phone` leave the
  model only through the builder source, never REST.
- The deploy payload is `content/mu-plugins` and `content/themes/josworld`; the
  framework packages are Composer-installed and gitignored.
