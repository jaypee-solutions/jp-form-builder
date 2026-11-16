---
status: accepted
---

# Block build with @wordpress/scripts

`@wordpress/scripts` builds `src/blocks` into gitignored `blocks/` for JSX + lint, deliberately deviating from the buildless jp-catalog-suite convention. The build step (CI, release, `build-release.sh`, tab indentation for `src/`, i18n script) is upstreamed now to wp-plugin-template as an optional Copier question `uses_blocks` (default false), so jp-form-builder adopts it via `copier update` instead of carrying local edits to template-owned files. `@wordpress/scripts` 28+ compiles JSX against the `react-jsx-runtime` script, which WordPress registers only from 6.6, so the `uses_blocks` variant and jp-form-builder require WordPress 6.6. The E2E mail-capture mu-plugin mapping stays plugin-local.

## Considered options

- Keep WordPress 6.4 with a custom classic-JSX babel config: rejected. It forks the build config to support versions no client site runs.
