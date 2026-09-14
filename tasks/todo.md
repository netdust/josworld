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
