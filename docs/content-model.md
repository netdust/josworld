# josworld content model

A model is its card: the fields another page needs to show it in a grid or
a CTA. The page itself — the narrative, the layout, the sections — is built
in the YOOtheme builder, not stored as meta. Four starter layouts live in
the builder library (`Single · Case/Tool/Team/Vacature`); a relation that
moved into the builder is a grid whose items bind one manually selected
post, through the singular `custom<Type>` query.

Reference doc for the four content post types, the `dienst` taxonomy, their
meta keys, relations, and the two registration conventions the codebase
enforces mechanically. Written at T12 (content-model phase close);
ground-truthed against the live services in
`app/content/mu-plugins/josworld-core/services/`, not summarized from the plan.

**This doc's meta-key table is not hand-copied.** It is verified against the
live model registry by
`tests/Integration/DocumentedMetaKeysConsistencyTest.php`, which parses the
table below (between the HTML comment markers surrounding the "Machine-checked
meta-key table" section) and asserts it is byte-for-byte the set of
`{model, field, meta_key}` triples the real `NTDST_Data_Manager` registry
reports for every josworld-owned model (same `_jw_`-meta-prefix registry
scope `ModelConventionsTest` already established). Edit a field's key in a
service without updating this table (or vice versa) and that test goes RED —
see "Keeping this doc honest" below.

## The two registration conventions

Both are asserted mechanically, project-wide, by
`tests/Integration/ModelConventionsTest.php` — this doc cites that test
rather than restating its logic:

1. **Post types**: every model is declared through
   `ntdst_data()->register()` (an `NTDST_Data_Manager` call, which calls
   `register_post_type()` internally, inside `ntdst-core`). **No file in
   `josworld-core` ever calls `register_post_type()` directly.**
2. **Taxonomies**: `dienst` is declared through
   `ntdst_get(\NTDST_Theme::class)->taxonomy()` (which calls
   `register_taxonomy()` internally, inside `ntdst-core`). **No file in
   `josworld-core` ever calls `register_taxonomy()` directly.**

`ModelConventionsTest`'s clause 2 is a token-based static scan of the whole
`josworld-core` plugin directory (not a regex/substring match — proven
immune to both a commented-out call and a call name inside a string
literal), plus clause 1 (every meta key on every registered josworld model
matches `^_jw_`, enumerated from the live registry, not a hardcoded model
list) and clause 3 (the metabox save guard genuinely rejects an unauthorised
POST — no nonce, a forged nonce, or a capability-less user).

### The meta-key rule this table exists to protect (no test enforces it)

**Meta keys (the field name your code reads/writes, before the `_jw_`
prefix) are ALWAYS English. Editor-facing labels (the `label` shown in
wp-admin) are ALWAYS Dutch.** These are independent concerns: a key is a
durable storage/API contract every future YOOtheme builder template reads
directly; a label is UI copy under the Dutch-admin requirement (FR-11).
Conflating them was a real defect fixed in this project (`CaseService`
originally used `wat_we_hebben_gedaan`/`doelgroep`/`uitdaging_*`/
`resultaat_*` as field keys — corrected to English in `f16ad36`). This is a
human decision, not a framework or test-enforced rule — the next service
that adds a field is the one place it can silently regress, which is the
whole reason it is written down here rather than assumed obvious.

## Post type: `case`

Source: `app/content/mu-plugins/josworld-core/services/content/CaseService.php`

The site's primary sales asset. `public`, `has_archive`, slug `ons-werk`,
REST base `cases`, `capability_type: post`. The card is two fields — the
narrative (uitdaging, resultaat, testimonial, team) is built per case in
the YOOtheme builder, not stored as meta.

| Field key | Meta key | Type | Notes |
|---|---|---|---|
| `client` | `_jw_client` | text | required; a fact (`'translate' => false`), never sent to DeepL |
| `hover_image` | `_jw_hover_image` | image | optional; the second image a case grid tile shows on hover |

`title`/`editor`/`thumbnail`/`excerpt` are native WP fields (`supports`),
not meta. A single untabbed metabox — two fields need no tabs.

## Post type: `team`

Source: `app/content/mu-plugins/josworld-core/services/content/TeamService.php`

`public => true`, `publicly_queryable => true` since d3ff09e — the archive
and YOOtheme's source enumeration require the flags, and WordPress has no
"archive yes, singles no" flag. The single-member page is public and built
in the YOOtheme builder, same as the other CPT singles.
`exclude_from_search => true` keeps members out of site search results even
though the single is reachable directly; `has_archive => true` and
`show_in_rest => true` as before.

**REST caveat, worth repeating here because it is counter-intuitive:**
`public => false` does NOT gate REST reads — that is standard WP core
behaviour (the flag gates the front-end query/sitemap/search, not
`WP_REST_Posts_Controller`'s own permission check). `GET
/wp-json/wp/v2/team` returns 200 to an unauthenticated request today. `name`
(post title) and `photo` (featured image) are therefore publicly readable.
`email`/`phone` are NOT currently exposed via REST — but only because no
`register_meta()` call exists for either field anywhere in mu-plugins. The
day someone registers that meta with `show_in_rest => true`, this PII goes
public with no further code change. See
`tests/Integration/TeamRestExposureTest.php`, which documents the current
posture and will go RED the moment it changes.

| Field key | Meta key | Type | Notes |
|---|---|---|---|
| `role` | `_jw_role` | text | required |
| `bio` | `_jw_bio` | textarea | the team-archive card's short bio; read server-side by the template, not REST-registered |
| `email` | `_jw_email` | email | not REST-exposed (see caveat above) |
| `phone` | `_jw_phone` | text | not REST-exposed (see caveat above); a fact (`'translate' => false`), never sent to DeepL |

`title`/`thumbnail` are native (`supports`) — "photo" is the WP featured
image by choice, not a bespoke meta field; the framework's `image` field
type exists and would be the pick if a second team photo were ever needed.

The team members shown on a case are picked in the case's builder layout —
a grid whose items bind one member each via the singular `customTeam`
query — so no reverse relation exists between `case` and `team`.

## Post type: `vacature`

Source: `app/content/mu-plugins/josworld-core/services/content/VacatureService.php`

`public`, `has_archive`, slug `vacatures`, REST base `vacatures`.

| Field key | Meta key | Type | Notes |
|---|---|---|---|
| `employment_type` | `_jw_employment_type` | select (closed list: `full_time`, `freelance`) | required; the closed list is enforced by a `validate` callback, not merely by the `<select>` options — `select` only RENDERS a dropdown, it does not reject an off-list value on save (framework fact, not this project's choice) |

`title` (native) is the vacancy title; `editor`/`post_content` (native, via
`supports => ['editor']`) is the vacancy body — no bespoke `body` meta field
exists for something WP's own content column already models.

## Post type: `tool`

Source: `app/content/mu-plugins/josworld-core/services/content/ToolService.php`

`public`, `has_archive`, slug `tools`, REST base `tools`. The card is
`subtitle`, plus what the tool IS: a downloadable `file` or a `url` to an external
page (`delivery` chooses; the editor shows only the matching field) — benefits and
related tools are built per tool in the YOOtheme builder. `ToolService::targetUrl()`
turns a tool into where the visitor goes; the builder reads it as the `target` field.

| Field key | Meta key | Type | Notes |
|---|---|---|---|
| `subtitle` | `_jw_subtitle` | text | |
| `delivery` | `_jw_delivery` | select | `file` (default, so older tools stay files) or `url` |
| `file` | `_jw_file` | file | optional downloadable file, shown when `delivery` = `file`; native `file` field type, stores the attachment id directly (no relation) |
| `url` | `_jw_url` | url | external link, shown when `delivery` = `url`; http(s) only — anything else is no target |

## Taxonomy: `dienst`

Source: `CaseService::registerDienstTaxonomy()`, registered on `case` ONLY
(no other post type). Non-hierarchical (tag-like) — the design shows a flat,
comma-joined service list per case with no parent/child grouping and no
service-filter UI.

`dienst` is a **taxonomy, not a Data model** — it carries no `meta_prefix`
and is deliberately excluded from the meta-key sweep both in
`ModelConventionsTest` and in this doc's own drift check, because it fails
registry membership (`ntdst_data()->isRegistered('dienst')` is `false`) on
the same terms every model must pass to be counted at all.

## Relations map

No `relation` field remains on any model — `tool.file` moved to the native
`file` field type (7eb21fb), and every card-to-card link (a case's team, a
tool's related tools) lives in the YOOtheme builder instead.

```
case.dienst    (taxonomy terms, case-only, non-hierarchical)
```

Every other relation moved into the YOOtheme builder: a case's team, a
tool's related tools, and any similar link between cards is now a grid
element on the page, binding one manually selected post through the
singular `custom<Type>` query — not a stored meta value.

## Machine-checked meta-key table

<!-- META-KEY-TABLE:START -->
| model | field | meta_key |
|---|---|---|
| case | client | _jw_client |
| case | hover_image | _jw_hover_image |
| team | role | _jw_role |
| team | bio | _jw_bio |
| team | email | _jw_email |
| team | phone | _jw_phone |
| vacature | employment_type | _jw_employment_type |
| tool | subtitle | _jw_subtitle |
| tool | delivery | _jw_delivery |
| tool | file | _jw_file |
| tool | url | _jw_url |
<!-- META-KEY-TABLE:END -->

### Keeping this doc honest

This table is not proofread by eye against the code — it is diffed by
`tests/Integration/DocumentedMetaKeysConsistencyTest.php` against the real
`NTDST_Data_Manager` registry (`ModelConventionsTest`'s own
`josworld_owned_model_slugs()` boundary: registered through `ntdst_data()`
and carrying `JOSWORLD_CORE_META_PREFIX`). If a future task renames a field,
adds one, or removes one without updating this table, that test goes RED —
run `ddev composer test:int -- --filter=DocumentedMetaKeysConsistencyTest`
to see exactly which row is stale. This is a deliberate choice over
generating the table at build time (no build step exists in this repo for
docs, and a hand-editable table stays readable in a PR diff); the assertion
carries the weight a generator would otherwise carry.

## The multilingual DB-state runbook — READ THIS BEFORE TOUCHING POLYLANG

**Polylang is an untracked, WP-CLI-installed dependency, not a code
dependency.** `.gitignore:128` excludes `app/content/plugins/*` wholesale,
and `composer.json` has no `repositories`/wpackagist/`installer-paths`
wiring for it. This means: **a fresh clone or a new deploy environment will
NOT reproduce a working multilingual site from code alone.** Everything
below is DB state (an option row + taxonomy terms — `include/model.php:907`
in the Polylang source), not something `git clone` or `composer install`
restores.

**Since 2026-09-14 the multilingual layer is ntdst-baseline's `polylang`
module**, opted into by `josworld-core.php`'s `ntdst/baseline/modules` filter.
josworld's own `MultilingualService` is gone with it. The difference that
matters for this page: the old service declared the translatable types at
RUNTIME through `pll_get_post_types`/`pll_get_taxonomies`, so a fresh clone
got them for free; the module converges them into DB STATE instead
(`Setup::ensureTypes` writes the `polylang` option), which no deploy carries.
So the list below grew a step rather than shrank — what used to be implicit
in code is now an explicit command per environment.

None of this is optional state: eight pieces are required before the site
renders correctly with Polylang active, and all of them were discovered the
hard way — this is the single most valuable page in this doc.

**The eight steps, in order:**

1. **Install + activate Polylang.**
   ```
   ddev wp plugin install polylang --activate
   ```
   Running 3.8.9. Polylang itself ships no `wp pll` CLI namespace (`wp help
   pll` reports unregistered) — the baseline module's `wp ntdst polylang`
   commands are what makes the next step scriptable.

2. **Converge the languages, the default and the translatable types** — one
   idempotent command, and the step that used to be implicit in code:
   ```
   ddev wp ntdst polylang languages --add=nl:nl_BE,en:en_US,fr:fr_BE \
     --default=nl --post-types=case,team,vacature,tool --taxonomies=dienst
   ```
   Add `--dry-run` first to see what it would change. Verify with
   `wp term list language --fields=slug,name` and by reading `post_types` /
   `taxonomies` out of `get_option('polylang')` — an empty `post_types` there
   means every content type silently lost its language switcher.

   The command needs the module ON; `wp ntdst polylang` reporting "not a
   registered wp command" means the `ntdst/baseline/modules` filter is not
   enabling `polylang`, not that the command is missing.

3. **Assign a `language` term to EVERY existing post/page.** Polylang's
   default `force_lang: 1` + `hide_default: 1` posture means `/` redirects
   to `/nl/`, and **`/nl/` 404s** if the front page (and every other
   existing post/page) carries no language term. This bites immediately on
   any site with content that predates Polylang activation.

4. **Assign a `language` term to `tool` CPT posts too** — easy to miss,
   since `tool` only becomes translatable once step 2 names it. **Any content
   post type added later needs BOTH**: its slug appended to step 2's
   `--post-types`, on every environment, and this backfill. Nothing in the
   test suite catches a forgotten one — the old `MultilingualService`
   constant was pinned to `AdminUIService::POST_TYPES` by
   `PostTypeRegistryConsistencyTest`, and that guard went with the service.

5. **`nav_menu_item` posts need `pll_set_post_language()`, NOT
   `wp post term add`.** The obvious CLI approach —
   `wp post term add <id> language nl` — **fails with "Invalid taxonomy
   language"** on a `nav_menu_item`, because Polylang registers the
   `language` taxonomy's association with that post type dynamically rather
   than through the normal static registration `wp post term add` expects.
   Use the PHP function instead, once per menu item:
   ```
   pll_set_post_language($menuItemId, 'nl');
   ```

6. **The `nav_menu` TERMS themselves also need a language** — a separate
   step from #5 above (menu *items* vs. the menu *term* that contains them).
   `pll_get_term_language($menuTermId)` returns `false` until set:
   ```
   pll_set_term_language($menuTermId, 'nl');
   ```

7. **The `polylang` option's `nav_menus` mapping — the load-bearing one.**
   This is the piece that actually determines whether YOOtheme renders a
   header at all, and it was the least discoverable of the seven. Without
   it, **every** theme-location → menu lookup resolves to `0` (even though
   the DB plainly stores a valid menu term ID against that location),
   `has_nav_menu()` reports `false` for every location, and YOOtheme emits
   `<main>` where `<header>` would be — with no error, no log entry, no
   visible signal beyond "the header is gone." Fix, in Polylang's expected
   shape (`[theme_slug][location][lang] = menu_term_id`):
   ```php
   $opt = get_option('polylang');
   $opt['nav_menus'] = ['josworld' => [
       'navbar' => ['nl' => 2, 'en' => 2, 'fr' => 2],
       'footer' => ['nl' => 3, 'en' => 3, 'fr' => 3],
   ]];
   update_option('polylang', $opt);
   ```
   Substitute the real menu term IDs and theme-location slugs for the
   environment being fixed (`wp menu list` / `nav_menu_locations` theme mod
   for the current values). After this, `composer gate` tier 9 (E2E) went
   from failing on "front page renders a header with a nav landmark" to all
   9 tiers green.

### Translating a record (2026-10-26)

Stefan, 2026-10-26, replacing the 2026-09-15 "one-time copy, nothing follows" rule: "+" on a
Dutch record opens a DeepL draft flagged for review, with the Dutch facts and `dienst` terms
copied once; after that a copy keeps its own files, images and values. Configured in
`josworld-core.php` (`ntdst/baseline/polylang/config`, baseline 2.18):

- `field_sync => 'create'`: facts and terms reach the copy at "+" only; nothing syncs later.
- `translate_copies`: DeepL fills the draft's text; a later Dutch text change re-translates a copy
  nobody edited. Needs `DEEPL_API_KEY` in that environment's `.env`; without it "+" opens a
  flagged Dutch copy and logs one `polylang` warning.
- `follow_status`: unpublishing or trashing the Dutch record does the same to its published copies.
- `fact_titles => ['team']`: a team member's name is never translated.

A `text` field that holds a fact (a name, a number) declares `'translate' => false`.

A copied case keeps only the diensten that have a term in its language: Jos translates the five
`dienst` terms on the Diensten screen in wp-admin, one language at a time, no script (Stefan, 2026-10-26).

### A methodological note: `wp eval` has no request context

`wp eval` runs outside any HTTP request, so `pll_current_language()` returns
`false` there and any menu/location lookup reports raw STORED state, not
RESOLVED state — the two can differ (as they did here: the raw theme mod
correctly stored `navbar: 2`, but every resolved lookup during a real
request came back `0`, because Polylang's own `nav_menus` mapping — not the
theme's — was what was missing). A long investigation session was spent
here inspecting stored state via `wp eval`, which answered a plausible but
wrong question. The moment the probe moved inside a real request (an
mu-plugin hook on `template_redirect`, logging `pll_current_language()` and
the resolved menu location) the root cause was a one-line answer. **For
anything language-, query-, or hook-order-dependent, probe inside a real
request rather than `wp eval`** — `wp eval` fails silently by returning a
plausible wrong answer, not by erroring.

## `ntdst-core` framework fixes shipped from this work

Two real defects in the shared `ntdst-core` framework (not josworld-specific)
were found and fixed while building this content model, and propagated to
the other sites sharing that mu-plugin (`daan`, `edushare`, `todai-client`):

- `95b7e06` — the relation reverse-lookup could not find a `meta_prefix`'d or
  PHP-serialized relation value (this project's `case.team` field is both).
- `135030a` — the relation picker was unusable for attachment-scoped and
  array-`post_type` relations. `tool.file` and `tool.related_tools` have
  since moved off `relation` entirely (native `file` type; builder grid),
  so the attachment-search behaviour that fix touched is now exercised by
  a throwaway fixture model in `tests/Integration/AttachmentSearchTest.php`
  instead.

`ntdst-core` has no test suite of its own — regression cover for both fixes
lives in this project (`tests/Integration/RelationFieldReverseLookupTest.php`
and `AttachmentSearchTest.php`) and travels with the patch to the other
sites, rather than being verified independently at the framework level.
Worth knowing before assuming a green gate here says anything about
the other five sites sharing this mu-plugin.
