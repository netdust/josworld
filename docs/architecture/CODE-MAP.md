# josworld — CODE-MAP

Decisions and traps about how this codebase works. No inventories: a wrong map gets
trusted, so only what a session must not re-derive lands here.

## The content concept (2026-09-03)

- **A model is its card.** A CPT declares only the fields another page needs to show it
  (`docs/content-model.md` carries the table; `CardFieldsModelTest` pins it). The page
  itself is built in the YOOtheme builder on the post.
- **A post with a builder layout bypasses its single template.** YOOtheme renders such a
  post through `page.php`; the trimmed `single-<type>` templates are the fallback for a
  post without a layout. Never bind YOOtheme's `content` field on a builder page: it is
  the layout's own introtext.
- **Four starter layouts live in the builder library**, `Single · Case/Tool/Team/Vacature`,
  seeded from `specs/cpt-builder-singles/layouts/` (gitignored — the DB is the artifact;
  re-seed after a DB refresh). A relation that moved into the builder is a grid whose
  items bind one manually selected post through the singular `custom<Type>` query.
- **The ntdst-baseline `yootheme` module is the only builder bridge.** josworld-core opts
  in by assignment on `ntdst/baseline/modules`; no per-project source code exists. A
  `relation` to `attachment` resolves empty through it — media fields use `file`,
  `image`, `gallery`.
- **Every layout write goes through `.yoo-tools/yoo-content.php` as an admin.** KSES
  destroys a layout written any other way; a layout needs a root `version`.

## Boundaries

- `app/content/mu-plugins/josworld-core/` is the business logic (services, one per CPT,
  explicitly required and resolved through the container). The theme carries no
  templates and no data code.
- The team single is public and `exclude_from_search`; `email` and `phone` leave the
  model only through the builder source, never REST.
- The deploy payload is `content/mu-plugins` and `content/themes/josworld`; the
  framework packages are Composer-installed and gitignored.
