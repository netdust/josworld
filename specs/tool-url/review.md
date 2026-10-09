Reviewed: 0579b28b4c17a4fcf9d6ec8995ffc7f7b8813ebe
The three reviewers are running. I'll wait for their reports and then compile the summary.

Security sentinel is done. Waiting on the code reviewer and the drift reviewer before compiling.

Two of three reviewers are in. Waiting on the drift reviewer.

Review of `feature/tool-url` is complete. All three reviewers reported.

**What was reviewed.** Head `origin/feature/tool-url` (0579b28), identical to the local branch. Production is `origin/main` from site.yml. The merge-base with production is 5b1da56, but that range contains everything already promoted onto staging, because the branch was cut from `origin/staging` (6d78647) rather than from production. The feature's own change is the single commit on top of staging, and that is the diff the reviewers were given. No plan exists at `specs/tool-url/`, so the review ran against the commit message, the docblocks and the STATE.md entry. Reviewers dispatched: the superpowers code reviewer, `security-sentinel` (the diff stores and outputs an editor-supplied URL), and `ntdst-drift-reviewer`. No `ARCHITECTURE-INVARIANTS.md` in the project, so `invariant-auditor` was skipped. Level: default `full` (the environment variable could not be read in this sandbox).

The code reviewer ran the suites in DDEV: unit and integration tests for the changed files pass. The security sentinel ran real `esc_url_raw` on hostile inputs through `ddev wp eval`. The drift reviewer's Part 1 (core's invariant commands) did not execute because shell access was denied to it; its findings come from reading the source.

## Blocking

None.

## Should fix

1. **Same-site link tools get downloaded instead of opened.** Form 3's stored confirmation is `<a href="{tool_file_url}" download>…</a>`; only the word was swapped, the `download` attribute stays. Browsers ignore it cross-origin, but a link to a page on the site's own domain saves the page as a file. Put the attribute behind a token, or strip it for link tools, with a unit test. Raised by the code reviewer. Lives in DB content plus `.yoo-tools/form3-action.php`, not in the diff.
2. **"http(s) only" is not what runs.** `ToolService.php:51`: `esc_url_raw` with the protocol list still passes `//evil.example/x`, `///evil.example`, `/wp-admin/` and `http:evil.example` (verified against real WordPress). No script risk, same reach as a normal https link, but the docblock and `docs/content-model.md` promise more than the guard delivers. Also require `wp_parse_url` scheme in http/https and a host; add those cases to `unsafeUrls` in `tests/Integration/ToolTargetTest.php:75-83`. Raised by security-sentinel (L1) and the code reviewer.
3. **The unit stub contradicts WordPress.** `tests/Unit/DownloadFormServiceTest.php:37-39` stubs `esc_url_raw` with `^https?://`; real WordPress turns `example.org/tool` into `http://example.org/tool`, so the "no scheme" case at line 204 asserts behaviour production does not have. Drop the case or move it to the integration test asserting the real result. Raised by security-sentinel (L2) and the code reviewer.
4. **Reads are publish-only, so the builder `target` is null on a draft or private tool.** `ToolService.php:50-54` uses `getMeta` with the default `'publish'` status; the builder preview of an unpublished tool shows an empty button. Baseline's resolvers and `CaseService.php:380` read `'any'` on purpose. Pass `'any'` in `targetUrl()`; the publish gate for visitors already lives in `DownloadFormService.php:64`. Raised by ntdst-drift-reviewer (D1); the code reviewer and security-sentinel noted it as fail-closed.
5. **A second answer to "is this tool a link?"** `DownloadFormService.php:74` re-reads `delivery` with its own default instead of asking `ToolService`, so the URL and the word `openen`/`downloaden` can drift apart. Have `ToolService` return the target and its kind together, inject it into `DownloadFormService`, delete line 74. Raised by all three reviewers (drift D3).
6. **The database-change script is not in the repo.** `.yoo-tools/` is gitignored (`.gitignore:211`), so the `form3-action.php` STATE.md tells staging to run exists only on this machine, and it reads `$row->value` with no null check. Commit it under a tracked path such as `scripts/once/`, or inline the exact steps in STATE.md. Raised by the code reviewer.
7. **Static `targetUrl()` bypasses the injected manager.** `ToolService.php:46-48` calls the global `ntdst_data()` while the constructor injects `NTDST_Data_Manager` at line 34; no other josworld service takes DI and then goes around it (`CaseService.php:380` uses `$this->dataManager`). Make it an instance method; callers get the service from the container. Raised by ntdst-drift-reviewer (D2); the code reviewer called it defensible given the name-called resolver.

## Note

- **`docs/architecture/CODE-MAP.md:19-20` is now false** ("no per-project source code exists"): `ToolService.php:61-79` is the second hand-wired `source.init` listener at priority -5, copied from `CaseService.php:206-224`. Baseline offers no seam for a computed builder field, so this is the only door; record the pattern in CODE-MAP or take the gap upstream to ntdst-baseline. Drift reviewer (B1).
- **File-scope `resolve_tool_target()`** (`ToolService.php:157-164`) follows the Case precedent, but YOOtheme resolves `'Class::method'` strings, so `ToolService::class . '::resolveTarget'` would keep the surface inside the class. Never use the `Service@method` form: YOOtheme's container would construct a fresh service and re-register the model. Change Case at the same time if changed. Drift reviewer (B2). The resolver has no test, unlike its Case twin in `tests/Integration/CaseLabelsTest.php`. Code reviewer.
- **`condition` is josworld's own feature, not core's.** Core ignores unknown declaration keys; `AdminUIService.php:96-141` plus `metabox-conditional.js` only hide the control, and the hidden field is still saved, so a stale `_jw_url` survives on a file tool (harmless, since `delivery` decides). `ToolTargetTest.php:85-93` asserts a schema key, not that anything is hidden; its name claims more than it tests. Drift reviewer (N1).
- **`'default' => 'file'` on the select** (`ToolService.php:99`) is read by nothing in core; the real default is the argument at line 50. Drift reviewer (N3).
- **`docs/tools/*`** (two standalone HTML tools and an xlsx, about 170 KB, no secrets) ride in the feature commit; they belong in their own commit, and the filename with spaces is awkward in scripts. Code reviewer.
- **Editorial trust boundary:** `capability_type` is `post`, so author-role accounts can publish a tool pointing at any http(s) host. No new privilege (kses already allows links in content); worth one line in content-model.md and `rel="noopener noreferrer"` on the external button. Security-sentinel.
- **Open copy** ("Je download staat klaar:", button "Download") still says download for link tools; already recorded in STATE.md.
- **Unverified:** the drift reviewer asks whether the metabox shows "Delivery" and "Url" instead of the Dutch labels, since core's `MetaboxGenerator.php:780` derives labels from the key. The code reviewer confirmed the show/hide works but did not check labels. Look at the tool edit screen during the shake-out.
- **`ToolTargetTest.php:21,44,53,61`** writes meta with raw `update_post_meta`; writing through the model's `updateMeta()` would exercise the prefix instead of restating it. Drift reviewer (N4).

Mitigated with evidence, per security-sentinel: metabox save is nonce- and capability-gated, the fields are not in REST, `javascript:`/`data:`/`vbscript:`/`mailto:` are rejected by real WordPress, no attribute breakout, the hidden `tool_id` is absint-ed and gated on type and publish status, a non-tool post object cannot read another type's meta, and the target URL was already in the page HTML before this branch.

Findings: 0 Blocking · 7 Should fix · 9 Note
