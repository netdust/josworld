# Lessons — josworld

Project-local corrections. **The main lesson from this project is NOT local** —
see `stakes-dial-ignored` in the harness calibration index
(`netdust-agent/skills/_shared/calibrations.md`) and the fleet entry in
`~/Sites/netdust-wp-manager/memory/GLOBAL.md`. It is recorded there because it
is a harness behaviour, not a josworld fact.

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
