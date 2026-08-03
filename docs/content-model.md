# josworld content model

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
REST base `cases`, `capability_type: post`.

| Field key | Meta key | Type | Notes |
|---|---|---|---|
| `client` | `_jw_client` | text | required |
| `tagline` | `_jw_tagline` | text | |
| `what_we_did` | `_jw_what_we_did` | textarea | |
| `target_audience` | `_jw_target_audience` | textarea | |
| `impact` | `_jw_impact` | repeater | sub-fields `figure`, `description` |
| `challenge_title` | `_jw_challenge_title` | text | |
| `challenge_body` | `_jw_challenge_body` | textarea | |
| `result_title` | `_jw_result_title` | text | |
| `result_body` | `_jw_result_body` | textarea | |
| `has_takeaways` | `_jw_has_takeaways` | boolean | UI-only toggle; controls the `takeaways` repeater's `condition`, never gates the save path itself |
| `takeaways` | `_jw_takeaways` | repeater | sub-fields `title`, `body`; optional — absent from storage entirely (no meta row) when empty, not stored as `[]` |
| `testimonial_quote` | `_jw_testimonial_quote` | textarea | |
| `testimonial_name` | `_jw_testimonial_name` | text | |
| `testimonial_role` | `_jw_testimonial_role` | text | |
| `has_interview` | `_jw_has_interview` | boolean | same UI-only role as `has_takeaways` |
| `interview` | `_jw_interview` | repeater | sub-fields `title`, `body`; optional, same absent-not-empty contract as `takeaways` |
| `team` | `_jw_team` | relation (`team`, multiple) | top-level, not nested per impact row — see FR-7 |

`title`/`editor`/`thumbnail`/`excerpt` are native WP fields (`supports`),
not meta.

## Post type: `team`

Source: `app/content/mu-plugins/josworld-core/services/content/TeamService.php`

`public => false`, `publicly_queryable => false` (no single-member page —
the design lists members on `/team/` and embeds them in cases, never links
to a member's own page), but `has_archive => true` (the `/team/` list stays
reachable) and `show_in_rest => true`.

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
| `email` | `_jw_email` | email | not REST-exposed (see caveat above) |
| `phone` | `_jw_phone` | text | not REST-exposed (see caveat above) |

`title`/`thumbnail` are native (`supports`) — "photo" is the WP featured
image, not a bespoke meta field (the framework has no single-image field
type).

**Reverse relation**: `case.team` creates a framework-provided reverse
metabox on a team member's own edit screen listing the cases referencing
them (`NTDST_RelationField` self-registers on `after_setup_theme` and reads
every registered model's `relation` fields). `reverse_label` is set
explicitly on `case.team` (Dutch: "Cases") because the framework's own
default is a hardcoded English string, which FR-11 forbids.

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

`public`, `has_archive`, slug `tools`, REST base `tools`.

| Field key | Meta key | Type | Notes |
|---|---|---|---|
| `subtitle` | `_jw_subtitle` | text | |
| `benefits` | `_jw_benefits` | repeater | sub-field `benefit` (text) — this project's established "list of strings" idiom, since no dedicated list field type exists |
| `file` | `_jw_file` | relation (`attachment`, single) | optional downloadable file; framework has no bespoke file/media field type, so a single-attachment `relation` models it |
| `related_tools` | `_jw_related_tools` | relation (`tool`, multiple) | self-referential by design (a tool CAN relate to itself at the schema level); curated editorially, no same-post exclusion enforced |

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

```
case.team          --> team        (multiple; reverse metabox auto-appears on team's edit screen)
tool.file           --> attachment  (single; optional download)
tool.related_tools  --> tool        (multiple; self-referential, editorial curation, no exclusion)
case.dienst          (taxonomy terms, case-only, non-hierarchical)
```

## Machine-checked meta-key table

<!-- META-KEY-TABLE:START -->
| model | field | meta_key |
|---|---|---|
| case | client | _jw_client |
| case | tagline | _jw_tagline |
| case | what_we_did | _jw_what_we_did |
| case | target_audience | _jw_target_audience |
| case | impact | _jw_impact |
| case | challenge_title | _jw_challenge_title |
| case | challenge_body | _jw_challenge_body |
| case | result_title | _jw_result_title |
| case | result_body | _jw_result_body |
| case | has_takeaways | _jw_has_takeaways |
| case | takeaways | _jw_takeaways |
| case | testimonial_quote | _jw_testimonial_quote |
| case | testimonial_name | _jw_testimonial_name |
| case | testimonial_role | _jw_testimonial_role |
| case | has_interview | _jw_has_interview |
| case | interview | _jw_interview |
| case | team | _jw_team |
| team | role | _jw_role |
| team | email | _jw_email |
| team | phone | _jw_phone |
| vacature | employment_type | _jw_employment_type |
| tool | subtitle | _jw_subtitle |
| tool | benefits | _jw_benefits |
| tool | file | _jw_file |
| tool | related_tools | _jw_related_tools |
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

`josworld-core`'s own `MultilingualService` (T09,
`app/content/mu-plugins/josworld-core/services/multilingual/MultilingualService.php`)
declares the four content post types + `dienst` translatable via Polylang's
`pll_get_post_types`/`pll_get_taxonomies` filters, and creates the three
project languages (`nl` default, `en`, `fr`) via `pll_add_language` — but
only IF Polylang is active (every call is `function_exists()`-guarded, since
absence is the DEFAULT state of a fresh environment, not an edge case). Even
with that service doing its job, seven further pieces of state are required
before the site actually renders correctly with Polylang active. All seven
were discovered the hard way this session (`.superpowers/sdd/tasks/
progress.md`, T09 log) — this is the single most valuable page in this doc.

**The seven steps, in order:**

1. **Install + activate Polylang.**
   ```
   ddev wp plugin install polylang --activate
   ```
   Currently pinned in practice at 3.6.5 (3.8.6 available, not yet
   upgraded/tested here). Ships no `wp pll` CLI namespace (`wp help pll`
   reports unregistered), so language configuration cannot be scripted via a
   dedicated CLI subcommand — either `MultilingualService`'s
   `pll_add_language` calls (once Polylang is active and this plugin's
   boot runs) or the admin setup wizard / `PLL_Admin_Model::add_language()`
   directly.

2. **Create the three languages**: `nl` (default), `en`, `fr`. Verify with
   `wp term list language --fields=slug,name`.

3. **Assign a `language` term to EVERY existing post/page.** Polylang's
   default `force_lang: 1` + `hide_default: 1` posture means `/` redirects
   to `/nl/`, and **`/nl/` 404s** if the front page (and every other
   existing post/page) carries no language term. This bites immediately on
   any site with content that predates Polylang activation.

4. **Assign a `language` term to `tool` CPT posts too** — easy to miss,
   since `tool` only became translatable once `MultilingualService` (T09)
   declared it via the `pll_get_post_types` filter. Any content post type
   added later needs the same backfill.

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
  array-`post_type` relations (`tool.file`, `tool.related_tools`).

`ntdst-core` has no test suite of its own — regression cover for both fixes
lives in this project (`tests/Integration/RelationFieldReverseLookupTest.php`
and the `tool`/`case` registration tests) and travels with the patch to the
other sites, rather than being verified independently at the framework
level. Worth knowing before assuming a green gate here says anything about
the other five sites sharing this mu-plugin.
