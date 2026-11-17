# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Every `v*` tag needs a matching `## [X.Y.Z]` section here — the release workflow
extracts it as the GitHub release notes and fails without it.

## [Unreleased]

### Added
- Added a protected Forms editor with configurable capability and REST read access.
- Added a settings page with constant-locked fields and encrypted secret storage.
- Added SMTP configuration, HTML mail layout previews, test mail, redacted failure logging, and WP-CLI mail and settings commands.
- Added the `/mail-preview` REST endpoint for authorized template previews.
- Added the reusable Form block, Field blocks, and server-rendered accessible Form embeds.
- Added Form parsing, defaults, published-Form lookup with revision resolution, country data, and the authenticated Forms picker endpoint.
- Added the block editor UI: Field previews and inspector controls (Field key, required, help text, type-specific options, Select options editor), Form settings with recipient checks and Turnstile-gated Confirmation, and the Form embed picker with a server-side preview.
- Added the Body template modal for Notification and Confirmation (code editor, placeholder inserter, sandboxed live preview with the Form's own Fields), a "Form settings" document sidebar panel that edits the Form block's settings, and an optional `fields` parameter for `/mail-preview`.
- Added the public Submission REST routes `GET /jp-form-builder/v1/forms/<id>/token` and `POST /jp-form-builder/v1/forms/<id>/submissions` with the spam pipeline (payload size, rate limit per resolved client IP, honeypot, signed fill-time token, Cloudflare Turnstile, per-revision validation), page-locale switching, Notification and Confirmation mails, and `Cache-Control: no-store` on every response.
- Added settings page notices on the plugin screens for a missing Turnstile configuration, an untrusted reverse proxy, and Forms whose Confirmation cannot be sent.
- Mail failures of plugin mail are logged with the Form ID.
- Added Backup and restore: a JSON Backup of every Form and the non-secret, non-constant settings, downloadable from the settings page (*Backup & restore*) and the Plugins list (*Back up forms*) or via `wp jp-form-builder backup export`, and restorable from the settings page or `wp jp-form-builder backup restore <file> [--overwrite] [--dry-run]`. Restore validates every Form, keeps free IDs and reports ID mappings. `JP_FORM_BUILDER_MAX_BACKUP_BYTES` limits the upload.
- Uninstall now removes every Form (any status, with revisions and meta) in addition to the settings, mail log, GitHub token, update checker state and transients; the README describes the backup-first workflow.
- Added privacy policy suggestion text under Settings → Privacy that describes mail-only Submissions, the hashed rate-limit and Confirmation values, Turnstile, the mail failure log and the update checker.
- Grouped the settings page into SMTP, Sender, Spam protection, Recipients, Mail, Test mail, Backup & restore and Recent mail failures sections.
- Documented the constants, WP-CLI commands, Turnstile setup, caching, trusted proxies, privacy snippets (German and English), outbound connections and the WPForms and WP Mail SMTP migration in the README; `readme.txt` now describes the plugin and its features.
- Added a German translation (`de_DE`, formal "Sie", plus a byte-identical `de_DE_formal`) with `.pot`, `.po` and script `.json` files, `npm run i18n:json`, and a Translations section in the README.

### Fixed
- The Reply-To header no longer wraps the display name in extra quotes and drops commas, which `wp_mail()` splits addresses at; the built-in Notification body names the page that held the Form.
- Validation errors name unlabelled Fields like the Form does ("Email is required.").
- The settings page rendered its Field loop as text instead of table rows.

## [0.1.0] - 2026-11-16

### Added
- Initial scaffolding from [wp-plugin-template](https://github.com/jaypee-solutions/wp-plugin-template).
