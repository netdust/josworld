# translation — flows

The shake-out flows of a branch with no spec. Proposed 2026-10-26; Stefan approves, strikes or adds.

Everything here is wp-admin on the local DDEV site, with the real DeepL key: "+" sends text to
DeepL and leaves drafts behind, so no flow is tagged `@e2e` (staging would spend the key on
every run). Each flow deletes what it created.

| # | Surface | What a person does | What they should see | Edges |
|---|---|---|---|---|
| 1 | Page "+" — Pagina's list, Polylang's "+" in the EN column | An editor clicks "+" on the Dutch page "Waarom Jos?" for English and saves the draft | The editor opens with an English title and the YOOtheme layout's texts in English; the saved draft reads "Unchecked" in the Translation column; ticking "I have checked this translation" and saving clears it; the draft's preview renders the layout in English | 8 the preview renders, not only the stored text · 2 an author who cannot edit the Dutch page gets no translation · 6 with no key the draft opens in Dutch, still flagged |
| 2 | Team "+" | An editor clicks "+" on a Dutch team member for English | Role and bio in English, the name unchanged, phone copied; after the copy changes its phone and the Dutch record is saved, the copy keeps its own phone | 5 a name with an accent stays byte for byte |
| 3 | Case "+" with diensten | An editor clicks "+" on a Dutch case for French, after one dienst got its French term by hand | The French case carries that dienst and no Dutch ones; the client name is copied | 1 a case whose diensten have no French term yet gets none |
| 4 | Unpublish travels | An editor unpublishes a Dutch team member whose English copy is published | The English copy goes to draft; publishing the Dutch record brings it back | — |
| 5 | Dienst "+" | An editor clicks "+" on a Dutch dienst for English and saves | The add form opens with the English name (and description) filled in by DeepL, language EN, linked to the Dutch term; saving makes it the English dienst | 2 an author who cannot edit the term gets an empty form · 6 with no key the form stays empty |

**Smoke (read-only, production too):** `/en/` answers 200 with `<html lang="en-US">`.

## Not driven

- DeepL's quality, and French formality: content, not behaviour.
- Re-translation of an untouched team copy after a Dutch text change: covered by baseline's own
  suite, and proven by hand on 2026-10-26 (shake-out of baseline 2.18).
