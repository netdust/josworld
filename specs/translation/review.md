# Review — feature/translation

Range d696286..5b707b5 (origin/main..feature/translation), no plan: a small change. Reviewed
2026-10-26 by superpowers' code reviewer, security-sentinel (a stored credential) and
ntdst-drift-reviewer; no ARCHITECTURE-INVARIANTS.md in the project, so no invariant-auditor. The
headless `make review` run could not start (sandbox denied `printenv`); this review ran in session.

## Blocking

None.

## Should fix

1. **Reverses a recorded decision** — josworld-core.php:51-53 (drift-reviewer S1). Stefan,
   2026-09-15: "A '+' translation is a ONE-TIME copy … Editing the Dutch post later changes
   nothing in the English one … Do not enable it." `field_sync`, `follow_status` and
   re-translation make copies follow the Dutch record. Intent: Stefan's to confirm, then record.
2. **Tool `file`/`url` and every featured image are forced from the Dutch record** —
   ToolService.php fields, baseline FieldMap (code reviewer 3, drift-reviewer S3). A French
   tool cannot keep a French PDF or link; a copy's own choice reverts at its save. No per-field
   opt-out for fact types exists. Intent: Stefan's.
3. **`dienst` terms exist only in Dutch** — database (code reviewer 2). With `field_sync`, every
   Dutch save copies taxonomies; Polylang drops a term with no translation, so en/fr cases lose
   their diensten. Fix: create en/fr `dienst` terms, or make `dienst` untranslated.
4. **The key does not travel with a deploy** — josworld-core.php:48 (code reviewer 1, sentinel
   S3). Without `DEEPL_API_KEY` in each server's `app/.env`, "+" opens a flagged Dutch copy and
   logs a warning. One key everywhere spends production quota on local and staging work.
5. **DeepL Free keeps submitted text** — baseline DeeplTranslator.php:57-60 (sentinel S2). "+"
   and re-translation send draft and private text and team bios. A `:fx` key is DeepL Free.
6. **CODE-MAP and content-model lack the new rules** — docs/architecture/CODE-MAP.md Polylang
   bullet, docs/content-model.md field tables (drift-reviewer S2): a text field holding a fact
   declares `'translate' => false`; a copy cannot keep its own non-text value.

## Note

- `composer.json` and `composer.lock` answer 200 on production; `.env` answers 403 on
  production and staging (checked 2026-10-26, headers only). Sentinel S1 does not hold.
- No DeepL `formality`: French follows DeepL's default (tends to "vous").
- Re-translation runs inside the Dutch save: a DeepL outage can stall "Update" up to ~1 min;
  autosave re-sends untouched copies' text.
- Existing copies take the Dutch facts and terms at the next Dutch save (local case 359).
- 2.17.0 adds `disable_media_enumeration`: logged-out attachment pages and `/wp/v2/media` 404.
- Hard-coded salts in tracked app/wp-config.php:75-82 (outside the diff).

Findings: 0 Blocking · 6 Should fix · 6 Note
