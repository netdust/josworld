# josworld — Project State

## Current Phase: CPT singles are builder pages; models are their cards (2026-09-03); next: build the remaining singles in the builder from the library starters

## CPT singles built in YOOtheme (2026-09-03, branch feature/cpt-builder-singles)

CPT singles are builder pages; the models keep only their card fields.

- **Render rule** — `RenderBuilderPage`: a post with a layout in `post_content`
  bypasses its single template. The trimmed `single-*` templates (tool `goYeARw1`,
  vacature `gDbuyeYA`, case, team) are the fallback for posts without a layout.
- **Library starters** — `Single · Case | Tool | Team | Vacature` (type `layout`),
  seeded by `specs/cpt-builder-singles/seed-library.php` (`ddev wp --user=1
  eval-file …`, idempotent by name). `specs/` is gitignored: the DB is the
  artifact — re-seed after a DB refresh. Built samples: `specs/…/pages/*.json`.
- **Never bind YOOtheme's `content` field on a builder page** — the layout IS
  `post_content`, so the binding echoes itself. The vacature starter learned it.
- **Relations live in the builder** — a grid whose items each bind ONE manually
  selected post via the singular `custom<Type>` query (`id`, "Select Manually").
  Placeholder id `-1` renders empty; id `0` shows the FIRST post, not nothing.
- **Team single is public again** — `closeSingleView()` removed, `editor`
  support added; `/team/<slug>/` answers 200 (was 301).
- **Tool `file` is the native `file` type** — the baseline module's relation
  resolver drops non-`publish` posts and attachments are `inherit`. Framework gap
  to raise upstream: core's picker widens to `inherit`, baseline's
  `FieldTypeBridge::$relatedPosts` resolver does not.
- **Card fields kept (8)** — case `client`; tool `subtitle`, `file`; team `role`,
  `bio`, `email`, `phone`; vacature `employment_type`.
- **18 keys removed and purged** (T09, 35 rows → 0): `_jw_tagline`,
  `_jw_what_we_did`, `_jw_target_audience`, `_jw_impact`, `_jw_challenge_title`,
  `_jw_challenge_body`, `_jw_result_title`, `_jw_result_body`, `_jw_has_takeaways`,
  `_jw_takeaways`, `_jw_testimonial_quote`, `_jw_testimonial_name`,
  `_jw_testimonial_role`, `_jw_has_interview`, `_jw_interview`, `_jw_team`,
  `_jw_benefits`, `_jw_related_tools`. Script: `specs/…/purge-removed-meta.php`
  (dry run: `ddev exec 'DRY=1 wp …'` — `ddev wp` drops a host-side env var).
- **Parked** — phpstan-baseline discovery-gap entries (`ntdst_data not found`,
  16 test files): scan `ntdst-core/core/Data.php` and prune; no Repository layer
  (ruled: Service owns its reads); throwaway admin `claude-gate` (id 5) to delete
  at branch close. Acceptance: `specs/…/acceptance-manifest.md` (SC-1..5 pass).

## Project synced to the fleet baseline (2026-09-03)

- **Deploy suite = netdust-wp 1.1.0 + josworld's three fixes.** The template
  now carries the flow floor (`_ensure-flow`, `_need-tty`, `_branch-role`,
  `flow-test.sh`): a rung branch is deploy-only, every flow verb needs an
  origin with the rungs on it, confirmations need a terminal. The template
  still LACKS `set -f` around the deploy.exclude loops, `structure.webroot`
  in setup and the project-named pull dump — re-apply them on every sync
  until they land upstream (marketplace source `plugins/netdust-wp/templates`).
- **This repo has NO `origin` remote.** Every flow verb (feature / finish /
  promote / release / deploy gate) refuses until one exists, and the agent's
  commit guard refuses commits and raw branching on `main`. The promote test
  in deploy-test.sh expects the flow-floor refusal while origin is absent.
- **ntdst-core 5.2.0 (`^5.2`), ntdst-baseline 2.3.0 (`^2.3`).** Core is
  additive (whereGroup/whereMissing/whereNotIn; `redirect()` takes a status —
  the parked TeamService 301 case is now expressible upstream; model hooks
  carry before-state). Baseline: the manual purge door became a resource
  route (josworld has no purge caller) and the opt-in `yootheme` module.
- **The YOOtheme bridge is the baseline module now.** josworld-core opts in
  by assignment on `ntdst/baseline/modules`; the theme's SchemaMapper /
  FieldResolver / YOOthemeSourcesService / cached-meta accessor, seven test
  files, the phpstan stub and the `YOOtheme\app` stub are deleted (unit tier
  112 → 23 tests). Proof: rendered HTML of 13 pages byte-identical before and
  after; the builder schema lists Case/Tool/Team/Vacature with every custom
  field, relations included. The exclude_fields filter kept its
  `ntdst/yootheme/` name (allowed by name in `BaselineFilterNamespaceTest`).
  The 5.0-era accessor/nonce/phpstan-stub traps below are history.
- `.yoo-tools/` (gitignored) refreshed from the 1.1.0 skill: yoo-lint,
  yoo-measure, yoo-recompile, yoo_layout.py + `sections/`, yoo-seed.
- CLAUDE.md rules 2–3 follow the 1.1.0 template (`ntdst-framework`; the
  "daan's copy is HEAD" line is gone — daan is on 5.0.0 / baseline 2.1.0).

## ntdst-core 5.0.0-FINAL consumed (2026-08-24, branch chore/ntdst-core-4)

`e3ce47d` had consumed a PRE-RELEASE 5.0.0 (`3cc96b76`, Aug 21); upstream
re-tagged 5.0.0 at spec-close 261 commits later. The lock now pins
`a818998` — **composer's "5.0.0" was two different trees two days apart;
`git ls-remote --tags` before trusting a version string when upstream is
mid-spec.** Migration spec: `specs/ntdst-core-5-final/` (local-only).
Six commits `e36343a..ff8b6f5`; gate green at every one; FULL panel +
shake-out closed with zero Criticals. Traps for future edits:

- **Metabox nonce action is POST-SCOPED**: `ntdst_save_{model}_{postId}`.
  A nonce minted without the id fails silently — the save is refused and
  the field reads back empty (cost the one red test of the bump).
- **Container eviction is a one-arg rebind**: `ntdst_container()->set($class)`
  clears that ID's resolved cache; `forget()` is gone. Contract pinned in
  `ContainerTest::test_rebinding_clears_the_resolved_cache`.
- **Relation picker**: `GET /ntdst/v1/relation/search` (`search` +
  `post_type[]`), wp.apiFetch transport, permission = declared relation
  targets + target type's `edit_others_posts`. The `ntdst/api_data/*`
  filter family is dead.
- **The YOOtheme bridge accessor** (`ntdst-cached-meta-accessor.php`) reads
  `get_post_meta($id)` + `maybe_unserialize` per value — RAW PREFIXED keys,
  the shape FieldResolver's injection guard needs. It had forwarded to
  `NTDST_Data_Manager::getPostMetaFromCache()`, a method NO core version
  ever had; unit stubs kept it green (`FieldResolverTest` now accepts ONE
  name). Real-body cover: `CachedMetaAccessorTest`.
- **Theme template helpers are DELETED** (zero callers, rotted once
  invisibly). A first template caller writes
  `ntdst_response()->html($slug, $args)` directly; data reaches templates
  as `$args['…']` ONLY (5.0 dropped loose-var extraction — a loose
  `$title` reads a WP query var, silently).
- **phpstan posture**: the packages are invisible to analysis; older call
  sites ride the frozen baseline, a NEW call site into a package class
  needs that class's file in `scanFiles` (a scanned file's
  `function_exists`-guarded declarations ARE reflected — the accessor-stub
  rationale does not generalize). Baseline may only shrink.
- **`window.ntdstAPI` + `josworldConfig.restNonce` deleted** (endpoints
  died with the dispatcher; zero consumers). Theme enqueues go through
  `$theme->on('wp_enqueue_scripts', …)` — `Theme::style()` is gone.
- **`wp rewrite flush` once after deploying this branch** — 5.0 page
  routes are rewrite rules (the DDEV site self-flushed; a fresh deploy may
  not).

**Parked** (fleet/next-session, deliberately not fixed in-branch):
`NTDST_Response::redirect()` hardcodes 302 — josworld's team-single 301
(`wp_safe_redirect` + exit, documented in TeamService) is the first live
case for an upstream `$status` param (ntdst-core-gaps candidate) · theme
JS polish from the branch review: logo-eye's body-wide MutationObserver
never disconnects; typewriter's `x-destroy` listener is dead code; two
comments still cite the deleted main.test.js; text-scroll flattens child
markup · no ARCHITECTURE-INVARIANTS.md yet (two review rounds asked;
author via `/architecture-invariants audit` before the next big spec) ·
`docs/fluent-booking-pro-2.2.1.zip` sits untracked — a paid plugin one
careless `git add .` from the public repo; move or ignore it.

## ntdst-core synced to daan HEAD (2026-08-11, commit fa51984)

The vendored `ntdst-core` now matches daan's copy (the framework HEAD).
What the port changed on the josworld side — the traps for future edits:

- **`find()`/`getMeta()` take a post-STATUS second arg, publish-only by
  default.** `find($id, true)` (the removed skipCache flag) now throws.
  Admin-facing reads (list columns, save-path rereads) must pass `'any'`.
- **Taxonomies are declared WITH the model** via `register()`'s
  `taxonomies` config key (`CaseService` `dienst`). `NTDST_Theme` lost
  `register()`/`taxonomy()`/`apiAction()`/`module()`.
- **The relation autocomplete action is `relation_search`** (login +
  target-type `edit_others_posts`, allow-list derived from declared
  relation targets). `search_posts`/`search_users` are deleted;
  `metabox-fields.js` was re-pointed and its user branch removed.
- **CPT registration is private-by-default** in the new core — all four
  josworld models declare their flags explicitly, so nothing changed here,
  but a NEW model must opt in to `public`/`has_archive`/`supports`.
- phpstan-baseline was regenerated against the new core (framework-only
  entries); php_codesniffer bumped past CVE-2026-67434.
- Three test failures fixed in the same pass were PRE-EXISTING drift, not
  port breakage: the team `publicly_queryable` assertion (stale since
  d3ff09e), employment-type test values (stale since 543a7ec), and
  `team.bio` missing from `docs/content-model.md`.

## ntdst-baseline converged with daan (2026-08-11, 9e123ae here / 18cb043 in daan)

The two vendored copies are now byte-identical and the README states the
policy: **a fix in one copy lands in the other in the same change.**
Superset merge — josworld's hardening went to daan; daan's
`purge_exclude_post_types` (S-2, inert here at default `[]`) and user-enum
doc block came here. Behavioural tests stay where the threat is real
(BaselinePurgeExclusionTest in daan's suite; input-handling and
filter-namespace tests here). daan's dev DB carries a `timezone_string`,
so ProductionReadinessBundleTest's gmt_offset precondition fails against
it — pre-existing, not baseline-related.

## Docblock standard + theme facade (2026-08-11, db4293e + efb5af8)

Docblocks are CONSTRAINTS-ONLY now (~4,400 comment lines → ~1,500):
invariants, tripwires, non-obvious proof techniques, and the pragmas/
annotations the gate needs. No review-finding IDs, no spec-clause
citations, no restated framework internals, no histories — git holds
those. Write new comments to this standard.

The theme routes ALL its wiring through the NTDST_Theme facade
(functions.php chains the CSS enqueue + YOOtheme bridge on the instance;
ViteAssets stores it and uses on()/filter()). Only NTDST_Bootstrap's boot
hooks stay raw — framework boot, not theme wiring.

Assets: NTDST_Theme's config-driven loader is GONE (720399f in daan,
cf9ff59 here — zero consumers, ran empty at 9999 every request). Enqueue
via the explicit `->style()`/`->script()` fluent helpers (thin
wp_enqueue_* pass-throughs deferred to wp_enqueue_scripts; trailing
priority arg — child CSS uses 20 to land after YOOtheme's own). There is
no `assets` config key anymore.

The boundary rule: static assets are `->style()`/`->script()` lines;
anything with a runtime fork (Vite manifest/dev-server split, conditional
payloads) is a hook class called directly —
`(new ViteAssets())->bind($theme)`. ViteAssets is the only such class.
A Theme::bind() forwarder + NTDST_Theme_Hooks interface were tried and
REVERTED same-day (7bf6ce5 daan / 98fb3e6 here): sugar over a one-line
call, one implementer, no polymorphic call site — don't reintroduce.

**Metabox client JS: framework-owned since d226048 (daan) / 1c25594
(here).** metabox-fields.js lives in `ntdst-core/assets/js/`;
MetaboxGenerator enqueues it (`ntdst-metabox-fields`, deps
jquery/jquery-ui-sortable/**wp-api-fetch since core 5.0** — the
ntdst-api handle died with the command dispatcher) on registered-model
edit screens — the theme-services.js probe and josworld-core's private
copy are gone. josworld's AdminUIService keeps only the
conditional-field layer, depending on the framework handle. Contract
test: AdminUIServiceEnqueueTest (against the core's enqueue).

stackedWP install running at https://josworld.ddev.site. `composer gate`: all
9 tiers green (integration 79, unit 18, JS 12 mu-plugins + 5 theme, e2e 3/3).
Admin `/wp/wp-admin` (admin/admin). Production domain + remote path are still
TODO placeholders in `site.yml`.

**Branch: `feat/yootheme-child`, NOT yet merged to main.** Carries both the
YOOtheme child theme foundation (see `memory/ARCHIVE.md` for that phase's
findings) and the full `josworld-core` content model (T01-T12, this session).

Full history: `memory/ARCHIVE.md` (theme-foundation phase) and
`.superpowers/sdd/tasks/progress.md` (the T01-T12 build ledger, including
every mutation-test proof and controller re-verification).

## The content model (T01-T12) — historical; current shape above

Full reference: `docs/content-model.md`. Current shape: 8 card fields, one
un-tabbed metabox per model, team single public — see "CPT singles built
in YOOtheme (2026-09-03)" above.

Traps still true: `ModelConventionsTest.php` enforces `ntdst_data()->register()`
for every model and `register()`'s `taxonomies` key for `dienst` (never the raw
WP function); meta keys are always English, labels always Dutch (human rule,
not mechanically enforced); vacature's `employment_type` closed list needs its
`validate` callback, not just the `<select>` render.

## THE MULTILINGUAL DB-STATE TRAP — read before touching Polylang

**Polylang is untracked and WP-CLI-installed, not a code dependency**
(`.gitignore:128` excludes `app/content/plugins/*`; `composer.json` has no
wpackagist wiring). **A fresh clone will NOT reproduce a working
multilingual site from code alone** — seven pieces of DB state are needed
beyond what `MultilingualService` declares in code. Full runbook + the
load-bearing `wp eval` snippet: `docs/content-model.md` § "The multilingual
DB-state runbook". Short version, in order: install+activate → create
nl/en/fr → language term on every existing post/page (or `/` 404s) → same
for `tool` posts → `pll_set_post_language()` on nav_menu_items (`wp post
term add` fails there) → `pll_set_term_language()` on the nav_menu TERMS →
**the `polylang` option's `nav_menus` mapping**, which is what actually
determines whether YOOtheme renders a header at all (missing it silently
zeroes every menu-location lookup — no error, just no header).

**Methodological note carried into the doc**: `wp eval` has no request
context, so `pll_current_language()` returns `false` there and any
menu/language lookup reports stored state, not resolved state. Probe inside
a real request (mu-plugin on `template_redirect`) for anything language- or
hook-order-dependent — this distinction turned a long investigation into a
one-line answer.

## `ntdst-core` framework fixes shipped from this work

Two real, non-josworld-specific defects were found and fixed in the shared
`ntdst-core` mu-plugin (used by 11 sites) and propagated to daan/edushare/
todai-client: `95b7e06` (relation reverse-lookup couldn't find a
`meta_prefix`'d/serialized value) and `135030a` (relation picker unusable
for attachment-scoped / array-`post_type` relations). `ntdst-core` has no
test suite of its own — regression cover for both lives here and travels
with the patch. See `memory/lessons.md` for the propagation caveats (3
diverged copies across the fleet; verify byte-identity before copying a fix).

## Theme foundation (pre-content-model; unchanged this session)

`josworld` is a thin YOOtheme child (mirrors `todai-client/todai-child`) —
the builder owns rendering, the child carries no template files. Design
system extracted from Figma (`15f2807`): Jos Brown / Raspberry / Lemon /
Beige palette, Fraunces + Inter type. Full findings (LESS compile mechanics,
Customizer exposure, font-face gotchas, the DB-only site structure of 13
pages + 2 menus) archived in `memory/ARCHIVE.md` — still true, just moved
out to keep this file a snapshot.

## The tools archive (2026-08-06) — template `oK_cwk04`, type `archive-tool`

Lives at **`/tools/`**. No slug collision here (the CPT's rewrite slug is `tools`
and the page is `gratis-aanbod`), so this is a template because an archive
belongs in one — not because a page was blocked. One 3-up grid bound to
`tools.customTools`, no offsets: the design shows a single grid with no CTA
splitting it, so there is nothing for cumulative slices to solve.

**`tool` supports only title + editor — there is NO featured image.** That is why
the design draws checkerboard placeholders, and why the grid ships
`show_image: false`: turning the slot on renders an empty box on every card.
Giving tools an image means adding thumbnail support to the model — code, which
Stefan has ruled out for now.

Six sample tools seeded by `.yoo-tools/seed-tools.php` (same `_jw_sample = 1`
contract as the cases seeder), using the design's own titles and subtitles so the
comparison against the screenshot is meaningful. A seventh card, `testDraft`
(post 44), is a real pre-existing published tool, not seed data.

Not built: the decorative lemon shape in the CTA (no asset supplied).

## A LONE COLUMN FILLS ITS ROW — `alignment` is what releases it

`row/templates/template.php:26` emits `tm-grid-expand {!alignment}`, and
`.tm-grid-expand > * { flex-grow: 1 }`. So the class is present only while the
row has NO `alignment`, and while present it expands a single column past its own
`uk-width-*`. The symptom is nasty because the markup looks right:
`uk-width-1-2@m` sits on the column in the DOM and the column still measures full
width. Setting the row's `alignment` — even `left` — removes the class and the
width binds. C3's 850px measure works for exactly this reason (`alignment:
center`), so the note above about `2-3` was always half the story.

## TEXT SIZES GO THROUGH THE FOUR NATIVE ROLES — never a bespoke class

**Corrected 2026-08-06 after Stefan asked twice for smaller type.** UIkit ships
FOUR named text roles — `lead`, `large`, `small`, `meta` — and each one is BOTH a
"Text Style" dropdown on the text element AND a Styler-exposed variable family
(`@text-lead-*`, `@text-large-*`, … — see `packages/styler/config/styler.php`,
which also exposes `@accordion-default-title-*`, `@base-h*`, `@button-*`).

Out of the box `lead` and `large` BOTH resolve to `@global-large-font-size`
(40px), nowhere near the design's 24 and 32 — and this build had answered that by
writing `.jw-lead` / `.jw-intro` classes into the CONTENT HTML. That is the wrong
fix and it is why a size tweak kept costing a LESS edit + browser recompile + a
round trip: **a class typed into content is invisible to the builder and to the
Customizer**, so the client can't change it and neither can a dropdown.

Both classes are now DELETED. `@text-lead-*` maps to 24/1.33/-0.02em and
`@text-large-*` to 32/1.25/-0.05em (the latter's letter-spacing via
`.hook-text-large()`, since `large` has only size + line-height as variables
while `lead` carries a full typo surface). Verified identical computed output to
the classes they replaced. The three usages migrated: hero → `uk-text-lead` in
its raw markup (it is an `html` element, so no dropdown exists), H3 header →
`text_style: "large"`, A3 card → plain body.

`small` (14) and `meta` are deliberately left at UIkit defaults — several built
sections already use `text_style: "small"` against that value.

**The rule going forward:** if a text size needs changing, it must be reachable
as an element dropdown or a Styler field. Reach for a class only when no role
fits (`.jw-disclaimer` at 12px is the one survivor), and say so out loud.

**Working rule, learned the hard way:** build with SETTINGS first, then LESS
VARIABLES, then a UIkit `.hook-*()` mixin, and only then a bespoke rule. The whole
build needed just five rules (nine by the end of the section run). The traps that
cost the most time are written up in the skill's `lessons.md` — most importantly
that hand-authored layout JSON MUST carry a root `"version"` or every element
migration silently rewrites props on save.

**THERE ARE TWO CLONES OF `netdust-plugins`, AND ONLY ONE IS REAL.**

| path | what it is |
|---|---|
| `~/.claude/plugins/marketplaces/netdust-plugins` | **the working clone** — has the `origin` remote, sits on `feat/yootheme-skill-lessons`, netdust-wp 0.7.0. Edit skills HERE. |
| `~/Projects/netdust-plugins` | a stale second clone — `main`, netdust-wp **0.4.1**, yootheme untouched since 2026-06-09. Looks authoritative and is not. |

Reading only the `~/Projects` clone on 2026-08-05 produced a confident, wrong
conclusion that a session's skill work had been lost. It had not — it was
committed on the branch in the marketplace clone all along. **Check which clone
has the remote before concluding anything about the plugins.**

Genuinely cache-only and now committed (`1cdcf36`, pushed): the
`getMeta()` 8 KB limit + `Style:` variant blocks in
`references/yootheme-less.md`, plus this session's lessons additions.

**RESOLVED 2026-08-06 — the root cause was a version mismatch, now fixed on main.**

`plugins/netdust-wp/.claude-plugin/plugin.json` said `0.7.0` while the repo's
`.claude-plugin/marketplace.json` still said `0.6.0` — the only plugin of the four
whose two declarations disagreed. That left several extracted cache snapshots, and
they did not hold the same skill: `0.6.0` carried the recovered ntdst-yootheme
(17 KB SKILL.md, 25 KB lessons.md, six references) while `0.7.0`, built from
`main`, carried the pre-recovery one (8.9 KB SKILL.md, an **empty** lessons.md,
two references).

Fixed by merging `feat/yootheme-skill-lessons` into `main` (fast-forward, five
commits) and bumping BOTH declarations to **0.7.1** (`f5f65aa`, pushed). `main`
now carries the full skill and the two versions agree.

**The risk was smaller than it first looked, and the reason is worth keeping:**
resolution goes through `~/.claude/plugins/installed_plugins.json`, which pins an
`installPath` — netdust-wp was pinned to `0.6.0`, so the regressed `0.7.0` tree
was never actually served. Read `installed_plugins.json` before concluding
anything from cache directory names; a version sitting in `cache/` is not
necessarily a version in use.

**Still to do (a `/plugin` action, not scriptable):** update netdust-wp to 0.7.1
so the install stops pointing at the 0.6.0 snapshot. Do NOT hand-delete cache
directories — several are marked `.in_use` by live `claude` PIDs, and Claude Code
orphans and GCs them itself (`.orphaned_at`).

**Style changes need a browser recompile, and it is automatable** — the Styler's
"Recompile style" button, driven headlessly. The Customizer is at
`admin-ajax.php?action=yootheme&yootheme=customizer`, NOT `customize.php`.

**Open, needs Stefan:** the C8 FAQ copy — five questions + answers are written
placeholders (plausible Dutch, not client-supplied) · **Thijs's role in the A3
card is a literal `[functie]` placeholder** (it should eventually bind to the
`team` CPT's `role` field rather than be typed) · **A3's email/phone links render
underlined where the design draws them plain raspberry** — that is
`@base-link-text-decoration`, a global call, so it was NOT changed for one
section · Mailchimp client/list id · real partner logos (only 2
exist, both with asset problems) · the tool-CTA white rectangle is a placeholder
for the real tool shot · social + two credit link targets · **the radius question
now has three independent measurements and they agree: the design draws ~12–13px
where the token says 16** (contact card ~12, service card ~13, and the earlier
"~6px" reading was taken before the 0.669 scale was known, i.e. ~9). Dropping
`@jw-r-lg` to 12px moves every rounded panel at once — cards, tiles and the
column "Round corners" checkbox all resolve through it · **the contact card's portrait is a crop out of
`Team_Adobe RGB-31.jpg` (attachment 121) — the design uses a real Thijs portrait
we don't have** · the contact card's email/phone are placeholders ·
**the review slider's client logos are the existing `logo-*` attachments, which
are photos, not logos** — the same asset gap as the logo wall, now visible in two
places.

## Next

- [x] **Section-by-section page building** — the workflow is running.
      **Waarom Jos? (page 12) is the first assembled page**, built
      2026-08-07 from five library sections. Two stale claims corrected
      while doing it: `theme.josworld.less` has had a full **section 4** of
      component CSS since 2026-08-04 (it was uncommitted, now committed),
      and "Josworld" **is** selected in the Customizer — `theme.1.css` had
      already been compiled in the browser on 2026-08-06.
- [x] **Voor overheden (17) and Voor social profit (18) are built too**
      (2026-08-07). Both Figma frames are structurally identical — 1440×10904,
      the same 13 bands, only the copy differs — so both assemble from the
      same section list: `H3 · L1 · C3 · C4 · W1 · A5 · C4 · C5 · C4 · C6 ·
      C7 · C8 · A3`. `C4` appears three times per page at different column
      counts (overheden 4/3/3, social profit 3/3/2) — a `grid_large` setting
      on each copy, not three sections. `H3` turned out to be already written
      for the overheden page; only its right-hand column was lorem.
- [x] **The homepage (page 11, `page_on_front`) is built too** (2026-08-07).
      Nine bands, every one an existing section: `H1 · H2 · L1 · C1 · W1 ·
      A1 · A2 · C2 · A4`. Two things worth knowing next time: the Figma layer
      called "Free offer" on the HOMEPAGE frame is **not** the download gate
      the doelgroep pages use — its copy is "Bekijk onze gratis tools &
      downloads", i.e. `A1 · Tools CTA` verbatim. And `H1`'s library version
      is a viewport-height text hero; on the homepage the text band is only
      ~190px and the photo band below carries the height, so the viewport
      lock is dropped on the page copy.
- [x] **Fixed a real bug in `C1 · Voor wie`**: BOTH columns linked to
      `/voor-overheden/`. The social-profit column now points at
      `/voor-social-profit/` on the homepage copy. **The library section
      still has the bug** — fix it there before `C1` is reused again.
- [ ] **The logo wall repeats 2 logos.** `L1` renders correctly (grayscaled,
      parallax marquee, verified in-browser) but only `logo-nrc` and
      `logo-actiris` exist, so they alternate ~6× across the band. The design
      shows 7 distinct client logos. Same asset gap already noted for the
      review slider.
- [x] **TRAP — a row's column COUNT must match its `layout` string.** Cost a
      wrong build on the team header. A `layout: "1-2,1-2"` row with only ONE
      column makes YOOtheme stamp `tm-grid-expand`, and
      `.uk-grid.tm-grid-expand > * { flex-grow: 1 }` beats the column's own
      `uk-width-1-2@m` — it stretches to the full width and the design's
      half-width column runs edge to edge. Dropping the `layout` prop is NOT
      the fix: the row then defaults to `uk-child-width-1-1` and stays full
      width. Supply the empty second column. An empty column is meaningful.
- [x] **TRAP — a percentage `position_top` on an absolutely-placed element
      silently does nothing.** Its containing block is a `.uk-panel` with
      height ZERO, so `top: -18%` and `top: -83%` both compute to `top: 0px`
      and the element never moves. `position_left` DOES work, because width
      is non-zero. Use px for the vertical. Cost two wasted rounds on A6's
      sun; the tell is the computed `top` reading `0px` while the authored
      value is a percentage.
- [x] **Every section needs a `name`.** Sections authored without one list as
      a bare "SECTION" in the builder and cannot be told apart. The library
      ones all carry `C8 · FAQ`-style names; new ones must too.
- [x] **The team archive is a TEMPLATE, not a page** (2026-08-07).
      `archive-team` (id `RMMlzZVy`, "Team — archief"), 5 sections: header ·
      dynamic 3-col grid · jobs CTA · `C8 · FAQ` · `A4 · Contact CTA`.
      Its two STATIC sections DO live on page 64 — `H5 · Header — tekst`
      (eyebrow + H1 + intro at half width, distinct from `H3 · Header —
      pagina`, which is a 3-5/2-5 split with an image beneath) and
      `A6 · Jobs CTA`. Page 64 is at 30 sections and the **YOOtheme Library
      at 8 items** — both new sections are saved there too, not only on the
      test page. Only the grid stays out,
      because only it is data-driven.
      **The pattern to copy for any future archive:** an empty page holds the
      slug, the CPT rewrites to that same slug, and the archive shadows the
      page. `url_to_postid('/team/')` and `('/ons-werk/')` both return 0.
      **Binding shapes, both verified against source rather than guessed:**
      the plural query is `<base>.custom<Base>` — `teams.customTeams` — from
      `CustomPostQueryType` (`custom{$base}`, `$base =
      camelCase(Helper::getBase($type))`); and custom fields bind by BARE
      schema key (`bio`, `email`), because `SchemaMapper` emits
      `$mapped[$field]` at the object type's top level. A `mailto:` link
      needs no computed field — `{"name":"email","filters":{"before":"mailto:"}}`.
- [x] **`team` is now `public => true`** and its single view is closed by a
      301 in `TeamService::closeSingleView()` (NTDST_Router). It was the only
      CPT YOOtheme could not see; `case`/`tool`/`vacature` were all already
      public. **There is no core flag for "archive yes, singles no"** —
      `WP::parse_request()` drops the `post_type` query var for a
      non-`publicly_queryable` type, taking the archive with it. Added a
      `bio` field too: the archive card shows a bio, NOT the functie.
- [ ] **8 team members are seeded with the DESIGN's copy** (names + bios
      from frame 625:12239). Still placeholder: every `role` is `[functie]`,
      every e-mail is `mail@jos.agency`, and 7 of 8 portraits are a
      checkerboard `placeholder-portrait.png` (attachment 182) — only Thijs
      has a real photo. **Two typos are reproduced verbatim from the design
      and left uncorrected on purpose** — "tusen" (Thijs) and
      "admistratieve" (Fatima). They are client copy; the fix is theirs.
- [ ] **Next pages** assemble the same way. The **YOOtheme Library**
      (`wp_options.yootheme` → `library`) now holds 6 items: `A3 · Contact
      CTA`, `A5 · Gratis aanbod — download`, `C7 · Ons team`, `C10 · Cijfers
      — band`, `C11 · Kaartenstapel`, `H4 · Hero — gelaagd`. **Inserting a
      library item COPIES it** — there is no back-reference, so fixing an
      item never reaches pages already built from it. Get a section right
      BEFORE saving it. Saving whole PAGES as library items is the agreed
      next step, not done yet.
- [ ] **The download gate on both doelgroep pages is a PLACEHOLDER.**
      `A5 · Gratis aanbod — download` is A1's tile shell (sun + tool card)
      with the design's 4-field form (Voornaam / Naam / Organisatie /
      E-mail + Download) rendered as a marked text block, exactly how `A3`
      handles its FluentBooking agenda. **No form plugin is installed** —
      only `fluent-booking`, `fluent-booking-pro` and `fluent-smtp` are
      active; FluentForms is absent and `fluent-crm` is inactive. Wiring it
      needs a form plugin + file delivery + probably CRM tagging.
- [ ] Build the actual builder templates/pages that consume the content
      model (`case`/`team`/`vacature`/`tool` — the data layer is done, the
      front-end rendering is not).
- [ ] Turn the DB-only site structure (13 pages, 2 menus, front-page
      options, the 7-step Polylang runbook) into a seed script before first
      deploy — none of it currently travels via `git pull`. **Related:**
      `specs/` is not whitelisted in `.gitignore` (the root rule is `/*`
      plus 54 `!` lines), so the layout JSON under
      `specs/waarom-jos-page/` is local-only. One `!/specs/` line would
      track it — deliberately NOT added unilaterally.
- [ ] **`composer gate` is RED at HEAD, and was before this page was
      built** — 3 phpstan errors in the YOOtheme sources bridge, none of
      them touched by the page work: `nullCoalesce.offset` and an
      `arguments.count` in `YOOthemeSourcesService.php` (`:629` calls
      `SchemaMapper::toSubObjectTypes()` with 4 args where it takes 2–3 —
      a real bug), plus `method.alreadyNarrowedType` in
      `tests/Unit/Yootheme/FieldMetadataTest.php`. Belongs to the
      `yootheme-sources-baseline` spec, parked deliberately.
- [ ] Set the real production domain + remote path in `site.yml`; fill
      Combell SSH aliases.
- [ ] Merge `feat/yootheme-child` once page building + the seed script land.
