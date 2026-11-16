---
type: Reference
title: wp-plugin-template v1.4.0 facts
description: Verified template conventions that shape the plugin design.
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

# Citations

[1] Plan: `docs/implementation-plan.md`, section Template mapping

