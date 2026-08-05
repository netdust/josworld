# josworld — Project State

## Current Phase: content model complete (T01-T12 landed); next is section-by-section page building

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

## The content model (T01-T12, `josworld-core` mu-plugin)

Full reference: **`docs/content-model.md`** — the four post types + their
meta keys + relations + the two registration conventions, and (most
valuably) the seven-step Polylang DB-state runbook below. That doc's
meta-key table is machine-checked against the live registry by
`tests/Integration/DocumentedMetaKeysConsistencyTest.php` — it cannot
silently drift the way a hand-copied table would.

**What's registered** (`app/content/mu-plugins/josworld-core/services/`):
- `content/CaseService.php` — `case` (T05): client/tagline/impact/
  uitdaging/resultaat/takeaways/testimonial/interview + a `team` relation.
  The site's primary sales asset.
- `content/TeamService.php` — `team` (T06): role/email/phone. No public
  single view (`publicly_queryable => false`), but REST-readable regardless
  (WP core behaviour, not a bug — see the doc's REST caveat).
- `content/VacatureService.php` / `ToolService.php` — `vacature` + `tool`
  (T07). Vacature's `employment_type` is a closed list enforced by a
  `validate` callback (the `<select>` render does NOT enforce it).
- `dienst` taxonomy (T08), registered on `case` only, via `CaseService`.
- `admin/AdminUIService.php` — metabox field JS enqueue + T11's admin
  columns and `condition` (conditional-field) layer for `case`.
- `multilingual/MultilingualService.php` — T09, declares all four post
  types + `dienst` translatable to Polylang via its filters, guarded by
  `function_exists()` throughout (Polylang absence is the DEFAULT state of
  a fresh clone, not an edge case — see below).

**Two conventions, enforced mechanically project-wide** (not per-model) by
`tests/Integration/ModelConventionsTest.php`:
1. Every model registers via `ntdst_data()->register()` — never a direct
   `register_post_type()`.
2. `dienst` registers via `ntdst_get(\NTDST_Theme::class)->taxonomy()` —
   never a direct `register_taxonomy()`.
Both are proven by a token-based static scan of the whole plugin directory
(immune to commented-out calls / string-literal mentions), not a hand audit.

**One human-decided rule no test enforces** — meta keys are ALWAYS English;
editor-facing labels are ALWAYS Dutch. `CaseService` originally violated
this (`wat_we_hebben_gedaan` etc. as field keys), fixed to English in
`f16ad36`. Every new field on every model must follow this; it will not be
caught mechanically if it regresses.

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

## Front-end build (2026-08-04/05) — header, footer, and 9 sections on a test page

Everything below is **DB state** (YOOtheme config + page 64 layout) plus the LESS
style. Nothing is committed yet.

**Test page:** `/test-secties/` (page id 64, nl). Section order: hero text · hero
image · logo scroll · lemon two-column · cases · tools CTA · simple CTA · **wie
is Jos (foto+tekst)** · **contact CTA (lemon band, rounded bottom)**. The
reproducible layout JSON is `.yoo-tools/hero.json` (gitignored working dir; also
holds `footer.json` + copies of the skill's `yoo-config.php` / `yoo-content.php`).

**The contact band's "laid over the footer" look is one class.** Section takes
`background_color` + `class: jw-band-round-bottom`; the class rounds only the
bottom corners and paints a `::before` at `z-index: -1` behind the section's own
background, so the notch that opens in each corner shows the FOOTER colour
(`@global-primary-background`, tracked — not `@jw-brown` hardcoded) instead of
the beige page. No wrapper section, no second element in the builder.

**Design screenshots are 1440-frame Figma exports, not 1:1.** The 2026-08-05
screenshot measured 964px of content — scale **0.669**. Confirmed independently:
body line-pitch measured 16px against the known `@jw-t-body` 18/24, and the
container padding measured 53px against the known 80px gutter. Divide every
measurement by 0.669 before treating it as a CSS value. On that basis the
contact band's bottom radius is ~51px (shipped as `@jw-r-xl: 48px`) and the
white card's is ~12px — which is the same signal as the still-open "grey card
16px vs the design's smaller radius" question, now pinned to ONE variable
(`@border-rounded-border-radius`, mapped to `@jw-r-lg`) so both move together.

**Header + footer are live** — logo, toolbar with a Polylang NL switcher, navbar
CTA as a Builder widget, mobile offcanvas; footer is a builder layout in
`config.footer.content` with a `newsletter` element (Mailchimp keys NOT set).

**Theme JS** (`src/`, Vite, 21 unit tests green): `logo-eye.js` (the mark's eye
tracks the cursor, keeps its SMIL blink), `typewriter.js` (hero's rotating line),
and the ported ntdst scroll effects in `src/effects/` + `less/components/
animations.less`. Lenis is wired; **Barba is deliberately NOT ported** — it needs
output-buffer rewriting to inject `data-barba`, which is on the drift list.

**Working rule, learned the hard way:** build with SETTINGS first, then LESS
VARIABLES, then a UIkit `.hook-*()` mixin, and only then a bespoke rule. The whole
build needed just five rules. The traps that cost the most time are written up in
the skill's `lessons.md` (pushed on branch `feat/yootheme-skill-lessons` in
`netdust-plugins`) — most importantly that hand-authored layout JSON MUST carry a
root `"version"` or every element migration silently rewrites props on save.

**Style changes need a browser recompile, and it is automatable** — the Styler's
"Recompile style" button, driven headlessly. The Customizer is at
`admin-ajax.php?action=yootheme&yootheme=customizer`, NOT `customize.php`.

**Open, needs Stefan:** Mailchimp client/list id · real partner logos (only 2
exist, both with asset problems) · the tool-CTA white rectangle is a placeholder
for the real tool shot · social + two credit link targets · whether the grey
card's 16px radius should drop to the design's measured ~12px (now one variable,
`@border-rounded-border-radius`) · **the contact card's portrait is a crop out of
`Team_Adobe RGB-31.jpg` (attachment 121) — the design uses a real Thijs portrait
we don't have** · the contact card's email/phone are placeholders.

## Next

- [ ] **Section-by-section page building** (the agreed next phase after the
      content model): design each section on a section page, then assemble
      pages from them. `less/theme.josworld.less` currently only has
      sections 1–3 (tokens → UIkit mapping → CSS custom properties); no
      per-section component CSS yet. Reference:
      `todai-child/less/theme.todai.less` sections 4–9.
- [ ] Build the actual builder templates/pages that consume the content
      model (`case`/`team`/`vacature`/`tool` — the data layer is done, the
      front-end rendering is not).
- [ ] Turn the DB-only site structure (13 pages, 2 menus, front-page
      options, the 7-step Polylang runbook) into a seed script before first
      deploy — none of it currently travels via `git pull`.
- [ ] Select "Josworld" in Customizer → Theme → Style and eyeball the
      compiled LESS in a real browser (proven to compile via `lessc`, not
      yet visually confirmed).
- [ ] Set the real production domain + remote path in `site.yml`; fill
      Combell SSH aliases.
- [ ] Merge `feat/yootheme-child` once page building + the seed script land.
