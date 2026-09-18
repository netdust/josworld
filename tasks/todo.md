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
- [ ] Recompile the style on staging (theme.1.css is in the PARENT theme,
      outside deploy.payload — rsync never carries it).
