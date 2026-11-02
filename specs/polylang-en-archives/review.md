Reviewed: 1230f79926fbb67edd385afa5b347fb30dd93c8c
## Review of feature/polylang-en-archives, 87626083..1230f79

I found nothing blocking. Two things should be fixed, and both are about the English and French URLs on production.

**What was reviewed:** the merge-base with origin/main up to 1230f79, three commits. There's no plan (`specs/polylang-en-archives/` doesn't exist), so the reviewers worked from the diff and the commit messages.

**Where I departed from the procedure:**
- **Name and level are assumed.** I wasn't allowed to read REVIEW_NAME or REVIEW_LEVEL, so I took the checked-out feature, polylang-en-archives, at level `full`.
- **No separate review checkout.** I wasn't allowed to create the worktree. This checkout is exactly at the feature head (1230f79 = origin/feature/polylang-en-archives), so the reviewers read it directly.

**Which reviewers ran:**
- superpowers' code reviewer and `ntdst-drift-reviewer` ran.
- `security-sentinel` didn't run: there's no threat model and the diff touches no security-boundary code.
- `invariant-auditor` didn't run: the project has no `ARCHITECTURE-INVARIANTS.md`.
- The devops core files (`Makefile.netdust`, `scripts/tests/*.sh`) are an upstream copy, so they were only checked for anything that breaks this site.

**What was confirmed against the source:**
- All four Dutch words in the map match each type's registered slug, so no Dutch URL changes.
- Tool pages stay at `/tools/`; only the tool archive gets the new word.
- Baseline rebuilds the URL rules by itself the first time they're missing, so a deploy needs no manual `wp rewrite flush`.
- The new archive URLs win over any page with the same slug.
- Baseline 2.24.1 needs nothing from josworld.
- The drift reviewer agrees `josworld-core.php` is the right place: it only supplies values, and baseline does all the routing.

### Blocking
None.

### Should fix
1. **The English case-links smoke check depends on content** (`tests/E2E/smoke/translated-archives.spec.ts:26-32`, code reviewer). It fails unless an English case is published, and production smoke runs it too. Local has exactly one; production wasn't checked. Either mark it `@e2e`, or drop the `length > 0` check and keep only "no `/en/ons-werk/` link".
2. **The old English and French URLs stop working, with no redirect** (`app/content/mu-plugins/josworld-core/josworld-core.php:42-47`, both reviewers). These now return 404:
   - `/en/ons-werk/…` and `/fr/ons-werk/…`
   - `/en/jobs-stages/…` and `/fr/jobs-stages/…`
   - `/en/gratis-aanbod/` and `/fr/gratis-aanbod/`

   `LegacyRedirectService.php:29-35` only covers the old Dutch paths. This matters if English or French pages were already live and indexed on production (French at least exists there). Check before `ship`.

### Note
- **`domain` term links may break** (`CaseService.php:302`, `:412`, both reviewers). The `domain` taxonomy shares the `ons-werk` word with cases. If `domain` is ever translated in Polylang, its links on English cases will say `/en/ons-werk/<term>/`, which now returns 404, because baseline doesn't rewrite term links. Today `domain` isn't translated locally, so nothing is broken.
- **The Dutch word lives in two places** (`josworld-core.php:43-46` and each CPT's registration, drift reviewer). If someone renames a slug in one place only, translation stops without any error. That's a limitation of baseline's API rather than drift in josworld.
- **French isn't tested** (`translated-archives.spec.ts`, code reviewer). The French words are configured, but the spec only checks Dutch and English.
- **Production smoke will be red until this ships** (`translated-archives.spec.ts`, code reviewer). `make smoke env=production` will fail the archive checks in the meantime.
- **Leftover placeholder pages** (code reviewer). The placeholder pages DeepL created are probably on staging and production only; they weren't found locally. Trash them, and point any English menu items at the archives first.
- **A new language needs a word for every type** (`TranslatedBases.php:78-85`, code reviewer). If a fourth language is added, any type missing a word for it loses all its translated words, without warning.
- **Outdated comment** (`scripts/tests/deploy-test.sh:166`, code reviewer). The comment still describes both promote and unpromote, but only unpromote is tested now. Harmless.

Findings: 0 Blocking · 2 Should fix · 7 Note

## Fix pass (2026-11-02)

- Should fix 1, smoke depends on content: the case-links check now asserts only that no link uses `/en/ons-werk/`.
- Should fix 2, old EN/FR URLs 404: Ruling: no redirect — the `/en/ons-werk/…` URLs were live about two hours on production with no language switcher, and nothing French is published — cost if wrong: an external link to one of them 404s. Internal links DO break: 19 English posts, `widget_builderwidget` and the `yootheme` option link to `/en/ons-werk/`, `/en/jobs-stages/` or `/en/gratis-aanbod`, and baseline's LinkRewriter cannot resolve them once the words change. Owed on production in the ship's sitting: `wp search-replace` of the plain and `\/`-escaped forms, dry run first.
