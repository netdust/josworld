# Lessons — josworld

Project-local corrections. **The main lesson from this project is NOT local** —
see `stakes-dial-ignored` in the harness calibration index
(`netdust-agent/skills/_shared/calibrations.md`) and the fleet entry in
`~/Sites/netdust-wp-manager/memory/GLOBAL.md`. It is recorded there because it
is a harness behaviour, not a josworld fact.

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
