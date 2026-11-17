# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Every `v*` tag needs a matching `## [X.Y.Z]` section here — the release workflow
extracts it as the GitHub release notes and fails without it.

## [Unreleased]

## [1.3.0] - 2026-11-17

### Added
- Conditional groups: a block that holds Fields shown only while a Condition on an earlier Field holds (Select or Radio buttons *is*, *is not*, *is one of*; Checkboxes *includes*; Consent *is ticked*; any Field *is filled* or *is empty*). The editor states the Condition on the canvas and offers only Fields before the group; the server evaluates every Condition itself, so Fields of a hidden group are neither validated nor mailed.
- Radio buttons and Checkboxes Fields with the options editor, required, help text and width; both appear in the Body template placeholders.

### Fixed
- The select caret is drawn as one shape, without a seam between two halves.

## [1.2.0] - 2026-11-17

### Added
- Two more Form block styles: *Card* (a raised surface with layered shadows) and *Liquid Glass* (a frosted, translucent surface with blur).

### Changed
- A cleaner *Standard* look: bold labels, a red required marker, muted help text, taller Fields with a 6 px radius, and one drawn select caret in every browser.

### Fixed
- Theme or wp-admin input rules no longer make text inputs square while selects and textareas follow the chosen style; all Field controls now look alike in the editor and on the page.
- The submit button no longer looks disabled before the visitor interacts with the Form; it dims only while a Submission is being sent.

## [1.1.0] - 2026-11-17

### Added
- Styling for the Form block: four style variations (Standard, Soft, Underlined, Rounded), the core color, typography, spacing and border tools, Field background, border and focus colors, Field border radius and width, and a Width (full, half, one third) per Field that stacks when the Form is narrow. Defaults follow the surrounding colors and can be set site-wide in Styles → Blocks → Form or with `settings.custom.jpfb` in theme.json; the README has a Styling section.

## [1.0.1] - 2026-11-17

### Fixed
- The Forms admin screens say "Add Form", "All Forms", "Form published." and so on instead of WordPress's Post labels ("Add Post"), also in the German translation; bulk actions report Forms instead of Posts.

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
