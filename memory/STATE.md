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

**Test page:** `/test-secties/` (page id 64, nl), **organised into labelled
groups** — each group opens with a muted `▸ LABEL` band (a section, not CSS), and
every section carries a group-prefixed builder name so the builder tree reads in
the same order as the page:

| Group | Sections |
|---|---|
| ▸ HEADERS | `H1 · Hero — tekst` · `H2 · Hero — beeld` · `H3 · Header — pagina` |
| ▸ LOGO'S | `L1 · Logo scroll` |
| ▸ CONTENT | `C1 · Voor wie` · `C2 · Wie is Jos` · `C3 · Diensten — kaarten` · `C4 · USP's — kolommen` · `C5 · Reviews — slider` · `C6 · Waarom Jos — beeld + cijfers` · `C7 · Ons team — beeld + label` · `C8 · FAQ` |
| ▸ CASES | `W1 · Cases` |
| ▸ CTA'S | `A1 · Tools CTA` · `A2 · CTA — tekst` · `A3 · Contact CTA` |

`A3 · Contact CTA` must stay LAST — it is designed to sit over the footer.
New sections go at the end of their group; add a `▸ LABEL` band for a new group.

The reproducible layout JSON is `.yoo-tools/hero.json` (gitignored working dir;
also holds `footer.json` + copies of the skill's `yoo-config.php` /
`yoo-content.php`). Keep it in sync after every `page set` — it is the only copy
of the layout that is not DB-only.

**`A3 · Contact CTA` was REBUILT (2026-08-06) around a booking tool.** It was
headline-left / contact-card-right in one row; it is now three stacked rows:
centred T1 headline + body subline → the booking card → `Liever bellen?` (T2)
beside the existing contact card. Still last on the page, still
`.jw-band-round-bottom`.

- **The booking tool is FluentBooking** (Stefan's pick; he installs it). The card
  is a `2-3` column centred — the same 850-wide shape as C3 — holding ONE `text`
  element whose content is a placeholder string. **That element is the entire
  swap surface**: YOOtheme runs `do_shortcode` over builder text content
  (`Builder\Wordpress\Listener\ApplyContentFilter`), so when the plugin lands the
  placeholder becomes the shortcode and nothing else changes. No html element,
  no PHP.
- `.jw-booking-embed > .uk-tile { min-height: 560px }` is the section's one rule
  and is NOT scaffolding — a booking calendar sizes itself after its iframe
  loads, and this band sits directly on the footer, so without a floor the page
  jumps ~560px late. Note the selector: the `class` prop lands on the column
  WRAPPER, the visible white box is the inner panel div that carries `uk-tile`.
- `block_align: right` on the card portrait was replaced by `text_align: right`
  on its COLUMN — the old prop was inert (no `maxwidth`), i.e. the trap already
  written up under C7 was live in this section.
- **This design could NOT be measured** — it was pasted from the clipboard, so no
  file reached `/mnt/c/Users/stefa/Pictures/Screenshots/`. Built from the
  established patterns instead and corrected off the render. Ask for the file if
  a section needs real numbers.

**The contact band's "laid over the footer" look is one class.** Section takes
`background_color` + `class: jw-band-round-bottom`; the class rounds only the
bottom corners and paints a `::before` at `z-index: -1` behind the section's own
background, so the notch that opens in each corner shows the FOOTER colour
(`@global-primary-background`, tracked — not `@jw-brown` hardcoded) instead of
the beige page. No wrapper section, no second element in the builder.

**`H3 · Header — pagina`** is the subpage header: eyebrow (body 18px, NOT the
`small` eyebrow the other sections use) + `h1` at T1/76px on the left, a
`text_style: large` lead (32/40) on the right, both columns `vertical_align: bottom` so
the lead's last line sits on the headline's last baseline — that bottom-align is
the whole trick, and it is a column setting, not CSS. Below it a second row with
a container-width image cropped 1280×442 (~2.9:1).

**`C3 · Diensten — kaarten`** is the muted full-width band whose white blocks are a
real `grid` (1 column at every breakpoint, `grid_row_gap: medium`) so the client
adds a block by adding a `grid_item` — image, title, content, link and link text
are all per-item. Three settings carry the look and are easy to miss:
`panel_style: card-default` (the white panel), `panel_padding: large`, and
`content_align: true` — "Force left alignment", which is what keeps the BODY copy
left while the column's `text_align: center` centres the image, title and link.
The 850px content width is a single `2-3` column plus row `alignment: center`;
`uk-container-small` (900) is the near-miss you will otherwise reach for.

**`C4 · USP's — kolommen`** is the same grid idea with no panel at all — title +
content only, `grid_default/small/medium/large = 1/2/2/4`, so the column count is
one dropdown (`grid_large`) and everything else follows. Needed ZERO LESS: the
default `uk-grid` gutter at ≥1200 (40px) already reproduces the design's 326px
column pitch, and the titles are `title_style: h4` = the T4 Inter-600 token.

**`C7 · Ons team — beeld + label`** — container-width image, then a `3-4,1-4` row
with the copy left and the Actiris label right. No LESS.

- **`block_align` on an image is a NO-OP unless `maxwidth` is also set** — its
  `enable` is literally `position != 'absolute' && maxwidth`, and it aligns the
  element only "in case the container exceeds the element max-width". It writes
  into the layout JSON regardless and silently does nothing, which reads as
  "right-align is broken". Use the COLUMN's `text_align` instead; the image is
  inline so it follows.
- The label is one asset — mark AND the two caption lines are baked into
  `actiris diversiteitslabel.png` (560×480, uploaded full-res as attachment 136;
  the 350px `logo-actiris.png` used by the logo wall is the same art, smaller).

**`C6 · Waarom Jos — beeld + cijfers`** — muted band, image + text row, then a
3-up stat grid whose items each open with a rule. Two traps:

- **`@jw-line` is invisible on a muted section.** `@jw-line` IS `@jw-brown-10`,
  which is also `@global-muted-background` — so the obvious border colour is
  exactly the band it sits on. Un-blending the design's antialiased 1px rule
  gives ~`#35070c`, i.e. full `@jw-primary`. Check the two before reaching for
  `@jw-line` on any muted band.
- **`grid_divider` is not this.** It reads like the setting for a rule per stat
  and draws VERTICAL lines BETWEEN columns instead. A `border-top` on `.el-item`
  is the whole of it (`.jw-stat`).
- The design draws the stat rules FLUSH with the photo's bottom edge (measured
  675 vs 674) and it was built that way — **Stefan overrode it on 2026-08-06**:
  the rules touching the photo read as a mistake on screen even though it is
  what the design shows. The stat row is now `margin_top: "default"` (24px).
  Don't "restore" the flush version from the measurement.
  Number = `meta` at `meta_style: h3`; label = `title` at `h4` with the size
  pulled back to body, since `h5` is the right 18px but reverts to Fraunces.

**`C8 · FAQ` cost ONE rule, and the reason is worth keeping: the "rule between
items, none above the first or below the last" look IS the master theme's
default accordion.** `@accordion-default-item-mode` already defaults to
`divider`, whose hook is literally `&:nth-child(n+2) { padding-top: …;
border-top: … }` — so the whole list is four variables
(`@accordion-default-item-border-width/-border/-item-margin-top` + the title
type). Do NOT reach for a bespoke `.el-item { border-bottom }`; that is the
shape people rebuild in CSS here. The single rule needed is the ICON: UIkit
injects a hardcoded `<svg width="13" height="13">` for `uk-accordion-icon` (a
plus whose bars rotate into a minus on open — exactly the design's behaviour,
just a third of the size) and there is no size variable. Sized `1em` so it
tracks `@accordion-default-title-font-size` instead of needing a paired edit.

Three more things from that section:

- **The divider spends the rhythm as `margin-top` + rule + `padding-top`**, both
  from the SAME variable, so `@accordion-default-item-margin-top` is HALF the
  gap between two questions. Item pitch = 2 × that + 1 + one title line.
- **The design's divider is FULL ink, not `@jw-line`.** Un-blending the
  antialiased 1px rule gives `#320610` — same finding as C6's stat rules, now
  seen twice. On this beige band the light tint would have been nearly invisible.
- **The design draws the question at 24px; it ships at 22 (T4).** Stefan asked
  for smaller titles and a smaller left button after seeing the first pass —
  the button is `button_size: "small"` (14/36, the same size W1 · Cases uses),
  not a LESS change. So this section deliberately departs from the measurement.

Layout: `2-5,3-5` at `column_gap: large`. The design's split is 464 / 80 / 736,
and UIkit's gutters here are 16/32/64 — no 80 — but `large` (64) lands the right
column at 733 rendered against 736 measured. Bumping `@global-large-gutter` to
80 to be exact would silently move the TEN other rows already using
`column_gap: large`; 3px is not worth that.

**`C5 · Reviews — slider` is the ONE section so far that genuinely needed CSS**,
and the two things it needed are worth knowing:

1. **A YOOtheme panel cannot put the image beside the title while the content
   runs full width.** `image_align: left` moves the CONTENT into the right column
   too. The fix is to KEEP `image_align: top` — that is the setting which leaves
   image/title/meta/content as SIBLINGS of `.el-item` — and re-lay them out with
   `display: grid` in `.jw-review`. Switching that dropdown to "left" wraps them
   in YOOtheme's own grid and silently breaks the rule. The image child is a
   `<picture>`, not the `<img>`; `.el-image` is on the inner element.
2. **The right-bleed needs THREE settings that only work together**: section
   `width: ""` (no container at all), row `width: "default"` +
   `width_expand: "right"`, and on the slider element
   `container_padding_remove: true`. With the section container left in place you
   get a NESTED container — UIkit zeroes the inner one's padding, `expand-right`
   computes its max-width off the wrong 50%, and the whole row shifts ~70px
   right while still not bleeding. That failure looks like "expand-right is
   broken"; it is the nesting.

3. **UIkit's margin utilities are `!important`.** The builder puts
   `uk-margin-top` on `.el-title`, `.el-meta` AND `.el-content`, and
   `.uk-margin-top { margin-top: … !important }` — so a plain `margin: 0` loses
   no matter how specific the selector is. That 24px is what held the role a
   full line below the name. With it gone the pair sits flush and grid's equal
   split of the avatar's leftover height centres the two lines as a unit. **Any
   future `.el-*` margin override in this theme needs `!important` for the same
   reason** — it is not a shortcut, it is the only thing that works.
4. **The cut-off card fades into the lemon via a gradient on the TRACK WRAPPER**
   (`.jw-review > .uk-position-relative::after`), not an opacity on the last
   item — which item is cut changes as the slider moves, so `:last-child` would
   fade the wrong card after one click. Gated to ≥960px: below that the slider
   shows one full card and the fade would wash out the card being read.

Also: `slidenav_breakpoint` defaults to `xl` (≥1600), so the arrows are simply
absent at 1440 until you lower it. Set to `m`. And `slider_finite: true` greys
the prev arrow on slide 1 and collapses the dotnav to 3 dots for 5 items —
`false` is what matches the design.

**Cards were flat until this section and are now variable-driven.** The master
theme exposes `@card-border-radius` (border-radius/card.less) and
`@card-default-box-shadow` (box-shadow/card.less) — both were the UIkit defaults
(0 / none). Set to `@jw-r-lg` + `@jw-shadow-md`, plus
`@card-large-body-padding-{horizontal,vertical}-l: @jw-s-16` for the design's
~60px card inset. All four are variables; no card rule exists.

**Design screenshot scale is NOT constant between screenshots.** The 09:44 one
was a 1440 frame at 0.669; the 11:21 one was effectively **1:1** (1449px carrying
1288px of content against the known 1280 container). Re-derive the scale on every
screenshot from the container gutter (80px) and the body line-pitch (24px) before
trusting a single measurement — see below.

**Design screenshots are 1440-frame Figma exports, often not 1:1.** The 09:44
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

## The cases archive (2026-08-06) — first DYNAMIC page, and it is a TEMPLATE

`/ons-werk/` is the `case` post type's ARCHIVE, not page 15. The CPT registers
`rewrite.slug => 'ons-werk'` and `has_archive => true`, which is the *same slug*
as the existing "Ons werk" page — and the archive wins the rewrite. Building the
layout on page 15 produced a page that renders nowhere. It lives in a YOOtheme
template instead: **id `Hhoi7Wz0`, type `archive-case`** (the first template on
this site; `template list` was empty before). Page 15 was left empty, as it was.

**Seven grids split by two CTA bands, each grid a different SLICE of one query.**
The binding lives on the single `grid_item` — NOT on the grid — and the item then
repeats over the query. Shape lifted from the Oakville demo package rather than
guessed:

```json
{"type":"grid_item","source":{
  "query":{"name":"cases.customCases","arguments":{"offset":1,"limit":2,"order":"date","order_direction":"DESC"}},
  "props":{"title":{"name":"title"},"meta":{"name":"client"},
           "image":{"name":"featuredImage.url"},"link":{"name":"link"},
           "content":{"name":"dienstString"}}}}
```

Query name = `<rest_base>.custom<RestBase>` → `cases.customCases` (confirmed by
introspecting the live schema, not inferred). Offsets are CUMULATIVE — 0/1/3/5/6/
8/10 against limits 1/2/2/1/2/2/2 — so changing any grid's `limit` shifts every
later `offset`. Source of truth: the generator, `.yoo-tools/` + the build script.

**`panel_link` is the setting that solves two problems at once.** It needs
`show_link: true` to be enabled at all (`'enable' => 'show_link'` on the field),
and then `template-link.php` runs `striptags()` over title/meta/content because
nested `<a>` is invalid HTML. That is what flattens `dienstString` — which is
WP's `get_the_term_list()`, i.e. `<a rel="tag">` LINKS — into the plain
"Dienst, dienst & dienst" line the design draws, while making the whole card a
link to the case. Blank `link_text` to suppress the "Read more" button
(`template-link.php` only emits it `if ($link && ($props['link_text'] ||
$element['link_text']))`). No CSS, no field change.

**The page header is Inter 32/40 — the Intro role, NOT a Fraunces heading.**
Measured off the export (cap 23.8 and x-height 18.0 both resolve to ~32px, line
pitch 40). Built as `title_element: "h1"` + `title_style: "text-large"`, which is
a real option on the headline element — so the archive keeps a true `<h1>` while
taking the Intro type. This only works because `@text-large-*` now sets the
family (see below); without it the class lands on an `<h1>` and Fraunces wins.

**12 SAMPLE cases are seeded and they are throwaway.** `.yoo-tools/seed-cases.php`
creates them with `_jw_sample = 1` so the whole set comes back out in one
command; the file documents it. Only five real case photos exist, so images
repeat. Dates are spread one day apart because `order: date` is what makes the
offsets a stable sequence.

**A one-item grid is a PANEL** (Stefan, 2026-08-06 — "more transparent"). The two
1-ups are `panel` elements now: same bindings, but the single query
`cases.customCase`, whose resolver is `getPosts(['limit' => 1] + $args)` — so it
honours `offset` and the cumulative sequence is unchanged. Card meta also moved
from body 18 to the `text-small` role (14) — it read too large.

**THE DOMAIN PILL IS DROPPED — decided 2026-08-06, do not re-propose it.**
Stefan: "i don't want any code written for this." The card ships as client +
title + plain services.

Why it would have needed code, so the question is not reopened by accident: a
`grid_item` / `panel` exposes exactly THREE text slots (meta, title, content)
and the design's card carries FOUR values — client, title, domain-pill,
services. There is no domain field on the case model either. And `panel_link`,
which makes the whole card clickable, runs `striptags()` over all three slots,
so `<span class="uk-label">` inside `content` is deleted before it renders. Every
route to the pill therefore costs either the client line, the whole-card link, or
a taxonomy + a custom source field.

**The process lesson matters more than the pill** (`memory/lessons.md`): this was
mis-classified as harnessed-development Class A, which mandates spec + plan +
tasks + six gate headings — so a `## Threat model` got authored for a layout
change, with `N/A` as its honest content. It is Class E work at most. Turn the
class dial before reaching for ceremony.

The logo wall, contact band and newsletter below the grids are not built (Stefan
asked for header + grid).

Design export scale for `jos.png` was **1.7195** (2475px frame; gutter 137 → 80,
content 2201 → 1280 — the two knowns agreed).

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
- [x] **Every section needs a `name`.** Sections authored without one list as
      a bare "SECTION" in the builder and cannot be told apart. The library
      ones all carry `C8 · FAQ`-style names; new ones must too.
- [x] **The team archive is a TEMPLATE, not a page** (2026-08-07).
      `archive-team` (id `RMMlzZVy`, "Team — archief"), 5 sections: header ·
      dynamic 3-col grid · jobs CTA · `C8 · FAQ` · `A4 · Contact CTA`.
      Its two STATIC sections DO live on page 64 — `H5 · Header — tekst`
      (eyebrow + H1 + intro at half width, distinct from `H3 · Header —
      pagina`, which is a 3-5/2-5 split with an image beneath) and
      `A6 · Jobs CTA`. Page 64 is at 30 sections. Only the grid stays out,
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
