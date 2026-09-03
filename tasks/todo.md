# josworld — Tasks

- [ ] `npm install && npm run build` in the theme; set brand colors in `src/css/tokens.css`
- [ ] Set production domain + remote path in `site.yml`
- [ ] First deploy to staging
- [ ] Add an `origin` remote and push `main` + `staging` — the flow floor refuses every Makefile flow verb without it (2026-09-03)
- [ ] Commit and merge down the 2026-09-03 sync (deploy suite 1.1.0, core 5.2.0, baseline 2.3.0, yootheme bridge → baseline module) once a branch can be cut
- [ ] Upstream to the marketplace `netdust-wp/templates`: `set -f` on the deploy.exclude loops, `structure.webroot` in setup, project-named pull dump, and the flow-floor-aware promote test in deploy-test.sh
- [ ] TeamService single 301: use core 5.2's `redirect($url, 301)` instead of `wp_safe_redirect` + exit

