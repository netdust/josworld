# Lessons — josworld

Project-local corrections. **The main lesson from this project is NOT local** —
see `stakes-dial-ignored` in the harness calibration index
(`netdust-agent/skills/_shared/calibrations.md`) and the fleet entry in
`~/Sites/netdust-wp-manager/memory/GLOBAL.md`. It is recorded there because it
is a harness behaviour, not a josworld fact.

---

## Do not spend session time verifying WordPress fundamentals

**Date:** 2026-08-03
**Correction (Stefan, watching a parallel session):** *"stuff like this takes
time isn't this already stored knowledge or something?"* -> *"tell them to
stop verifying wordpress basics"*

Triggered by a session step reading "Confirming WP_Hook same-priority
ordering behavior". That is documented, invariant WordPress behaviour. It
costs a probe, a tool round-trip, and reading time to rediscover something
that could be stated in one line.

### STATE these, do not derive them

- `add_action`/`add_filter` at the SAME priority fire in REGISTRATION order.
- Lower priority numbers run FIRST. Default 10.
- `plugins_loaded` -> `after_setup_theme` -> `init` -> `wp_loaded`.
- mu-plugins are included alphabetically, BEFORE regular plugins.
- `get_current_screen()` is populated when `admin_enqueue_scripts` fires on
  `post.php`/`post-new.php`.
- `update_post_meta()` on a PHP array serialises via `maybe_serialize()`.
- `wp_get_object_terms()` orders by NAME, not insertion.
- `public => false` does NOT gate REST reads.

If one of these is ever wrong, the next test run says so immediately. The
cost of being wrong is one red test; the cost of checking every time is
paid on every task forever.

### DO verify: where a primitive does not do what its name implies

The distinction is *stable framework behaviour* vs *this project's wiring*.
Three from this session, each of which cost real time and NONE of which was
guessable from documentation:

- DAAN's `josworld-coreloader.php` sorts BEFORE `ntdst-coreloader.php`
  alphabetically — the whole reason the boot defers to `plugins_loaded`.
- `NTDST_RelationField` SELF-REGISTERS at the bottom of its own file. I read
  the class, saw no bootstrap resolving it, and concluded it was never
  instantiated. Wrong — I nearly shipped a brief that would have
  double-registered every reverse metabox. **Read framework files to the
  END.**
- `select` renders a dropdown but does NOT validate on save
  (`MetaboxGenerator.php:686-697`). A "closed list" enforces nothing without
  a `validate` callback — so the obvious test (assert the dropdown's
  options) is a presence check wearing a denial check's clothes.

### The rule

Ground-truth what a task will BUILD ON — a signature, a storage format,
whether a guard actually fires. Do not ground-truth what the next test run
would reveal anyway.

### Why it matters at this scale

Same branch, same day: this session took **290 min for 2,077 lines** of
declarative config; the parallel baseline session took **26 min for 4,291**.
The gap was not the verification TECHNIQUE — mutation testing found every
real bug and is cheap — it was ceremony plus re-deriving known things.
See also the granularity entry below and `stakes-dial-ignored`.

---

## The plan's TASK GRANULARITY is challengeable at the seam — not just the ceremony applied to it

**Date:** 2026-08-03
**Correction:** *"i am concerned that a setup that untill now is more config
than anything else took so long."*

This is a DIFFERENT failure from `stakes-dial-ignored`, and it is the one
that actually cost the four hours. Recorded here as well as at fleet level
because the two compound and are easy to confuse.

### The measurement

**290 minutes (~4h).** Deliverable: **2,077 lines** of declarative config
(4 CPTs, 1 taxonomy, field maps) against an existing reference
implementation (DAAN). Tests: **4,651 lines** — a 2.2:1 ratio on field maps.

Where it went:
| | |
|---|---|
| The actual config | ~45 min — should have been the whole job |
| Ceremony (18 dispatches, 8 reviews, ledger writes) | ~2h — mostly waste |
| Real bug investigation | ~45 min — genuinely earned |
| Chasing my own bad diagnosis (`wp eval` dead end) | ~30 min — my error |

### The distinction that matters

`stakes-dial-ignored` says: **scale the ceremony to the stakes.** True, and
already recorded. But it operates INSIDE a given plan — it tunes how much
verification each task buys.

It never asks whether the plan's DECOMPOSITION was right. Here it wasn't:
12 gated tasks with per-task test contracts, for what is essentially
`'client' => ['type' => 'text']` twenty-five times. T05–T08 were four
separate dispatches producing four near-identical field maps. That is ONE
task with four field maps in it, not four tasks.

**I executed that plan faithfully instead of pushing back on it at the
seam** — where I had explicit authority to correct it, and where I DID use
that authority twice for other things (the T03/T04 vacuous-ordering defect,
the `composer.json` file-list error). I corrected the plan's *content* twice
and never once questioned its *shape*.

### The rule

At the seam, before approving a plan, ask a second question:

1. (existing) Are the gates right for the stakes?
2. **(new) Is the task GRANULARITY right for the KIND of work?**

For **config against an existing reference implementation**:
**one task per cluster, not one per model.** T01–T02 collapse to one.
T05–T08 collapse to one. Realistically ~6 tasks instead of 12.

A task boundary should exist where there is a real decision, a real risk, or
a real dependency — not merely where there is another item in a list. Four
field maps that differ only in their field names share ONE contract and want
ONE review.

### The evidence it would have worked

Cluster C ran the light protocol (mutation-test per task, ONE review at the
cluster gate) and came in at **0.44×** verification budget, versus 1.39× and
1.10× for A and B — with the SAME guard rigour, every guard mutation-tested
and biting. That shape should have applied from T01.

Estimated correct cost for this work: **60–75 minutes**, with all four real
bugs still caught, because every one of them came from mutation testing —
the cheap technique — and none from the eight review dispatches.

### The counter-check (do not over-correct)

The four real bugs were worth finding and would have reached production:
- `search_posts` fataled on its own default args — the relation picker never
  worked, for ANY field, on 11 sites
- a stored XSS in ported admin JS, incl. my own insufficient first fix
- the Polylang `nav_menus` mapping (site down without it)
- T02's guard not biting (commented scaffolding pre-satisfied its invariant)

So the lesson is NOT "do less verification". It is: **fewer, larger tasks;
mutation-test the guards inside them; review at cluster boundaries.** The
bug-finding technique was never the expensive part.

---

## Local: the plan's acceptance criteria were wrong twice, and that was fine

**Date:** 2026-08-03

Two of `specs/josworld-core`'s acceptance criteria could not be satisfied as
written. Both times the right move was to say so, not to write a test that
appeared to satisfy them:

- **T03's contract** ("every service named in the boot block resolves")
  was unsatisfiable in its original sequence position — the boot block named
  ZERO live services, so it would have passed vacuously over an empty set.
  Resolved by a plan correction (swap T03/T04) approved at the seam, plus a
  strengthened clause making a vacuous pass fail by definition.
- **T08's contract** ("terms retrievable in insertion order") is not
  achievable through the WP API at all: `wp_get_object_terms()` orders by
  name and `wp_set_object_terms()` never populates `term_order`. The
  implementer asserted what IS true (alphabetical) with deliberately
  non-alphabetical fixtures, so the test would fail on a naive
  insertion-order assumption, and documented why.

**Rule for this project:** an acceptance criterion that cannot be honestly
proven gets reported as a plan defect and corrected in the artifact. It never
gets a test that looks like proof. Both corrections cost minutes; a vacuous
green would have cost a production bug.

---

## Local: `ntdst-core` is shared and vendored — fixes need propagation, not just a commit

**Date:** 2026-08-03

Two framework bugs were found and fixed here (`95b7e06`, `135030a`) in code
deployed to **11 sites**. Facts worth keeping:

- The copies have **diverged into 3 versions**. `eb3fa5b044` (6 sites incl.
  josworld/daan/edushare/todai-client) carried both reverse-lookup defects;
  `aedad605c7` (4 sites) has only the meta-prefix one — its serialized LIKE
  is already correct; `0f5189c995` (bavi) is a third revision.
  **Blind-copying a fix to all of them is wrong.** Verify byte-identity to
  the pre-fix state before copying, per file.
- Layouts differ: `app/content/mu-plugins/` (stackedWP) vs
  `web/app/mu-plugins/` (Bedrock). A path-rewritten patch invites error; a
  copy of a verified-identical file does not.
- **daan is the regression canary.** It uses relation fields with NO
  `meta_prefix` — i.e. the back-compat path — and has 254 integration tests.
  Run those before calling a `RelationField`/`Data` propagation done.
- `ntdst-core` has **no test suite of its own** (fleet gap, L-1 at the
  Cluster B gate). Regression tests for framework fixes currently live in the
  downstream site that found the bug and travel with the patch.

---

## Local: verified traps in this stack

**Date:** 2026-08-03 — each cost a wrong assumption before it was probed.

- `select` **renders** a dropdown; it does **not validate on save**
  (`MetaboxGenerator.php:686-697`). A closed list is only enforced by a
  `validate` callback (`Data.php:105-117` collects, `:422-426` invokes).
  A test asserting "the dropdown has these options" is a presence check
  wearing a denial check's clothes.
- There is **no `group`/`object` field type** — only
  text/textarea/select/relation/gallery/repeater. Nested field shapes must
  be flattened.
- Repeater sub-fields do **not** support a nested `relation`.
- `gallery` is **image-specific** (`wp_get_attachment_image_url`, alt-text
  UI). For a generic downloadable file use `relation` scoped to
  `post_type => 'attachment'`.
- `NTDST_RelationField` **self-registers at the bottom of its own file**
  (`after_setup_theme:20`). Read framework files to the END before
  concluding something is unwired — I nearly shipped a brief that would have
  double-registered every reverse metabox.
- The `search_posts` filter registers on `rest_api_init`; a test must fire
  that action or it asserts against a filter that does not exist. Its rows
  are keyed lowercase `id`, not WP's `ID`.
- `public => false` does **not** gate REST reads. It gates front-end
  query/sitemap/search. `team` is unauthenticated-readable at
  `/wp-json/wp/v2/team`; email/phone are unexposed **only** because nothing
  calls `register_meta()` with `show_in_rest`.

## Front-end / YOOtheme

- **Never put a font-size in a class in content HTML.** Stefan had to ask twice
  for smaller type before asking the real question — "are you using the styler
  font system or custom sizes in less?" — and the answer was custom
  (`.jw-lead` / `.jw-intro`, typed into element content). UIkit ships FOUR named
  text roles (`lead`, `large`, `small`, `meta`), each one BOTH a "Text Style"
  dropdown on the element AND a Styler-exposed variable family. They default to
  40px for lead/large, which is what makes them look unusable on this design and
  tempts the bespoke class. Map `@text-lead-*` / `@text-large-*` instead. The
  cost of getting this wrong is not the pixel value — it is that the size stops
  being changeable by a dropdown or by the client, so every tweak becomes a LESS
  edit + browser recompile + a round trip.
- `packages/styler/config/styler.php` is the authority on what the Customizer
  exposes. Check it before concluding a value has to be bespoke — it lists
  `@text-*`, `@accordion-default-*`, `@base-h1..h6-*`, `@button-*`, `@card-*`
  and many more.
- Two design sizes in a row shipped one step too big (accordion titles 24→22,
  contact card 24→18). When a screenshot cannot be measured (clipboard paste, no
  file on disk), say so and prefer the SMALLER of two candidate tokens rather
  than the larger.
- **A fullpage CDP screenshot can come back with images blank even when they are
  fully loaded.** `img.complete` was true, `naturalWidth` 1152, opacity 1, no
  clip — and the photo still painted as empty grey in the capture, which reads as
  "the image is broken" and nearly sent me debugging a working section. Loading
  is not painting: `await img.decode()` on every image before capturing is what
  fixed it. This is on top of the lazy-loading trap already in the skill's
  lessons (forcing `loading="eager"` is necessary but NOT sufficient).
- **A CPT archive beats a same-slug page.** `case` registers
  `rewrite.slug => 'ons-werk'` + `has_archive`, and there is also an "Ons werk"
  PAGE at that slug. The archive wins, so a layout built on the page renders
  nowhere and the symptom is a correct-looking save followed by the stock archive
  on screen. Check `get_post_type_object(...)->rewrite['slug']` before deciding
  whether an archive layout belongs on a page or in a template.
- **Dynamic list bindings go on the `grid_item`, not the grid.** One item +
  `source.query` = the item repeats over the query. Query name is
  `<rest_base>.custom<RestBase>`. Verify against a demo package
  (`unzip -p <demo>.zip 'wp-content/sample_yootheme.json'`) instead of inventing
  the shape — and introspect the live schema for field names
  (`app(Source::class)->getSchema()->getType('Case')->getFields()`).
- **`<taxonomy>String` fields are LINKED term lists**, not plain text — they are
  `get_the_term_list()` output. `panel_link: true` (which requires
  `show_link: true`) strips them, because the card becomes one `<a>` and nested
  anchors are invalid. That is a settings answer to what looks like a CSS problem.
- **Turn the class dial before reaching for ceremony — over-calling it is the
  expensive failure.** 2026-08-06: Stefan asked why a YOOtheme layout tweak
  needed a threat model. Because I classified "add a taxonomy + a small resolver
  + flip a JSON prop" as harnessed-development **Class A** (new feature) instead
  of **Class E** (small self-contained change), and Class A mandates spec.md +
  plan.md + tasks.md + the six gate headings — so a `## Threat model` section
  got written whose honest content was `N/A`. The harness's own red-flag table
  names this: "a tweak is Class E — route it light. Ceremony is scaled by class,
  not by mood." His words: "this is why you spin out of control every time and we
  lose enormous amounts of time."
  **Test before invoking the harness at all:** could this be one red/green cycle?
  Then it is C/D/E and there is no plan artifact. Class A is for work with open
  design questions and several days of surface — not for three files with an
  existing pattern to copy.
- **Type sizes are a FIXED vocabulary — never derive one per design.** Stefan,
  2026-08-06: "type sizes should be fixed in yootheme, don't invent size every
  page, we get into rhythm troubles… there [are] lead, text, meta, small for text
  sizes." The scale is `lead` / `text` (default) / `meta` / `small` for body
  copy, and `h1`–`h6` for headings. I had measured each design and picked the
  nearest number, which put three page headers on three sizes — 76 (test page),
  32 (cases, via the non-vocabulary `text-large`), 56 (tools). Map the element to
  its ROLE and use the same role for the same element on every page; if a role's
  size is wrong, change the variable once, not the page.
- **A lone column fills its row no matter what `uk-width-*` you give it.**
  `row/templates/template.php:26` emits `tm-grid-expand {!alignment}`, and
  `.tm-grid-expand > * { flex-grow: 1 }` — so the class exists only while the row
  has NO `alignment`, and while it exists it expands a single column past its own
  width. Symptom: `uk-width-1-2@m` sits correctly in the DOM and the column still
  measures full width. Fix: set the row's `alignment` (even `left`). This is why
  C3's 850px measure works — its row carries `alignment: center`.
- **An SVG that colours itself with an internal `<style>` class is immune to
  UIkit's recolouring.** `jos-sun.svg` ships `.cls-1 { fill: #fff794 }` in a
  `<defs><style>` block. UIkit's rule is
  `.uk-svg :not(.uk-preserve)[fill*="#"]`, which matches the fill ATTRIBUTE — a
  fill coming from a class inside the SVG is never touched, and `fill:
  currentcolor` on the root loses to it. Symptom on the tools CTA: the sun sat
  in the DOM at the right size and position with `fill: rgb(255,255,255)`
  computed on the `<svg>` element, and rendered lemon-on-lemon, i.e. invisible.
  Fix: target the paths (`.jw-sun-white svg path { fill: … }`). Check
  `getComputedStyle(svg.querySelector('path')).fill`, not the `<svg>`'s.
  `A1 · Tools CTA` uses the same asset with `image_svg_color: emphasis` and is
  probably lemon too — unverified.
- **A `template get` → patch → `template set` cycle silently loses whatever the
  human saved in the builder meanwhile.** 2026-08-06: Stefan added a section and
  re-saved while a patch was in flight; his save dropped the class and element
  the patch had just added, and the next patch (built on a stale fetch) found
  nothing to update. Always re-fetch immediately before writing, patch the LIVE
  copy, and make the script FAIL LOUDLY when its target node is missing instead
  of writing a no-op.
