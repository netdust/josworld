# stackedWP

stackedWP is a WordPress boilerplate for projects managed with Git and Composer and Make. Much of the philosophy is inspired by Bedrock and best practice principles.

- Clean folder structure with security in mind (core in `app/wp/`, content in `app/content/`)
- Dependency management with Composer
- Easy build and deploy process with Make (Git-bundle deploys, Combell-style)
- The theme owns its own Vite build at `app/content/themes/<theme>/` — there is no root frontend layer

## Getting Started

Copy `.env.example` to `.env` and adjust it, then run `make setup`. The root `.env` is the canonical local env file; on deployed servers the same file lives next to `wp-config.php` (the `app/.env` leg of the load chain).

`make setup` configures DDEV (docroot `app/`), starts it, runs Composer, downloads WordPress core into `app/wp/` if missing, installs the theme's npm dependencies if the theme ships a `package.json`, and initializes the `staging` branch.

## Make targets

The daily drivers:

| Target | Purpose |
|--------|---------|
| `make setup` | Local setup: DDEV + Composer + WP core into `app/wp/` + theme npm install |
| `make dev` | Start DDEV and switch to a safe (non-main) branch |
| `make save` | Stage everything and commit with a prompted message |
| `make deploy` | Build a Git bundle (incl. theme asset build) and deploy to staging |
| `make ship` | Same as deploy, to production — confirmed, with a DB backup first |
| `make feature name=xyz` / `make finish` | Create `feature/xyz` from `staging` / merge it back |
| `make sync-to-local` | Pull the staging DB + uploads into the local site |
| `make backup` | Export the local DB and archive uploads to `backups/` |

Run `make help` for the full list — the help output is generated from the `##`
comments on the targets themselves, so it cannot drift from the Makefile.

Deploys ship the `app/` directory to the server root as a single-commit Git bundle; the theme's assets are built inside the bundle build dir, and `node_modules` is never deployed. Staging/production hosts, paths, and URLs are configured at the top of the Makefile. An optional root `.deployignore` file (rsync exclude patterns, one per line) is honored when the bundle is assembled, for keeping site-specific files out of deploys.

## Update model

**This stack is client-updatable by design.** After handover, WordPress core, plugin,
and theme updates work from wp-admin — Netdust involvement is not required. That is a
property the template guarantees, and it rests on three checkable facts:

1. **No `DISALLOW_FILE_MODS`.** Neither `app/wp-config.php` nor the environment defines
   it, so wp-admin's update/install UI stays enabled
   (`wp eval 'var_export(defined("DISALLOW_FILE_MODS") ? DISALLOW_FILE_MODS : false);'`
   → `false`, and `get_filesystem_method()` → `'direct'`).
2. **Core is installed by download — never version-locked in Git or Composer.**
   `**/wp/` is gitignored, no Composer manifest requires WordPress core, and deploys
   never ship or reset `app/wp/`. `make setup` provisions core once via
   `wp core download` (always the latest release); after that, wp-admin updates write
   straight to `app/wp/` and nothing in the deploy flow reverts them.
3. **Plugins and themes live as regular directories** under `app/content/plugins/` and
   `app/content/themes/` — untracked by default in `.gitignore`, so wp-admin
   installs/updates land as plain files that no deploy or Git operation stomps.
