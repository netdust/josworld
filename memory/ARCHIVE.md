# josworld — Archive

History moved out of `memory/STATE.md` to keep it a snapshot (per this
project's `CLAUDE.md`). Newest section first.

---

## Archived 2026-08-03 (T12, content-model phase close) — theme-foundation phase

This is the full theme-foundation-phase STATE.md content, as it stood before
the content-model work (T01-T12) landed. Superseded by the current STATE.md
"Content model" section, but kept here since several of these findings
(YOOtheme LESS compile mechanics, Customizer exposure, font faces) remain
true and undocumented anywhere else.

### Current Phase: theme foundation (YOOtheme child, pre-Figma)

stackedWP install running at https://josworld.ddev.site. Gate: all tiers green.
Admin `/wp/wp-admin` (admin/admin). Production domain + remote path are still
TODO placeholders in `site.yml`.

**Branch: `feat/yootheme-child`** (4 commits, NOT merged to main):
- `1e31863` josworld theme → YOOtheme Pro 5.0.35 child
- `3947760` strip Tailwind/PostCSS, scaffold `less/theme.josworld.less`
- `5449d92` fix vite `base` for the stackedWP `/content/` layout
- `15f2807` map the real Josworld design system onto the style (Figma tokens)

### Theme architecture (decided 2026-08-03)

`josworld` is a **thin YOOtheme child** — mirrors the `todai-client/todai-child`
pattern. YOOtheme's builder owns rendering; the child carries no template files.

- Parent `yootheme` 5.0.35 copied from `todai-client`. **Gitignored** (41 MB,
  licensed, updates in place) — `.gitignore` ignores `app/content/themes/*` and
  re-includes only `josworld/`. It must be installed separately on any other host.
- Removed from the starter theme: `header/footer/front-page/page/single/404/`
  `index/searchform.php`, `partials/`, `Ntdst_Nav_Walker`, `josworld_fallback_menu`
  — a child's template files override the parent and would bypass the builder.
- Kept: ntdst-core bootstrap, `theme-config.php`, `helpers/`, `AssetHooks`, and the
  **Vite JS pipeline** (`src/main.js` = Alpine + the `ntdstAPI` REST wrapper, 5
  vitest tests). JS only — no CSS build. Do not reintroduce a CSS entry.
- `functions.php` enqueues parent `style.css` (handle `yootheme`) then the child's,
  filemtime-versioned, at priority 20.

### Non-obvious things a future session will otherwise rediscover the hard way

1. **Activating a child theme does not rewrite the `template` option.** The theme
   was activated as standalone before `Template: yootheme` was added, so the
   `template` option stayed `josworld` and the parent was never used — templates
   silently resolved to `theme-compat`. Fix: activate the parent, then re-activate
   the child. Verify with `get_template_directory()` (must point at `yootheme/`).
2. **PHPStan must exclude the YOOtheme parent** — `phpstan.neon` `paths:` includes
   all of `app/content/themes`. Without the exclude it analyses 41 MB of vendor code
   (1000+ errors). Baseline is 222 findings with it excluded.
3. **Regenerating the PHPStan baseline requires emptying it first** —
   `--generate-baseline` loads the existing baseline, and stale patterns abort the
   run with an internal error. Write `parameters:\n\tignoreErrors:\n` then regenerate.
4. **The E2E smoke test needs a real header.** `tests/E2E/smoke.spec.ts` asserts a
   `<header>` + nav landmark. YOOtheme renders nothing until a menu is assigned, so
   a fresh install FAILS this. Fixed here by creating a `Primary` menu assigned to
   YOOtheme's `navbar` location — **this lives in the DB, not in git**, and will not
   travel to Combell or to any new scaffold.
5. **YOOtheme style discovery**: `Styler::getThemes()` globs
   `{rootDir,childDir}/less/theme.*.less`, so a style in the CHILD theme is found.
   `@import` paths resolve relative to the LESS file → reach the parent via
   `../../yootheme/…`. Verified: `id=josworld name=Josworld`.
6. **YOOtheme compiles LESS in the BROWSER, not in PHP.** `Styler::resolveImports()`
   only gathers file contents + strips comments; the actual compile is less.js in
   the Customizer. There is no PHP compile step to call from the CLI — so to prove
   a style compiles, install `less@4` in the scratchpad and run `lessc` by hand.
   (Two traps when doing that: `head`-ing the output makes lessc exit 1 via SIGPIPE
   — check `${PIPESTATUS[0]}` or redirect to a file; and the local `lessc` FETCHES
   `@import url(...)` and inlines the `@font-face` rules, which the browser
   compiler does NOT do. Its absence from local output is expected, not a bug.)
7. **Brand fonts are picked in Customizer → Theme → Style → Fonts — never
   `wp_enqueue_style`, and no longer via an `@import` in the LESS either
   (removed in `e6d5727`, controller-decided: the choice belongs in the
   Customizer, not pinned in source).** Either route ends in the same
   `StyleFontLoader`, which downloads the woff2 files and self-hosts them
   (GDPR-friendly, same-origin, no preconnect needed) — but the selector keeps
   it editable. Enqueuing the same families in `functions.php` would load them
   twice AND from Google's CDN; a note at the enqueue site says so.
   The LESS `@jw-font-*` stacks only NAME the families + fallbacks; **pick
   Fraunces and Inter in the selector or the stacks fall back to Georgia/system-ui.**
8. **Figma's font names are optical-size CUTS, not families.** Figma reports
   "Fraunces 72pt SuperSoft" and "Inter 18pt"; Google serves them as `Fraunces`
   and `Inter` with the cut selected by the `opsz` axis. The CSS family name must
   be the plain one or the font silently falls back.
9. **Both `style.css` enqueues deliver only comments — this is KNOWN and
   deliberately left in place** (asked and decided 2026-08-03). The parent's
   `style.css` is 422 bytes of pure metadata header with zero CSS rules
   (YOOtheme ships its real CSS through the Styler), and the child's has a
   header + a placeholder comment. The FILE is mandatory either way — WordPress
   needs `style.css` to recognise a theme, and its `Template: yootheme` line is
   what makes josworld a child. Only the two `wp_enqueue_style` calls are
   optional; keeping them preserves the conventional child-theme shape and the
   escape hatch for one-off CSS. Do not "optimise" these away as a stray finding.
   (If the parent handle ever IS dropped, drop the child's `['yootheme']`
   dependency with it or the child stylesheet silently stops loading.)
10. **The child `style.css` header description is STALE** — it still says
   "Tailwind + Alpine via Vite", but Tailwind was stripped in `3947760`. Alpine
   and Vite are still accurate. Cosmetic, untouched so far.
11. **Customizer exposure, measured (not assumed): 139 of the style's variables
   ARE editable there; the ~90 `@jw-*` tokens are NOT.** The Customizer exposes
   by PATTERN whitelist (`packages/styler/config/styler.php`), so every
   `@global-*` / `@base-*` / `@button-*` / `@card-*` / `@navbar-*` / `@inverse-*`
   mapping written in section 2 shows up as a field — but `@jw-*` matches no
   pattern and stays invisible. (A handful of section-2 vars are also unexposed:
   `@button-font-size`, `@form-*`, `@container-*`, `@navbar-background`.)
   **The trap:** editing a colour under Customizer → Global → Colors overrides
   the MAPPED `@global-*` value while the `@jw-*` token it derives from still
   reads the old one — they silently diverge and the DB copy wins at runtime.
   **Decision (`9a017d7`, controller-decided): the LESS file is the source of
   truth; change brand values in section 1, use the Customizer for fonts and
   per-page work.** Making a token client-editable means renaming it to match an
   exposed pattern, moving its source of truth into the DB, outside git.
12. **"Theme.Josworld" in the Customizer's COMPONENT list is not a broken name.**
   (First read misdiagnosed it as the style picker — it is the variable-component
   list, alphabetically between Text and Thumbnav.) The styler groups variables
   by the FILE that declares them, and labels the group from the filename, so
   `theme.josworld.less` surfaces as its own group. Renaming the label therefore
   means renaming the FILE, which changes the style id everywhere and orphans
   saved style settings. **Decided: leave it, purely cosmetic.**
   Separately, the style PICKER name is correct — verified at runtime,
   `Styler::getThemes()` returns `id=josworld name=Josworld`. `getMeta()`
   regex-parses `Name:` from the file's FIRST comment block, so edits above that
   block are not risk-free; re-check the runtime name after touching the header.
13. **Figma MCP is rate-limited on a View seat** (Professional plan). The token
   extraction fit inside the budget, but a re-pull will not — budget calls, and
   prefer `get_variable_defs` (compact, authoritative) over `get_metadata` on a
   page node (291 KB, overflows the tool limit).

### Design system (extracted 2026-08-03, commit `15f2807`)

Source: Figma `bwFc8dsfRNScrQgOwUcXKW` (Jos-website), published VARIABLE
collection — global to the file, not per-page. An aviation/ATC consultancy
(skeyes, Eurocontrol, LVNL, DFS logos on the client strip).

- **Palette**: Jos Brown `#35070c` (ink/primary) · Brown 60% `#866a6d` (muted) ·
  Brown 10% `#ebe6e7` (lines) · Beige `#fcfcf7` (page bg) · Lemon `#fff794` ·
  Raspberry `#e60b66` (accent/CTA) · White.
- **Type**: Fraunces (display/headings/buttons) + Inter (everything else).
  Scale T1 76 / T3 40 / T4 22 / Intro 32 / Body-lg 24 / Body 18 / Button 18 /
  Nav 16 / Nav-2 12 / Disclaimer 12.
- **FOUR faces to select** in Customizer → Theme → Style → Fonts:
  **Fraunces 300 + 400**, **Inter 400 + 600**. No italics (nothing uses one;
  UIkit's italic blockquote is overridden to `normal`). A `700` in the compiled
  CSS comes from UIkit's own `<strong>`/`<b>` rule and is NOT a design token —
  load Inter 700 only if you want real bold instead of a synthesised faux-bold.
  Fraunces is variable (`opsz`); take the variable option if offered, since
  Figma's "72pt SuperSoft" is the `opsz=72` cut.
- **DEVIATION — Instrument Sans is deliberately NOT loaded** (`1f2b12b`,
  controller-decided). Figma sets disclaimers in it, but it had exactly one job
  (the 12px Disclaimer style) and is near-indistinguishable from Inter at that
  size, so `@jw-font-disclaimer` points at Inter and the third family is not
  loaded. The restore path is written at the variable; `@jw-t-disclaimer` still
  records the Figma provenance, so the deviation is visible from either end.
- **Two values are DERIVED, not extracted** — both flagged `TODO(figma)` in-file:
  **T2** (no such variable is published; interpolated between T1 and T3) and the
  **spacing/radii scale** (not published; 8px system matching the observed
  32px frame padding / 80px page gutter). Confirm against node `625:2790`.
- **No mobile scale exists** — every Figma variable is suffixed `desktop` and the
  file ships no mobile frames. Rather than invent one, the desktop sizes are the
  large-screen end of UIkit's native `-m` breakpoint pattern.

### Site structure (created 2026-08-03 — LIVES IN THE DB, NOT GIT)

⚠ Like the E2E menu (note 4), all of this is local DDEV database state. It will
NOT travel to Combell or to a fresh scaffold. Re-creating it is a `wp` script,
not a `git pull` — worth turning into a seed script before first deploy.

**13 page stubs**, empty, Dutch slugs, published:

| Page | Slug | ID |
|---|---|---|
| Homepage | `home` (front page) | 11 |
| Waarom Jos? | `waarom-jos` | 12 |
| Diensten | `diensten` | 13 |
| Samenwerken | `samenwerken` | 14 |
| Ons werk | `ons-werk` | 15 |
| Team | `team` | 16 |
| Voor overheden | `voor-overheden` | 17 |
| Voor social profit & duurzame ondernemers | `voor-social-profit` | 18 |
| Blog & Nieuws | `blog-nieuws` (posts page) | 19 |
| Gratis aanbod | `gratis-aanbod` | 20 |
| Jobs & stages | `jobs-stages` | 21 |
| Contact | `contact` | 22 |
| Privacy & cookie policy | `privacy-cookie-policy` | 23 |

`show_on_front=page`, `page_on_front=11`, `page_for_posts=19`.

**Menus.** The theme declares `primary`/`footer` locations, but YOOtheme renders
navigation from ITS OWN locations — the existing `Primary` menu is assigned to
**`navbar`**, which is what the E2E smoke test depends on. Extended that menu
rather than creating a competing one.
- `Primary` → location `navbar`: Waarom Jos? · Diensten (children: Voor overheden,
  Voor social profit, Samenwerken) · Ons werk · Team · Blog & Nieuws ·
  Jobs & stages · Contact
- `Footer` → location `footer`: Gratis aanbod · Contact · Privacy & cookie policy

**The menu structure is a GUESS** — the Figma Nav bar is a component `<instance>`
whose master could not be read (MCP rate limit), so labels/order/nesting were
inferred from page names. Nesting the two audience pages + Samenwerken under
Diensten is the most defensible reading, not a confirmed one. Correct in
Appearance → Menus; re-read node `13:119` when Figma access returns.

**Not yet built — repeating content needs a `josworld-core` mu-plugin.**
`theme-config.php` states business logic belongs there, not in the theme, and
ntdst-core ships no CPT registration. Figma draws these as one-template-many-items:
- **cases** (`Ons werk /`) — 7 drawn (Ivago, Stad Leuven, Sugg, Tonuso, UitX,
  VLIRUOS, VUB), 2 template variants (interview / no-interview)
- **blog** — native `post` is fine (posts page already wired to Blog & Nieuws)
- **gratis aanbod** — item template drawn
- **vacatures** — 3 states drawn (2 vacatures / geen vacatures / Stageformulier)

(This section's "Not yet built" list is what T01-T12 — see current
STATE.md — went on to build as `josworld-core`: `case`, `team`, `vacature`,
`tool` post types + the `dienst` taxonomy, superseding the plan above.)
