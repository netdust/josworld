Reviewed: 54a8a64730bfe9328b6fdcc202c383fdc506e7f9
Both reviewers are running. I'll wait for their reports and then compile the summary.

The drift reviewer is in. Waiting on the code reviewer before compiling.

Review of `feature/seo-launch` is complete. Both reviewers reported.

**What was reviewed.** Head `origin/feature/seo-launch` (54a8a64), identical to the local branch. Production is `origin/main` from site.yml; the merge-base with it is 5b1da56, but that range is everything already promoted onto staging, because the branch was cut from `feature/tool-url` (8329ee0, staging's last promoted feature) rather than from production. The feature's own change is the single commit 54a8a64 on top of that, and that is the diff the reviewers were given. No plan exists at `specs/seo-launch/`, so the review ran against the commit message, the docblocks and the STATE.md entry. Level: default `full` (the environment variables could not be read in this sandbox). Dispatched: the superpowers code reviewer and `ntdst-drift-reviewer`. `security-sentinel` was not dispatched: the diff touches no auth, session, capability, nonce, input-parsing, outbound or credential surface. No `ARCHITECTURE-INVARIANTS.md` in the project, so `invariant-auditor` was skipped.

The code reviewer ran the unit and integration suites for the changed files in DDEV (both green) and phpcs on the new tests (clean); it could not run the Playwright block or curl the site. The drift reviewer ran core's invariant greps against josworld-core (all clean or nameable false positives). Verified true: the installed baseline is v2.16.0 and reads `og_image_crop`, `og_image_fallback_size` and the CPT `description` exactly as josworld supplies them; the sitemap filter signatures are correct; all slugs the one-off and the e2e name exist locally (staging and production unverified).

## Blocking

None.

## Should fix

1. **Browser icons have three writers; baseline already owns the set.** `SiteMetaService.php:155-168` hand-prints two `rel="icon"` links at `wp_head` 3 while YOOtheme's `FilterIconMetaTags.php:28-36` still prints `favicon.png sizes="any"` + `touchicon.png`, and baseline 2.16 has a `site_icon` config key (`SeoService.php:126,167-175`, README 592-604) that feeds WordPress's own `wp_site_icon()`. Fix: have the Python script emit the five baseline sizes, add `'site_icon'` to `seoConfig()`, clear YOOtheme's favicon/touchicon setting, shrink `printIconTags` to favicon.ico + manifest + theme-color (or route those through the `ntdst/baseline/seo/meta` filter), and move the e2e expectation at `seo-clean-html.spec.ts:66-69` off the YOOtheme pair. Raised by both reviewers (code reviewer I-1, drift S1). Open question for staging: whether `get_option('site_icon')` is set, which decides which door wins.
2. **Shared slugs leave the translations in the sitemap.** `SiteMetaService.php:138-141`: `get_page_by_path('stageformulier')` returns one ID; baseline's SharedSlugs lets an EN/FR copy keep the slug, so Polylang's `page-en`/`page-fr` sitemap subtypes still list the form. Expand through `pll_get_post_translations()` when it exists. Code reviewer I-2.
3. **Placeholder titles get frozen into descriptions.** `2026-10-10-seo-descriptions.php:67-71` wrote "Lorem ipsum title about wat JOS voor ons oploste. Zo pakte Jos het aan voor Stad Leuven." into 11 local cases, and `jos_fill` never revisits a filled excerpt. Skip and report any text matching the template placeholders. Code reviewer I-3.
4. **The one-off re-triggers baseline's machine re-translation.** `wp_update_post` on `post_excerpt` changes the TextBundle hash, and `Retranslation.php:79-102` rewrites every EN/FR copy still marked unchecked. Either state that in the header or wrap the loops in `Retranslation::suspend(true)`. Code reviewer I-4.
5. **Runbook gap: `wp media regenerate --image_size=ntdst_og`.** Baseline README 608 requires it once `og_image_crop` is set; without it existing featured images ship uncropped. Add it to the STATE entry beside the descriptions one-off. Code reviewer I-5.
6. **Raw meta prefix in the one-off.** `2026-10-10-seo-descriptions.php:68,73` read `_jw_client` and `_jw_bio` with `get_post_meta`; the prefix is declared once and `CaseService.php:347-352` names this exact trap. Third instance of tool-url review N4, which has no recorded disposition. Use `ntdst_data()->get('case')->getMeta($id, 'client')`, or at least `JOSWORLD_CORE_META_PREFIX . 'client'`. Drift S2.
7. **A write that reports success it never checked.** `2026-10-10-seo-descriptions.php:52` discards `wp_update_post`'s return, so `done (N written)` can count rows that failed. Pass `true` and print the WP_Error. Drift S3.

## Note

- **The one-off is safe only under `wp eval-file`.** `wp_update_post` re-saves `post_content`; kses would mangle the builder layout JSON (verified: json_decode fails after `wp_kses_post` on pages 12 and 146). WP-CLI strips kses, so it works as documented. Guard it with a `has_filter('content_save_pre', 'wp_filter_post_kses')` exit. Code reviewer.
- **Four page excerpts are never read.** `ons-werk`, `team`, `jobs-stages`, `gratis-aanbod` resolve to the CPT archives (rewrite list verified), so baseline reads the CPT description, and the texts diverge from it. Drop the four slugs. Code reviewer.
- **team and vacature do not support `excerpt`** (`TeamService.php:110`, `VacatureService.php:117`), so the written descriptions are invisible and uneditable in wp-admin. Code reviewer.
- `SiteMetaService.php:159`: the ICO holds 16/32/48; declare `sizes="16x16 32x32 48x48"`. Blind `/favicon.ico` requests still 404 since the file lives under the theme and the closed payload cannot place it at the web root. Code reviewer.
- `jos_trim()` sits beside the public `SeoService::trimDescription()`; its sentence-end preference is a deliberate difference, worth one line saying so. Drift N2.
- `SITEMAP_TAXONOMIES` is a whitelist where a blacklist of `category`/`post_tag` says what is meant; `doelgroep` is already out via `is_taxonomy_viewable()`, and `SeoSitemapTest.php:40` skips that step so it asserts a removal WordPress does itself. Category and tag archives stay crawlable with a canonical. Drift N1.
- Silent skips: a slug missing on the target is not reported (`:59-62`). Pillow is an undocumented prerequisite of `2026-10-10-icons.py`. `(int) '192x192'` in `SiteMetaServiceTest.php:173` is brittle. The e2e hardcodes `/ons-werk/onze-energie/` and `/team/goele-van-dam/`; consider discovering the first published item as `bin/e2e.sh` does. The one-off bumps `post_modified` on about 32 posts, so `lastmod` and `article:modified_time` shift once per environment. Code reviewer.
- `composer.lock`: baseline's source URL is now `git@github.com:` while ntdst-core's entry still says `https://`; harmless for rsync deploys, cosmetic until a box with only an HTTPS token runs `composer update`. Code reviewer.
- Framework gaps for baseline, not defects here: a `sitemap_exclude` / `sitemap_taxonomies` config surface beside `sitemap_users_provider`, and `web_manifest` + `theme_color` beside `site_icon`. Drift reviewer.

Findings: 0 Blocking · 7 Should fix · 10 Note

---
## Fix pass (2026-10-10)
Fixed: S2 (translations of the form page leave the sitemap — unit-tested), S3 (placeholder text skipped and reported), S5 (regenerate + rewrite flush in STATE), S6 (`JOSWORLD_CORE_META_PREFIX`), S7 (`wp_update_post` errors surfaced), S4 (header states the re-translation and `post_modified` effects; `Retranslation::suspend` is test-facing, so not used), and the notes: kses guard, the four listing slugs dropped, `sizes="16x16 32x32 48x48"`, not-found reported, `jos_trim` comment.
Ruling: S1 (icons through baseline's `site_icon` and one writer) deferred — it needs a Site Icon attachment and a Customizer change on every environment two days before launch; the hand-written tags work and are tested. The right home is a baseline `web_manifest`/`theme_color` config beside `site_icon`, then drop `printIconTags` — cost if wrong: a duplicate icon link set, no functional harm.
