# josworld — Archive

History moved out of `memory/STATE.md` to keep it a snapshot (per this
project's `CLAUDE.md`). Newest section first.

---

## Archived 2026-09-03 (CPT singles close) — front-end build + cases archive

Moved out of STATE.md at the cpt-builder-singles close to keep it under 40 KB.
The two sections below are verbatim as they stood on 2026-09-03.

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

---

## Archived 2026-08-03 (T12, content-model phase close) — theme-foundation phase

This is the full theme-foundation-phase STATE.md content, as it stood before
the content-model work (T01-T12) landed. Superseded by the current STATE.md
"Content model" section, but kept here since several of these findings
(YOOtheme LESS compile mechanics, Customizer exposure, font faces) remain
true and undocumented anywhere else.

### Current Phase: theme foundation (YOOtheme child, pre-Figma)

stackedWP install running at https://josworld.ddev.site. Gate: all tiers green.
Admin `/wp/wp-admin` (admin/admin). Production domain + remote path are still
TODO placeholders in `site.yml`.

**Branch: `feat/yootheme-child`** (4 commits, NOT merged to main):
- `1e31863` josworld theme → YOOtheme Pro 5.0.35 child
- `3947760` strip Tailwind/PostCSS, scaffold `less/theme.josworld.less`
- `5449d92` fix vite `base` for the stackedWP `/content/` layout
- `15f2807` map the real Josworld design system onto the style (Figma tokens)

### Theme architecture (decided 2026-08-03)

`josworld` is a **thin YOOtheme child** — mirrors the `todai-client/todai-child`
pattern. YOOtheme's builder owns rendering; the child carries no template files.

- Parent `yootheme` 5.0.35 copied from `todai-client`. **Gitignored** (41 MB,
  licensed, updates in place) — `.gitignore` ignores `app/content/themes/*` and
  re-includes only `josworld/`. It must be installed separately on any other host.
- Removed from the starter theme: `header/footer/front-page/page/single/404/`
  `index/searchform.php`, `partials/`, `Ntdst_Nav_Walker`, `josworld_fallback_menu`
  — a child's template files override the parent and would bypass the builder.
- Kept: ntdst-core bootstrap, `theme-config.php`, `helpers/`, `AssetHooks`, and the
  **Vite JS pipeline** (`src/main.js` = Alpine + the `ntdstAPI` REST wrapper, 5
  vitest tests). JS only — no CSS build. Do not reintroduce a CSS entry.
- `functions.php` enqueues parent `style.css` (handle `yootheme`) then the child's,
  filemtime-versioned, at priority 20.

### Non-obvious things a future session will otherwise rediscover the hard way

1. **Activating a child theme does not rewrite the `template` option.** The theme
   was activated as standalone before `Template: yootheme` was added, so the
   `template` option stayed `josworld` and the parent was never used — templates
   silently resolved to `theme-compat`. Fix: activate the parent, then re-activate
   the child. Verify with `get_template_directory()` (must point at `yootheme/`).
2. **PHPStan must exclude the YOOtheme parent** — `phpstan.neon` `paths:` includes
   all of `app/content/themes`. Without the exclude it analyses 41 MB of vendor code
   (1000+ errors). Baseline is 222 findings with it excluded.
3. **Regenerating the PHPStan baseline requires emptying it first** —
   `--generate-baseline` loads the existing baseline, and stale patterns abort the
   run with an internal error. Write `parameters:\n\tignoreErrors:\n` then regenerate.
4. **The E2E smoke test needs a real header.** `tests/E2E/smoke.spec.ts` asserts a
   `<header>` + nav landmark. YOOtheme renders nothing until a menu is assigned, so
   a fresh install FAILS this. Fixed here by creating a `Primary` menu assigned to
   YOOtheme's `navbar` location — **this lives in the DB, not in git**, and will not
   travel to Combell or to any new scaffold.
5. **YOOtheme style discovery**: `Styler::getThemes()` globs
   `{rootDir,childDir}/less/theme.*.less`, so a style in the CHILD theme is found.
   `@import` paths resolve relative to the LESS file → reach the parent via
   `../../yootheme/…`. Verified: `id=josworld name=Josworld`.
6. **YOOtheme compiles LESS in the BROWSER, not in PHP.** `Styler::resolveImports()`
   only gathers file contents + strips comments; the actual compile is less.js in
   the Customizer. There is no PHP compile step to call from the CLI — so to prove
   a style compiles, install `less@4` in the scratchpad and run `lessc` by hand.
   (Two traps when doing that: `head`-ing the output makes lessc exit 1 via SIGPIPE
   — check `${PIPESTATUS[0]}` or redirect to a file; and the local `lessc` FETCHES
   `@import url(...)` and inlines the `@font-face` rules, which the browser
   compiler does NOT do. Its absence from local output is expected, not a bug.)
7. **Brand fonts are picked in Customizer → Theme → Style → Fonts — never
   `wp_enqueue_style`, and no longer via an `@import` in the LESS either
   (removed in `e6d5727`, controller-decided: the choice belongs in the
   Customizer, not pinned in source).** Either route ends in the same
   `StyleFontLoader`, which downloads the woff2 files and self-hosts them
   (GDPR-friendly, same-origin, no preconnect needed) — but the selector keeps
   it editable. Enqueuing the same families in `functions.php` would load them
   twice AND from Google's CDN; a note at the enqueue site says so.
   The LESS `@jw-font-*` stacks only NAME the families + fallbacks; **pick
   Fraunces and Inter in the selector or the stacks fall back to Georgia/system-ui.**
8. **Figma's font names are optical-size CUTS, not families.** Figma reports
   "Fraunces 72pt SuperSoft" and "Inter 18pt"; Google serves them as `Fraunces`
   and `Inter` with the cut selected by the `opsz` axis. The CSS family name must
   be the plain one or the font silently falls back.
9. **Both `style.css` enqueues deliver only comments — this is KNOWN and
   deliberately left in place** (asked and decided 2026-08-03). The parent's
   `style.css` is 422 bytes of pure metadata header with zero CSS rules
   (YOOtheme ships its real CSS through the Styler), and the child's has a
   header + a placeholder comment. The FILE is mandatory either way — WordPress
   needs `style.css` to recognise a theme, and its `Template: yootheme` line is
   what makes josworld a child. Only the two `wp_enqueue_style` calls are
   optional; keeping them preserves the conventional child-theme shape and the
   escape hatch for one-off CSS. Do not "optimise" these away as a stray finding.
   (If the parent handle ever IS dropped, drop the child's `['yootheme']`
   dependency with it or the child stylesheet silently stops loading.)
10. **The child `style.css` header description is STALE** — it still says
   "Tailwind + Alpine via Vite", but Tailwind was stripped in `3947760`. Alpine
   and Vite are still accurate. Cosmetic, untouched so far.
11. **Customizer exposure, measured (not assumed): 139 of the style's variables
   ARE editable there; the ~90 `@jw-*` tokens are NOT.** The Customizer exposes
   by PATTERN whitelist (`packages/styler/config/styler.php`), so every
   `@global-*` / `@base-*` / `@button-*` / `@card-*` / `@navbar-*` / `@inverse-*`
   mapping written in section 2 shows up as a field — but `@jw-*` matches no
   pattern and stays invisible. (A handful of section-2 vars are also unexposed:
   `@button-font-size`, `@form-*`, `@container-*`, `@navbar-background`.)
   **The trap:** editing a colour under Customizer → Global → Colors overrides
   the MAPPED `@global-*` value while the `@jw-*` token it derives from still
   reads the old one — they silently diverge and the DB copy wins at runtime.
   **Decision (`9a017d7`, controller-decided): the LESS file is the source of
   truth; change brand values in section 1, use the Customizer for fonts and
   per-page work.** Making a token client-editable means renaming it to match an
   exposed pattern, moving its source of truth into the DB, outside git.
12. **"Theme.Josworld" in the Customizer's COMPONENT list is not a broken name.**
   (First read misdiagnosed it as the style picker — it is the variable-component
   list, alphabetically between Text and Thumbnav.) The styler groups variables
   by the FILE that declares them, and labels the group from the filename, so
   `theme.josworld.less` surfaces as its own group. Renaming the label therefore
   means renaming the FILE, which changes the style id everywhere and orphans
   saved style settings. **Decided: leave it, purely cosmetic.**
   Separately, the style PICKER name is correct — verified at runtime,
   `Styler::getThemes()` returns `id=josworld name=Josworld`. `getMeta()`
   regex-parses `Name:` from the file's FIRST comment block, so edits above that
   block are not risk-free; re-check the runtime name after touching the header.
13. **Figma MCP is rate-limited on a View seat** (Professional plan). The token
   extraction fit inside the budget, but a re-pull will not — budget calls, and
   prefer `get_variable_defs` (compact, authoritative) over `get_metadata` on a
   page node (291 KB, overflows the tool limit).

### Design system (extracted 2026-08-03, commit `15f2807`)

Source: Figma `bwFc8dsfRNScrQgOwUcXKW` (Jos-website), published VARIABLE
collection — global to the file, not per-page. An aviation/ATC consultancy
(skeyes, Eurocontrol, LVNL, DFS logos on the client strip).

- **Palette**: Jos Brown `#35070c` (ink/primary) · Brown 60% `#866a6d` (muted) ·
  Brown 10% `#ebe6e7` (lines) · Beige `#fcfcf7` (page bg) · Lemon `#fff794` ·
  Raspberry `#e60b66` (accent/CTA) · White.
- **Type**: Fraunces (display/headings/buttons) + Inter (everything else).
  Scale T1 76 / T3 40 / T4 22 / Intro 32 / Body-lg 24 / Body 18 / Button 18 /
  Nav 16 / Nav-2 12 / Disclaimer 12.
- **FOUR faces to select** in Customizer → Theme → Style → Fonts:
  **Fraunces 300 + 400**, **Inter 400 + 600**. No italics (nothing uses one;
  UIkit's italic blockquote is overridden to `normal`). A `700` in the compiled
  CSS comes from UIkit's own `<strong>`/`<b>` rule and is NOT a design token —
  load Inter 700 only if you want real bold instead of a synthesised faux-bold.
  Fraunces is variable (`opsz`); take the variable option if offered, since
  Figma's "72pt SuperSoft" is the `opsz=72` cut.
- **DEVIATION — Instrument Sans is deliberately NOT loaded** (`1f2b12b`,
  controller-decided). Figma sets disclaimers in it, but it had exactly one job
  (the 12px Disclaimer style) and is near-indistinguishable from Inter at that
  size, so `@jw-font-disclaimer` points at Inter and the third family is not
  loaded. The restore path is written at the variable; `@jw-t-disclaimer` still
  records the Figma provenance, so the deviation is visible from either end.
- **Two values are DERIVED, not extracted** — both flagged `TODO(figma)` in-file:
  **T2** (no such variable is published; interpolated between T1 and T3) and the
  **spacing/radii scale** (not published; 8px system matching the observed
  32px frame padding / 80px page gutter). Confirm against node `625:2790`.
- **No mobile scale exists** — every Figma variable is suffixed `desktop` and the
  file ships no mobile frames. Rather than invent one, the desktop sizes are the
  large-screen end of UIkit's native `-m` breakpoint pattern.

### Site structure (created 2026-08-03 — LIVES IN THE DB, NOT GIT)

⚠ Like the E2E menu (note 4), all of this is local DDEV database state. It will
NOT travel to Combell or to a fresh scaffold. Re-creating it is a `wp` script,
not a `git pull` — worth turning into a seed script before first deploy.

**13 page stubs**, empty, Dutch slugs, published:

| Page | Slug | ID |
|---|---|---|
| Homepage | `home` (front page) | 11 |
| Waarom Jos? | `waarom-jos` | 12 |
| Diensten | `diensten` | 13 |
| Samenwerken | `samenwerken` | 14 |
| Ons werk | `ons-werk` | 15 |
| Team | `team` | 16 |
| Voor overheden | `voor-overheden` | 17 |
| Voor social profit & duurzame ondernemers | `voor-social-profit` | 18 |
| Blog & Nieuws | `blog-nieuws` (posts page) | 19 |
| Gratis aanbod | `gratis-aanbod` | 20 |
| Jobs & stages | `jobs-stages` | 21 |
| Contact | `contact` | 22 |
| Privacy & cookie policy | `privacy-cookie-policy` | 23 |

`show_on_front=page`, `page_on_front=11`, `page_for_posts=19`.

**Menus.** The theme declares `primary`/`footer` locations, but YOOtheme renders
navigation from ITS OWN locations — the existing `Primary` menu is assigned to
**`navbar`**, which is what the E2E smoke test depends on. Extended that menu
rather than creating a competing one.
- `Primary` → location `navbar`: Waarom Jos? · Diensten (children: Voor overheden,
  Voor social profit, Samenwerken) · Ons werk · Team · Blog & Nieuws ·
  Jobs & stages · Contact
- `Footer` → location `footer`: Gratis aanbod · Contact · Privacy & cookie policy

**The menu structure is a GUESS** — the Figma Nav bar is a component `<instance>`
whose master could not be read (MCP rate limit), so labels/order/nesting were
inferred from page names. Nesting the two audience pages + Samenwerken under
Diensten is the most defensible reading, not a confirmed one. Correct in
Appearance → Menus; re-read node `13:119` when Figma access returns.

**Not yet built — repeating content needs a `josworld-core` mu-plugin.**
`theme-config.php` states business logic belongs there, not in the theme, and
ntdst-core ships no CPT registration. Figma draws these as one-template-many-items:
- **cases** (`Ons werk /`) — 7 drawn (Ivago, Stad Leuven, Sugg, Tonuso, UitX,
  VLIRUOS, VUB), 2 template variants (interview / no-interview)
- **blog** — native `post` is fine (posts page already wired to Blog & Nieuws)
- **gratis aanbod** — item template drawn
- **vacatures** — 3 states drawn (2 vacatures / geen vacatures / Stageformulier)

(This section's "Not yet built" list is what T01-T12 — see current
STATE.md — went on to build as `josworld-core`: `case`, `team`, `vacature`,
`tool` post types + the `dienst` taxonomy, superseding the plan above.)
