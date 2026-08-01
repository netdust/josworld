# stackedWP

stackedWP is a WordPress boilerplate for projects managed with Git and Composer and Make. Much of the philosophy is inspired by Bedrock and best practice principles.

- Clean folder structure with security in mind (core in `app/wp/`, content in `app/content/`)
- Dependency management with Composer
- Easy build and deploy process with Make (Git-bundle deploys, Combell-style)
- The theme owns its own Vite build at `app/content/themes/<theme>/` — there is no root frontend layer

## Getting Started

Copy `.env.example` to `.env` and adjust it, then run `make setup`. An optional `.env.local` (never committed) can override `.env` for local-only tweaks.

`make setup` configures DDEV (docroot `app/`), starts it, runs Composer, downloads WordPress core into `app/wp/` if missing, installs the theme's npm dependencies if the theme ships a `package.json`, and initializes the `staging` branch.

## Make targets

| Target | Purpose |
|--------|---------|
| `make setup` | Local setup: DDEV + Composer + WP core into `app/wp/` + theme npm install |
| `make setup-staging` | Initialize the staging remote (git init, first bundle, DB push, uploads) |
| `make setup-production` | Initialize the production remote (confirmed) |
| `make dev` | Start DDEV and switch to a safe (non-main) branch |
| `make save` (`make s`) | Stage everything and commit with a prompted message |
| `make deploy` (`make d`) | Build a Git bundle (incl. theme asset build) and deploy to staging |
| `make ship` | Same as deploy, to production — confirmed, with a DB backup first |
| `make feature name=xyz` | Create `feature/xyz` from `staging` |
| `make finish` | Merge the current feature branch back into `staging` |
| `make db-clean` | Delete spam/trash comments, revisions, transients; optimize the DB |
| `make db-backup` | Export the local DB to `backups/` |
| `make sync-to-local` (`make p`) | Pull the staging DB + uploads into the local site |
| `make sync-to-staging` | Push the local DB + uploads to staging (confirmed, staging DB backed up) |
| `make template-save name=x` | Export DB + content as a reusable template (local or GitHub) |
| `make template-load name=x` | Import a template — overwrites the local DB and content (confirmed) |
| `make template-list` | List available templates (local + GitHub) |
| `make status` | Show local branch/changes and the last staging commit |
| `make backup` | Export the local DB and archive uploads to `backups/` |
| `make rollback` | Emergency remote rollback (see the REVIEW note in the Makefile) |
| `make ssh` | Open an SSH shell in the staging path |

Deploys ship the `app/` directory to the server root as a single-commit Git bundle; the theme's assets are built inside the bundle build dir, and `node_modules` is never deployed. Staging/production hosts, paths, and URLs are configured at the top of the Makefile.

## Update model

**This stack is client-updatable by design.** After handover, WordPress core, plugin,
and theme updates work from wp-admin — Netdust involvement is not required. That is a
property the template guarantees, and it rests on three checkable facts:

1. **No `DISALLOW_FILE_MODS`.** Neither `app/wp-config.php` nor the environment defines
   it, so wp-admin's update/install UI stays enabled
   (`wp eval 'var_export(defined("DISALLOW_FILE_MODS") ? DISALLOW_FILE_MODS : false);'`
   → `false`, and `get_filesystem_method()` → `'direct'`).
2. **Core is never tracked by Git** — `**/wp/` is gitignored, and deploys never ship or
   reset `app/wp/`. Core is provisioned once at setup (Composer's
   `johnpbloch/wordpress` package, with `wp core download` as the Makefile fallback);
   after that, wp-admin updates write straight to `app/wp/` and nothing in the deploy
   flow reverts them.
3. **Plugins and themes live as regular directories** under `app/content/plugins/` and
   `app/content/themes/` — untracked by default in `.gitignore`, so wp-admin
   installs/updates land as plain files that no deploy or Git operation stomps.
