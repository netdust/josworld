# Josworld — WordPress Starter Theme

A clean, minimal Bedrock starter theme wired into **ntdst-core**. Distilled from
the `stridence` LMS theme down to its reusable machinery: the Vite asset
pipeline, the `theme-config.php` data-driven setup, the NTDST bootstrap wiring,
and a token-driven Tailwind + Alpine front end. All LMS / domain code removed.

## Stack

- **Tailwind CSS 3** — utility-first styling, driven by CSS-variable design tokens
- **Alpine.js** — lightweight interactivity (mobile menu, dropdown, toast, ...)
- **Vite 5** — bundling, hashed assets, manifest, HMR dev server
- **ntdst-core** — framework (`NTDST_Bootstrap`, `NTDST_Theme`, router, response, ...)

## Setup

```bash
npm install        # install dev + runtime deps
npm run build      # production build → dist/ (+ dist/.vite/manifest.json)
```

For development with hot module reload:

```bash
npm run dev        # starts the Vite dev server on http://localhost:5173
```

When `WP_DEBUG` is on **and** no production manifest exists, the theme forks to
the Vite dev server automatically (see `services/frontend/hooks/ViteAssets.php`).
In production it reads `dist/.vite/manifest.json` and enqueues the hashed bundle.

> The theme **degrades gracefully before the first build**: with no manifest in
> production, no theme JS/CSS is enqueued (no fatal) — run `npm run build` once.

## How it wires into ntdst-core

`functions.php`:

1. Defines `JOSWORLD_VERSION` / `JOSWORLD_DIR` / `JOSWORLD_URI`.
2. Loads the helpers in `helpers/`.
3. Reads `theme-config.php` (data only), registers `NTDST_Bootstrap`, and boots
   core services @5 / feature services @15 on `after_setup_theme`.
4. Instantiates `NTDST_Theme` with the menus / image sizes / support / excerpt /
   assets blocks from the config.
5. Binds the frontend hook class(es) — currently just `ViteAssets` — via
   `->bind($theme)`.

## Layout

```
josworld/
├── style.css                 # theme header (Theme Name, textdomain)
├── functions.php             # ntdst-core wiring + nav walker
├── theme-config.php          # data-only config (menus, image sizes, modules)
├── header.php footer.php     # chrome
├── index.php front-page.php  # templates
├── page.php single.php 404.php searchform.php
├── partials/empty-state.php
├── helpers/                  # icons.php, formatting.php, templates.php
├── icons/                    # inline SVGs (menu, x, chevron-down, search, ...)
├── services/frontend/hooks/  # ViteAssets.php (Vite pipeline)
├── src/                      # main.js + css/ (tokens, base, components)
├── vite.config.js tailwind.config.js postcss.config.js package.json
└── dist/                     # build output (gitignored)
```

## Customising

- **Brand colours**: edit `src/css/tokens.css` — the palette there is a neutral
  placeholder marked `REPLACE WITH BRAND`. Everything else maps off those tokens.
- **Fonts**: add the brand webfont `<link>`s in `header.php` and point the
  `--font-*` tokens at them.
- **Menus**: `primary` + `footer` are registered via `theme-config.php`.
- **Business logic**: belongs in a `<project>-core` mu-plugin, not the theme.
