Reviewed: 2c9e655c39de02eb38c85c60b3dbb9a702140bf4
# Review of feature/devops-core

I reviewed the feature's own two commits, **2005f10..fd1776e** (7dbeac2 and fd1776e). There is no plan for this feature (no `specs/devops-core/plan.md`), so the reviewers worked from the diff and the commit messages.

**Which feature, at what level.** This session could not read `REVIEW_NAME` or `REVIEW_LEVEL` because env access needed approval. I took the current branch, feature/devops-core, and the default level, `full`. origin/staging's tip is `promote: devops-core`, which matches that guess.

**Why this range.** The merge-base with production (origin/main 5b1da56) is 158 commits back, because main hasn't shipped staging's other promoted features yet. Those were reviewed on their own, so only this feature's commits were in scope.

**Reviewers.**
- **Ran:** superpowers' code reviewer and `netdust-wp:ntdst-drift-reviewer`.
- **Skipped `security-sentinel`:** there's no threat model, and the diff touches none of its trigger surfaces (auth, sessions, nonces, credentials and the like).
- **Skipped `invariant-auditor`:** the project has no `ARCHITECTURE-INVARIANTS.md`.

**Confirmed sound:**
- The checksums in `.netdust-devops` match all 15 vendored files (`sha256sum -c` passes).
- `feature` and `hotfix` now branch from `origin/main`.
- `00-block-outgoing-mail.php` stays protected from rsync `--delete`.
- `make e2e env=production` is refused.
- Nothing project-specific from the old 920-line Makefile was lost.

**Not checked by running:** the reviewer couldn't run `make help`, `make test` or a fresh-clone checkout without approval. Fresh-clone behaviour was checked by reading the files only.

## Blocking
None.

## Should fix
- `tests/E2E/footer-newsletter.spec.ts:8,28-33` (code reviewer): the whole `describe` block is tagged `@smoke`, and one of its tests clicks the newsletter's submit button with `naam@domein`. `make smoke env=production` would run that on the live site. The address is probably rejected today, so nothing is written yet. But any test added to that block later becomes a production smoke and could subscribe someone to the FluentCRM `nieuwsbrief` list. Move the submit test into its own `@e2e` block, and tag individual read-only tests rather than whole describes.

## Note
- **Commit 7dbeac2 fails on its own** (code reviewer): it includes `Makefile.netdust`, which was still git-ignored until fd1776e. This only hurts bisects; squash the two commits on merge.
- **`site.yml:132-134`: `@smoke` and `@e2e` select the same read-only design specs** (code reviewer).
  - The `e2e/staging` stamp that `ship` waits for therefore proves layout, not any flow.
  - Assertions tied to exact content (pixel sizes, card counts, a specific post slug) will turn production smoke red after a plain content edit.
- **Possible Matomo pollution** (code reviewer, plausible, not verified): headless smoke runs may be recorded as visits unless Matomo excludes them.
- **Mode change 644→755** on `scripts/remote/00-block-outgoing-mail.php` and `scripts/README.md` (code reviewer and drift reviewer): harmless. It comes from `scripts/devops-version:120` making everything under `scripts/` executable, and the local mode never reaches the server.
- **`README.md:27` is stale** (code reviewer): it still says `make feature` branches from staging and lists `make finish`.
- **`mk/wp.mk:203`** (drift reviewer): `make pull` sets FluentSMTP's simulate-emails option with a raw `ddev wp eval … update_option`. It's vendored local tooling and breaks no convention; any change belongs upstream.
- **`scripts/remote/00-block-outgoing-mail.php:46`** (drift reviewer): its `'__return_true'` would trip a future INV-3 sweep. That's a false positive: it's a FluentCRM filter, not a REST permission.
- **`scripts/remote/00-block-outgoing-mail.php:21`** (drift reviewer): `ntdst_mail_block_is_production()` uses core's `ntdst_` prefix and could collide with a future core helper. This predates the branch.

Findings: 0 Blocking · 1 Should fix · 8 Note
