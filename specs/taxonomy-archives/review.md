Reviewed: 85c7cd11046571cadca0b07813e1ec1e5505b8ad
The three reviewers are running. I'll wait for their completion notifications, then merge the findings into one report.

Security-sentinel is back: no blocking findings, three Low items. Still waiting on the code reviewer and the drift reviewer.

Drift reviewer is back: no Critical, five Drift items. Waiting on the code reviewer before writing the combined report.

## Feature review: taxonomy-archives

**Reviewed:** `bf370d4..origin/feature/taxonomy-archives` (85c7cd1), three commits. The feature branched from staging, and `origin/main` sits far behind it, so the staging merge-base is the base. No plan exists under `specs/taxonomy-archives/`; the reviewers worked from the diff, the commit messages and the STATE.md entry. Level: full (the environment variables could not be read in this sandbox, so the name came from the checked-out branch). Reviewers: the superpowers code reviewer, security-sentinel (capabilities and request parsing touched), and ntdst-drift-reviewer. No `ARCHITECTURE-INVARIANTS.md`, so the invariant-auditor did not run.

All three agree on the shape: limits enforced at the `set_object_terms` seam, capability split correct and holding in REST, taxonomy args passing through ntdst-core unchanged, hooks registered through the framework's door, no second home for anything. The holes are all in the URL-sharing half and in one test's isolation.

### Blocking

None.

### Should fix

1. **Two cases can end up with the same slug.** `CaseService.php:121-133`. The filter runs after core's uniqueness check and returns `<slug>-N` without re-checking posts. Two cases titled "Klimaat" while a domain `klimaat` exists both get `klimaat-2`. Fix by delegating the candidate to `wp_unique_post_slug()` rather than a bare suffix. (code reviewer, security-sentinel F3)
2. **Baseline SharedSlugs undoes the guard once Polylang has languages.** `CaseService.php:130` versus `ntdst-baseline/services/polylang/SharedSlugs.php` `uniqueSlug()`/`repair()`. SharedSlugs runs later on the same filter, sees the suffix, and on language assignment restores the original slug. The case then takes the domain's URL. Latent today, live once nl/en/fr exist. Interim: register at priority 20 and use the delegation from item 1. File the premise defect against baseline. (code reviewer; drift reviewer open question)
3. **A new domain term silently hides an existing case.** `CaseService.php:105-110`. Nothing stops an admin creating a domain whose slug matches a published case; the term wins and the case goes dark. Suffix or refuse via `wp_unique_term_slug` / `pre_insert_term`, and compare term slugs against case slugs on staging before flushing. (code reviewer, security-sentinel, drift reviewer)
4. **`CaseDomainUrlTest::setUp` leaks a different `domain` registration into later tests.** `tests/Integration/CaseDomainUrlTest.php:23-24`. `(array) get_taxonomy()` carries `cap`, not `capabilities`, so caps reset to `manage_categories`. `CaseTermLimitsTest` only passes because of file ordering and `CaseLabelsTest` re-registering in between. Call the post type's and taxonomy's `add_rewrite_rules()` instead of re-registering. (code reviewer)
5. **`clampTerms` swallows the `WP_Error` from its corrective write, and dereferences a possible `false`.** `CaseService.php:85-88`. If the rewrite fails the over-limit set stays silently. Capture the return and log; filter out failed `get_term_by` lookups. (drift reviewer #5, security-sentinel F2, code reviewer)
6. **`AdminAppService::TAXONOMIES` is a hand-kept copy of `get_object_taxonomies('case')`.** `AdminAppService.php:49-51`. One new taxonomy needed two files, and the private const is pinned by no test. Derive it from the registry filtered on `show_ui`. (drift reviewer INV-5/A)
7. **The label cap re-spells the limit.** `CaseService.php:353` slices at a literal 3 instead of `CaseService::LIMITS['dienst']`. (drift reviewer #14)
8. **Script version `null` emits no cache-buster.** `AdminUIService.php:80` (and the pre-existing sibling at `:105`). A deploy can keep serving the old `case-term-limits.js`. Use `filemtime` like `AdminChromeService.php:121-131`. (drift reviewer, code reviewer, security-sentinel F7)

### Note

- `CaseService.php:100`: `?domain[]=x` reaches the cast as an array and emits a PHP warning. Guard with `is_string`. The filter also runs for admin list requests; an `is_admin()` early return makes the intent explicit. (security-sentinel F1, code reviewer)
- `CaseService.php:67-90`: server keeps the FIRST term for limit 1, the JS keeps the LAST. Consistent only while the script trims before save; worth a docblock sentence. (code reviewer)
- `case-term-limits.js:38-49`: opening a legacy case with four diensten dirties the post without user action. (code reviewer)
- `CaseService.php:269-274`: `doelgroep` is `public => true`, so it appears in Appearance → Menus with a dead link. `public => false, show_ui, show_in_rest` says the spec in one line. (code reviewer)
- `CaseService.php:234` vs `:206`: `with_front => false` on `domain` but default on `case`. Align. (code reviewer)
- `CaseService.php:139`: `posts_per_page = -1` on a public archive is unbounded. Fine at this scale; a cap costs one line. (security-sentinel F5)
- REST term creation by editors is blocked only because the taxonomies are hierarchical. Add one integration test: editor `POST /wp/v2/domain` → 403. (security-sentinel F4)
- `CaseService::LIMITS` is keyed by taxonomy slug while the JS reads the editor store by REST base. Equal today; name the coupling at the declaration. (drift reviewer)
- `AdminUIService.php:84`: reaching `CaseService::LIMITS` by FQCN is the only service-to-service reference in the module. Moving the enqueue into `CaseService` would remove it. Defensible either way. (drift reviewer)
- `AdminUIService.php:119`: pre-existing `function_exists('ntdst_data')` guard around a core helper. (drift reviewer #15)
- `CaseService.php:321-328`: the admin column lists all diensten while the card caps at three. (drift reviewer)
- `CaseService.php:4,18-20,215`: docblocks still say "its `dienst` taxonomy"; the class now declares three. (drift reviewer)
- `tests/E2E/taxonomy-archive.spec.ts:15`: `waitForLoadState('networkidle')` will flake once Matomo beacons run; `expect(page).toHaveURL()` auto-waits. (code reviewer)
- `AdminUIService.php:96-97`: `wp_localize_script` stringifies the limits; `wp_add_inline_script` with `wp_json_encode` keeps ints. (code reviewer)

Deploy notes in STATE.md hold: `wp rewrite flush --hard`, create the two doelgroep terms, plus the slug-overlap check from item 3. A framework gap worth a baseline ticket: `admin_ui` could default `taxonomies` from the registry.

Findings: 0 Blocking · 8 Should fix · 14 Note
