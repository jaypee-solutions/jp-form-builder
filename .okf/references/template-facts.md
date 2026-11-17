---
type: Reference
title: wp-plugin-template facts
description: Verified template conventions (v1.4.0, block tooling from v1.5.0) that shape the plugin design.
tags:
  - template
  - tooling
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Facts verified while orienting on the scaffold (slug `jp-form-builder`, namespace `JayPee\FormBuilder`).

1. PHP >= 8.0 (composer platform 8.0.30), WP >= 6.4, tested up to 7.1; wp-env core 7.1.4 on PHP 8.5; Node 22.13+.
2. Flat `includes/class-*.php` loaded by explicit `require_once`; no PSR-4 for plugin code, Composer autoload covers vendor only.
3. PHPCS WordPress standard; `composer lint` = phpcs + check-test-seams + check-i18n + check-hook-names. Hooks start with `jp_form_builder_`, exist in core, or are in `scripts/hook-allowlist.txt`.
4. Tests: PHPUnit 9 unit (namespace stubs), wp-env integration (`WP_UnitTestCase`, MockPHPMailer), Playwright E2E. Done criterion `OK (N tests, M assertions)`, exit 0.
5. Release via `scripts/build-release.sh` (composer no-dev, php-scoper prefix `JayPee\FormBuilder\Vendor`, zip). Scoper `exclude-functions /.*/` is safe only while bundled packages declare no global functions. `build/` is wiped, so wp-scripts must not output there.
6. Plugin Update Checker points at github.com/jaypee-solutions/jp-form-builder.
7. No JS build/lint in the template. jp-catalog-suite ships buildless blocks, `uninstall.php`, `docs/adr` + `.okf`; German is formal "Sie" in `de_DE.po`, byte-identical `de_DE_formal.po`.
8. CI: lint + unit in a `composer:2` container; integration job with wp-env + E2E; build job zips. Workflows, scripts and lint config are template-owned.
9. From v1.5.0 the `uses_blocks=true` variant adds `@wordpress/scripts` (build, `lint:js`, `lint:css`, `eslint.config.mjs`), raises the floor to WordPress 6.6 and copies `templates/` into release ZIPs.
10. `copier update` resolved "in favour of the template" drops local additions to template-owned files: the APM entries in `.gitignore` (`apm_modules/`, `HANDOFF.md`) had to be restored.
11. The integration PHPUnit run writes `.phpunit.result.cache` into the checkout; it is ignored locally (an upstream fix would set the cache path in `phpunit-integration.xml.dist`).
12. On this workstation port 8888 can be held by another project's podman container; run wp-env with `WP_ENV_PORT=8912 WP_ENV_TESTS_PORT=8913`.
13. The `uses_blocks` lint scripts only cover `src/blocks/`; this plugin widened them to `src/` and added `@wordpress/*` externals to `import/core-modules` (local edits to template-owned files, to be upstreamed).
14. The `uses_blocks` `.gitignore` entry `blocks/` is unanchored and also ignores `src/blocks/`, so new block sources were silently left out of commits; this plugin anchors it as `/blocks/`. Upstream fix needed.
15. wp-env pins `WP_HOME`/`WP_SITEURL`, so integration tests cannot change the site URL through the `home` option; use the `home_url`/`network_home_url` filters instead.
16. The template's `build-release.sh` copy list omits `uninstall.php`, so released ZIPs never clean up; this plugin adds it (upstream fix needed). The template's `i18n:pot` excludes every `build` directory, which also drops `admin/build`; exclude only the release staging paths.
17. A second wp-scripts entry (`src/admin` → `admin/build/`) serves the settings page, and `npm run test:js` (Node's built-in test runner) checks the JS ports of the Field-key rule and the phone normaliser against fixtures shared with PHPUnit.
18. The GitHub repo's ruleset protects `main` (pull request required, no approvals or status checks required, no force-push or deletion): every change, including release bumps, goes through a PR; tags are pushed directly. The pre-push hook runs `composer test`. A new repo gets Dependabot security updates that fail on transitive dev-toolchain packages; shipped runtime packages have no advisories (`npm audit --omit=dev`).

# Citations

[1] Plan: `docs/implementation-plan.md`, section Template mapping

