# josworld — Tasks

- [ ] `npm install && npm run build` in the theme; set brand colors in `src/css/tokens.css`
- [ ] Set production domain + remote path in `site.yml`
- [ ] First deploy to staging
- [ ] Add an `origin` remote and push `main` + `staging` — the flow floor refuses every Makefile flow verb without it (2026-09-03)
- [ ] Commit and merge down the 2026-09-03 sync (deploy suite 1.1.0, core 5.2.0, baseline 2.3.0, yootheme bridge → baseline module) once a branch can be cut
- [ ] Upstream to the marketplace `netdust-wp/templates`: `set -f` on the deploy.exclude loops, `structure.webroot` in setup, project-named pull dump, and the flow-floor-aware promote test in deploy-test.sh
- [x] Build the remaining singles (11 cases, 6 tools, 7 team, 1 vacature) in the builder from the library starters — 2026-09-03, all 30 posts carry a layout; editors replace the placeholder prose
- [ ] Raise the attachment/`inherit` resolver gap in ntdst-baseline (FieldTypeBridge::$relatedPosts) — see STATE 2026-09-03
- [x] Design fixes 2026-09-10 on staging @ 519b3b9 — DB content, config and recompile re-applied the same sitting
- [ ] Replace the placeholder "© Voornaam Naam" credit on case 146 (Onze energie) with the real photographer, or remove it
- [ ] Three design e2e tests fail against the running site — `case pills are 26px tall`, `team tiles space the text under the photo like the tool tiles`, `stacked tiles keep a 64px row gap` (all `locator.evaluate` → null, so the selectors match nothing). NOT a polylang regression: reproduced on 7cbdbf5 without that change. They went unseen because `bin/e2e.sh`'s tsc preflight had been failing since `0ab3065` (2026-09-11) — the tier never reached the tests. `ade86d3` fixed the preflight, which is what surfaced them.

## Design fixes 2026-09-18 — staging re-apply ledger

A deploy carries LESS and tests only. Everything below is DB state or a
compiled artifact and must be re-applied on staging in the SAME sitting.

- [ ] **Config diff local↔staging first** (the 09-10 rule). Already known:
      staging carries `nav_menu_locations[dialog-mobile] = 20` ("Mobiel") that
      local does not — it renders a second, unstyled mobile nav (fix #9).
- [ ] template `WXXvROde` (archive-vacature): grid at
      `layout/children/1/children/0/children/0/children/1` → `props.panel_link: true` (#1)
- [ ] pages `11, 12, 14, 17, 18, 64` — full `page set` from the LOCAL JSON (the 09-10
      procedure). They carry #7 (stack: `position_sticky_offset` `50vh - 50%` → `48`,
      rows 2+ `margin_top: xlarge`) and, on 12 only, #5 (impactmarketing button
      `margin_top: default`). Transform is `.yoo-tools/stack-fix.py`.
      11, 17, 18, 64 also carry #4 (logo wall: `slider_parallax: true`,
      autoplay off, still infinite) — `.yoo-tools/logo-scroll.py`.
- [ ] template `MnIgjiB4` (error-404) is NEW — create it on staging with
      `template set new .yoo-tools/tpl-404.json` (#6). Its artwork
      `themes/josworld/images/jos-404.c1fd4329.svg` is tracked and rides the deploy.
- [ ] Recompile the style on staging (theme.1.css is in the PARENT theme,
      outside deploy.payload — rsync never carries it).

## Site motion 2026-09-18 — staging re-apply ledger

The deploy carries the reduced-motion LESS rule and `tests/E2E/site-motion.spec.ts`
only. The motion itself is DB state on 41 layouts and must be re-applied on staging
in the SAME sitting. Do NOT `page set` local JSON over staging for this — run the
transform against STAGING's own layouts, so staging content is kept; it only
touches section `animation` / `animation_delay` and grid/gallery `item_animation`.

- [ ] Per layout: `get` → `.yoo-tools/motion.py <src> <dst>` → `set` (always `--user=1`).
      Idempotent; skips the hero, card stacks and the logo wall by rule. Local run:
      313 of 313 eligible sections across 30 pages + 10 templates (error-404 has none).
- [ ] Nearly every layout needs `no-lint`: they carry pre-existing `unknown-prop` /
      `select-value` lint errors. Only bypass when the error count equals the live copy's.
- [ ] Templates: all 11 ids; `-98_Pkh4` (archive-post) starts with a dash — pass it after `--`.
- [ ] Recompile the style on staging (the reduced-motion rule; theme.1.css is outside the payload).
- [ ] Check after: a page scrolled (cascade), reduced motion on (nothing hidden), a card-stack
      page and the logo wall unchanged.
