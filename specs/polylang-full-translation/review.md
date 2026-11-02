Reviewed: a70e0dae9e03c37c4753bcb43b3ccda2d96cc810
## Review: feature/polylang-full-translation

I couldn't read `REVIEW_NAME` or `REVIEW_LEVEL` here because the shell won't run `printenv` in this headless run. I took the name from the checked-out branch and used the default `full` level. The feature head (a70e0da) is the commit already checked out, and the tree was clean. So the reviewers read the checkout itself instead of a separate detached worktree. It was still clean after they finished.

**What was reviewed**
- **Range:** 2b917c4 (merge-base with origin/main) to a70e0da, two commits, four files.
- **Changes:** ntdst-core goes from 5.4.1 to 5.5.0 and ntdst-baseline from 2.24.1 to 2.26.0. The runbook in `docs/content-model.md` now lists `domain` and `doelgroep`. The e2e step list gains a `post_terms` step.
- **Plan:** none (`specs/polylang-full-translation/` doesn't exist), so the reviewers worked from the diff and the commit messages.
- **Reviewers:** superpowers' code reviewer and `netdust-wp:ntdst-drift-reviewer`. I skipped `security-sentinel` because there is no threat model and the diff touches no security-boundary file. I skipped `invariant-auditor` because the project has no `ARCHITECTURE-INVARIANTS.md`.

**Blocking**
- None.

**Should fix**
- `app/content/mu-plugins/josworld-core/services/content/CaseService.php:119,152` (drift reviewer): two checks call `get_term_by('slug', …, 'domain')`. One decides whether `/ons-werk/<slug>` is a domain or a case; the other decides whether a new case may take a slug. Once `domain` becomes translatable, Polylang filters that lookup by the current language. In wp-admin that is the language the admin filter is set to. An English case could then take an English domain's slug while an editor has the filter on NL. Suggested fix: pass `'lang' => ''` or use the post's own language, then check `/en/our-work/`. I confirmed both calls are at those lines.
- `docs/content-model.md:265-269` (code reviewer): the new paragraph sits between the `languages` command and its "Verify with" line. Line 269 runs "Add `--dry-run` first…" and "Verify with" together, so the verify step now reads as if it checks `setup` instead of the `languages` command. The `setup` note needs its own paragraph, or should move after "Verify".
- `josworld-core.php:159`, `functions.php:29,42`, `AdminUIService.php:127` (drift reviewer, existed before this branch): each checks whether a core function or class exists first. If core is missing, the site quietly runs without those features and nothing reports it.
- `ToolAppService.php:66-67` (drift reviewer, existed before this branch): renders a template with `ob_start` and `include` instead of `ntdst_response()->html()`. The slug is checked against a fixed list first, so this is not a security issue.

**Note**
- `tests/E2E/polylang-setup.spec.ts:55` (code reviewer): baseline's `PostTerms.php:68-81` counts a translation as failed when one of its terms has no translation in that language, and it does so in a dry run too. On a local database where, say, an EN case carries an untranslated `dienst` term, the "0 failed" assertion will fail.
- `docs/content-model.md:266-267` (code reviewer): "production translated neither until 2026-11-02" describes a fix that hasn't happened yet. Production only changes once this ships and the command runs there.
- Baseline 2.26 `setup` (code reviewer): `setup` now makes every public post type translatable, not just pages and posts. Read its `--dry-run` "become translatable first" list (`SiteSetup.php:279-289`) before running it on production.
- Core 5.4.2 (code reviewer): this version comes in with the bump, and a field's declared `label` now shows in the metabox. josworld-core declares 13 labels, so some admin field labels may change.
- `docs/content-model.md:184` (code reviewer, existed before this branch): calls `dienst` non-hierarchical, but it is hierarchical (`CaseService.php:305`).
- `AdminUIService.php:131` (drift reviewer, existed before this branch): reads the schema with `ntdst_data()->get()->getSchema()` outside a repository. It is read-only, so this one is borderline.

**Checked and fine**
- The lockfile, installed versions and e2e step order match baseline's `SiteSetup.php:113-126`.
- `domain`, `dienst` and `doelgroep` are the only taxonomies, all on `case`, so the runbook list is complete.
- `app/composer.json` is identical to the one on feature/chat-pilot, as the commit message says.
- Josworld does no Polylang work by hand that baseline now owns, so there is no second home.
- `clampTerms` and the new `post_terms` step don't conflict.
- Nobody ran `composer validate`; it needed approval, so the lock's content-hash wasn't checked.

Findings: 0 Blocking · 4 Should fix · 6 Note

## Fix pass (2026-11-02)

- Should fix 2 (runbook paragraph splits the command from its verify line): moved after "Verify with", and the dated claim now says what holds for any environment converged before 2.26.
- Should fix 1 (`CaseService` slug checks are language-filtered once `domain` is translatable): Ruling: deferred — it takes a new case given the exact slug of another language's domain while the admin language filter differs; the case's URL then shows the domain overview, visible at once; a test needs a Polylang-translated `domain` in wptests — cost if wrong: one case URL shadowed until renamed. The front resolution on `/en/our-work/<slug>/` was proven on DDEV.
- Should fix 3 and 4 (core guards, `ob_start`+`include` in ToolAppService): predate this branch; not in its diff.
- Note on the dry run's `0 failed`: the e2e runs on DDEV, where no translation carries an untranslated term today; it passes in the gate.
