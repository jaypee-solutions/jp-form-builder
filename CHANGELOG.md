# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Every `v*` tag needs a matching `## [X.Y.Z]` section here — the release workflow
extracts it as the GitHub release notes and fails without it.

## [Unreleased]

## [1.0.0] - 2026-11-17

First release: contact-style Forms as blocks, SMTP delivery and spam protection without third-party services beyond Cloudflare Turnstile.

### Added
- Forms as protected `jp_form` posts edited in the block editor: name, email, phone, address, text, select, message and consent Fields, a "Form settings" sidebar panel, and the Form block for pages with a picker and live preview.
- Accessible front end: labels, autocomplete hints, required markers, browser checks with the server's rules, a focused error summary with links, `aria-invalid` and status messages; nothing is requested until the visitor interacts with the Form.
- Cache-safe Submissions over public REST routes without a nonce: payload limit, per-IP rate limit behind trusted proxies (CIDRs), honeypot, signed fill-time token, Cloudflare Turnstile (hostname and action checked, fails closed) and validation against the Form version a cached page shows.
- SMTP via `phpmailer_init` with constants that lock settings and encrypted stored secrets; test mail on the settings page and via WP-CLI; redacted log of the last mail failures.
- HTML mails with a plain-text part: a site-wide Mail layout plus site-default and per-Form Body templates, each with a code editor, placeholder inserter and preview; the Confirmation to the Submitter requires Turnstile, is limited to one per address per hour and never repeats submitted content.
- Replies and mails in the language of the page that embeds the Form.
- Backup and restore of Forms and non-secret settings (settings page, Plugins list, WP-CLI) and a full uninstall that removes every Form and setting.
- WP-CLI: `wp jp-form-builder mail test`, `settings list|set|delete`, `form list`, `backup export|restore`.
- Privacy policy suggestion text, a German translation (formal "Sie", also as `de_DE_formal`) and README documentation for constants, hosting, privacy snippets and the migration from WPForms and WP Mail SMTP.

## [0.1.0] - 2026-11-16

### Added
- Initial scaffolding from [wp-plugin-template](https://github.com/jaypee-solutions/wp-plugin-template).
