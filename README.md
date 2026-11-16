# JayPee Solutions Form Builder

JayPee Solutions Form Builder is a WordPress plugin to create basic forms like a contact form.

Scaffolded from [jaypee-solutions/wp-plugin-template](https://github.com/jaypee-solutions/wp-plugin-template).
Scaffolding changes (CI, scripts, hooks, lint/test config) arrive as pull requests
from the `template-update.yml` workflow, which runs `copier update` — edit them in
the template, not here.

## Requirements

- PHP 8.0+
- WordPress 6.4+
- Composer, Node 22.13+ (wp-env 12), and Docker or Podman (for `wp-env`)

## Development

```bash
composer install
npm install

composer lint          # PHPCS (WordPress Coding Standards)
composer lint:fix      # PHPCBF
composer test          # PHPUnit

npm run env:start      # main checkout uses 8888; linked worktrees use isolated ports
npm run test:e2e:run   # seed + Playwright
npm run env:stop
```

Linked git worktrees get their own deterministic wp-env ports (8900–8999); the main
checkout keeps 8888/8889. `WP_ENV_PORT`, `WP_ENV_TESTS_PORT`, `WP_ENV_URL`,
`WP_ENV_TESTS_URL` and `WP_ENV_HOME` override them. wp-env, integration and E2E commands
queue per plugin (`E2E_LOCK_DIR` moves the queue, `E2E_LOCK_DISABLE=1` turns it off);
`npm run test:e2e:ui` leaves the queue once the site is ready. If a site that does not
serve this checkout answers on the port, the E2E runner stops instead of testing it.
Seed shared site data in `tests/seed.php` idempotently: stable slugs or SKUs, create only
when absent. Specs import `test`/`expect` from `./support/fixtures`, `wpCli` from
`./support/wp-cli` and `withOptions` from `./support/options`, which restores every
changed option even when a test fails.

`composer lint` also validates `languages/*.po` with `msgfmt -c` and checks that every
`add_action`/`add_filter` name is fired by WordPress, starts with
`jp_form_builder_`, or matches a pattern in `scripts/hook-allowlist.txt`, where
third-party hooks go. The hook check reads the source from the wp-env cache and skips
until `npm run env:start` has downloaded it; CI fetches it.

`npm install` runs Husky, which points Git at `.husky/`; `composer hooks:check` verifies
that. `.husky/pre-commit` runs `composer lint`; `.husky/pre-push` runs `composer test`.

## Release

1. Bump the version in **both** places in `jp-form-builder.php` (the
   `Version:` header and the `VERSION` constant) — `scripts/check-version.sh`
   enforces that they agree — and in `readme.txt` (`Stable tag`).
2. Add the matching `## [X.Y.Z]` section to `CHANGELOG.md`.
3. Tag and push:

   ```bash
   git tag vX.Y.Z && git push --tags
   ```

`.github/workflows/release.yml` lints, tests, builds `build/jp-form-builder-vX.Y.Z.zip`
and publishes it as a GitHub release asset. `scripts/build-release.sh` installs
the runtime dependencies with `--no-dev` and prefixes them with `php-scoper`, so
this plugin cannot collide with another JayPee plugin bundling a different
version of the same library on the same WordPress install.

## Updates

Sites pull updates from GitHub through the Plugin Update Checker. A private
repository needs a read-only token, pasted under
**Settings → JayPee Solutions Form Builder Updates**; the "Test token" button there says
exactly what GitHub answered when a check fails.
