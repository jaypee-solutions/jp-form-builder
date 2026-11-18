# JayPee Solutions Form Builder: PRD

Plugin slug `jp-form-builder`, namespace `JayPee\FormBuilder`, scaffolded from wp-plugin-template v1.4.0. Implementation plan: [implementation-plan.md](implementation-plan.md).

## Problem

WPForms Lite + WP Mail SMTP paywall needed features (per-form Reply-To, confirmation mail, constant-locked SMTP) and phone home. Target: the user's client sites (DE/EN, DSGVO), block themes, Docker Compose, WP-CLI, Ansible.

## Users & context

- Site admins building contact-style Forms in the block editor. Editing Forms needs `manage_options` unless a site widens it with a filter ([ADR 0001](adr/0001-forms-as-locked-cpt.md)); editors place Form embeds on pages.
- Operators deploying via Docker Compose + Ansible: configuration by constants and WP-CLI, not the UI.
- Visitors (DE/EN) on full-page-cached block-theme sites; keyboard and screen-reader users included.

## Goals

Contact-style forms as blocks, reliable SMTP delivery, spam protection that works behind full-page caches, and zero unexpected outbound traffic.

## v1 scope

All must-haves from the brief:

- Forms live as non-public CPT `jp_form` posts edited in the block editor. One `jp-form-builder/form` container block holds the field blocks. Pages embed a form with the `jp-form-builder/embed` block ("Form"). Form ID = post ID. The server resolves the definition from that post's blocks and never from client data.
- Field blocks: name (single, or first/last), email (Reply-To), phone (E.164, default region DE), address (street + no., postcode, city, country select; German order), text (single line, e.g. company or subject), select (label/value options, placeholder, required), radio buttons and checkboxes (label/value options; v1.3), textarea, consent (required, privacy-policy link). Every field has label, required, help text and, where applicable, placeholder.
- Conditional groups (v1.3, [ADR 0016](adr/0016-conditional-groups.md)): a group block inside the Form shows its Fields only while one Condition on an earlier Field holds; no nesting; the server evaluates Conditions, ignores hidden Fields for validation and leaves them out of mails.
- Accessibility: labels, fieldset/legend for compound fields, `aria-describedby` for help and error, `aria-invalid`, an error summary with links that receives focus, a success message in `role="status"`, fully keyboard-operable. Theme-friendly CSS: inherits fonts and colours; the button uses `wp-element-button`.
- Styling (v1.1/v1.2, [ADR 0015](adr/0015-form-styling-with-block-design-tools.md)): on the Form block per placement and site-wide in Site Editor → Styles → Blocks → Form; style variations Standard, Soft, Underlined, Rounded, Card (raised surface with layered shadows) and Liquid Glass (frosted translucent surface); colour, typography, spacing and border controls incl. Field background, Field border and focus/accent colours; Field widths 100 %, 50 %, 33 % that stack on narrow containers. Field controls are class-qualified so theme or wp-admin input rules cannot restyle only some of them.
- Submission via REST with no nonce. Without JS, a `<noscript>` notice is shown and the submit button stays disabled. With JS, a Form activates on first interaction: only then are the fill-time token and Turnstile loaded ([ADR 0002](adr/0002-cache-safe-submission.md)). view.js pre-validates with the server's rules; the server stays authoritative.
- Spam protection: Cloudflare Turnstile (siteverify plus hostname and action check, fail closed), honeypot, signed minimum-fill-time token, per-IP rate limit (10 attempts per 600 s) with hashed IPs in transients and trusted-proxy CIDRs, max payload size, strict sanitisation, header-injection guard ([ADR 0003](adr/0003-spam-pipeline.md)).
- SMTP via `phpmailer_init`. Constants take precedence over the settings page; secrets are never echoed back. Microsoft 365 is supported with OAuth (XOAUTH2, client credentials) because Basic auth ends there ([ADR 0012](adr/0012-m365-smtp-oauth.md)). Test mail button plus `wp jp-form-builder mail test <to>`.
- Per-form Notification: Recipients, subject template with placeholders, Reply-To = Submitter. Mails are HTML with a plain-text alternative and full templating, all edited in the admin area: the site-wide Mail layout and site-default subjects/bodies on the settings page, per-form Body templates in the Form editor, each with a code editor, placeholder inserter and preview ([ADR 0010](adr/0010-html-mail-templating.md)). Optional Confirmation to the Submitter, off by default, available only while Turnstile is configured and limited to one per address per hour ([ADR 0011](adr/0011-confirmation-requires-turnstile.md)); it never echoes submitted content ([ADR 0008](adr/0008-no-entry-storage.md)).
- On mail failure: redacted log entry and a generic visitor error; the visitor's input stays in the form.
- Cached pages that show an older version of a Form keep working: the server validates against the Fields of that version ([ADR 0014](adr/0014-stale-markup-revision-validation.md)).
- Admin UI: a top-level Forms menu (all Forms, add Form, settings page with backup/restore and mail log; `manage_options`). The template's Updates page stays under Settings. Constants and WP-CLI for everything; i18n with `.pot` plus German translations.
- Multilingual sites: REST responses and mails use the locale of the embedding page; one Form per language ([ADR 0013](adr/0013-per-language-forms-rest-locale.md)).
- Backup and restore of Forms (and non-secret settings) as a JSON download/upload and via WP-CLI; clean uninstall that removes all plugin data including `jp_form` posts ([ADR 0009](adr/0009-form-backup-restore-and-clean-uninstall.md)).
- Unconfigured Turnstile does not block rendering: Forms run on honeypot + fill-time token + rate limit, with an actionable notice on the settings page ([ADR 0003](adr/0003-spam-pipeline.md)).

## Non-functional/hard constraints

- The only allowed outbound requests are Turnstile siteverify (server), the Turnstile `api.js` (browser, only after a visitor interacts with a Form) and SMTP.
- Documented exceptions: the template's update checker polling the user's own GitHub repo ([ADR 0007](adr/0007-update-checker-exception.md)), and the Microsoft token endpoint, only when Microsoft 365 OAuth is configured ([ADR 0012](adr/0012-m365-smtp-oauth.md)).
- No upsells; admin notices only for actionable config errors, shown only on the plugin's settings page and `jp_form` screens.
- GPL-compatible bundles only.
- PHP 8.0 / WP 6.6 minimums (6.6 is the floor for the block build's JSX runtime, [ADR 0006](adr/0006-wp-scripts-block-build.md)). Single-site only; network activation is not supported.

## Data processed (DSGVO)

- Submitted field values are processed in memory, sent by mail and not stored.
- An HMAC-SHA256 hash of the IP (keyed with `wp_salt('auth')`, truncated to 32 hex chars) is kept in a transient for the rate-limit window (default 600 s).
- An HMAC-SHA256 hash of the Confirmation address is kept in a transient for one hour to throttle Confirmations.
- Turnstile: the browser loads Cloudflare's script only after the visitor interacts with a Form; it processes IP and browser signals. The server sends secret + token only (no `remoteip`).
- The mail failure log stores time, form ID and the error message with addresses redacted.
- The Backup file contains Form definitions (Recipients, Body templates) and non-secret settings; it contains no Submissions and no secrets. Treat it as configuration.
- Ship privacy-policy suggestion text via `wp_add_privacy_policy_content()`, and the same DE/EN snippet in the README.

## Later phases

Out of v1:

- entry storage with admin list and retention deletion
- CSV export
- conditional logic beyond one Condition per group (AND/OR, nesting)
- multi-step forms
- file uploads
- payments
- webhooks/integrations
- a WPForms JSON importer
- per-form field placeholders in the confirmation mail

## Decisions from review

Answered 2026-11-13:

1. Country list: EU27 + CH, LI, NO, IS, GB (32 codes), default DE is sufficient.
2. Confirmation mail echoes no submitted content (prevents relay abuse). Confirmed.
3. Uninstall cleans up everything including `jp_form` posts; the plugin offers a backup download and a restore function (v1).
4. Turnstile unconfigured: forms run on the remaining protections (honeypot, fill-time token, rate limit) plus a settings-page notice.
5. HTML notification mail is in v1 with full templating support (not deferred).
6. Template changes are upstreamed now, as an optional `uses_blocks` Copier question in wp-plugin-template ([ADR 0006](adr/0006-wp-scripts-block-build.md)).

Grilling round 1, answered 2026-11-13:

1. Minimum WordPress 6.6 in the plugin and in the template's `uses_blocks` variant.
2. Forms need `manage_options`; filter `jp_form_builder_capability` widens it per site.
3. Top-level Forms menu holds the settings page; the template's Updates page stays under Settings.
4. A single-line text field is in v1; radio and checkbox groups come later.
5. Forms activate on first interaction (token fetch, explicit Turnstile render).
6. Confirmation only while Turnstile is configured, one per address per hour.
7. view.js pre-validates; the rate limit counts every attempt, default 10 per 600 s.
8. `GLOSSARY.md` holds the canonical terms.
9. Backup = Forms + non-secret, non-constant settings; one default Mail layout without per-form branding; Body templates allow no `img` or remote assets.
10. Trusted proxies accept CIDRs; client IP = rightmost untrusted `X-Forwarded-For` entry; settings notice when proxies look untrusted.
11. Microsoft 365 OAuth (XOAUTH2, client credentials) is in v1.
12. REST uses the embedding page's locale; one Form per language; WPML/Polylang integration later.
13. Single-site only.
14. Restore reuses the original ID when free, otherwise reports the mapping; embeds are not rewritten.

Review round 2, answered 2026-11-13:

1. Template branch: WP 6.6 floor for the `uses_blocks` variant and `templates/` in the release copy list; PR against wp-plugin-template `main`, then tag `v1.5.0`.
2. Stale cached markup validates against the matching revision; no match → 409 reload notice ([ADR 0014](adr/0014-stale-markup-revision-validation.md)).
3. Microsoft 365 via SMTP XOAUTH2 with client credentials; Graph and Google OAuth rejected ([ADR 0012](adr/0012-m365-smtp-oauth.md)).
4. After the review rounds, the scaffold and docs are committed as one chore commit; P1 starts once template `v1.5.0` is tagged.

Review round 3, answered 2026-11-16:

1. The Mail layout is edited on the settings page (code editor, live preview, reset, test mail); the theme-file override is dropped.
2. Body templates are edited in a Form editor modal with code editor, placeholder inserter and preview; a visual editor comes later.
3. Site-default subjects and bodies on the settings page; a Form's empty field uses them, then the built-in default.
4. `{all_fields}` stays generated and is not separately editable.

Implementation note, 2026-11-17: Microsoft 365 OAuth (P2b) is deferred because the first customer does not use Microsoft 365. It must ship before a client on Exchange Online needs it (Basic auth off by default from the end of December 2026); until then its settings are hidden.

Feedback after v1.0.1, answered 2026-11-17: the Form looked too plain and had no styling options besides CSS. Styling lives on the Form block with site-wide defaults in Global Styles, with style variations, design controls and Field widths (ADR 0015).

Feedback after v1.1.0, 2026-11-17: in the editor, theme/admin input rules made text inputs square while selects and textareas followed the Rounded style ("looks broken"). Field controls now use class-qualified selectors; Standard follows a cleaner reference look (bold labels, red required marker, muted help text, taller Fields, one drawn select caret); two more variations, Card and Liquid Glass, were added.

## Open questions

None.
