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

## Boundaries

- `app/content/mu-plugins/josworld-core/` is the business logic (services, one per CPT,
  explicitly required and resolved through the container). The theme carries no
  templates and no data code.
- The team single is public and `exclude_from_search`; `email` and `phone` leave the
  model only through the builder source, never REST.
- The deploy payload is `content/mu-plugins` and `content/themes/josworld`; the
  framework packages are Composer-installed and gitignored.
