---
okf_version: "0.1"
---

# Form Builder knowledge

* [Forms are a locked custom post type](decisions/0001-forms-as-locked-cpt.md) - Forms stored as non-public CPT posts, admin-only by default.
* [Cache-safe submission without nonce](decisions/0002-cache-safe-submission.md) - Public nonce-less REST submit; token and Turnstile load on first interaction.
* [Spam pipeline order](decisions/0003-spam-pipeline.md) - Ordered checks so floods never trigger outbound calls; proxy-aware rate limit.
* [Lightweight phone normaliser](decisions/0004-lightweight-phone-normalizer.md) - Own E.164 normaliser instead of libphonenumber.
* [SMTP config precedence and secret storage](decisions/0005-smtp-config-precedence.md) - Constants beat option beats default; encrypted stored secrets.
* [Block build with @wordpress/scripts](decisions/0006-wp-scripts-block-build.md) - wp-scripts builds src/blocks into gitignored blocks/; WordPress 6.6 minimum.
* [Update checker as documented outbound exception](decisions/0007-update-checker-exception.md) - PUC polls the user's own GitHub repo; M365 token endpoint only when configured.
* [No entry storage in v1](decisions/0008-no-entry-storage.md) - Submissions are mailed, not stored.
* [Form backup, restore and clean uninstall](decisions/0009-form-backup-restore-and-clean-uninstall.md) - Uninstall removes all data; JSON backup and restore offered.
* [HTML mail with full templating](decisions/0010-html-mail-templating.md) - HTML multipart mail; layout and Body templates edited in the admin.
* [Confirmation mail requires Turnstile](decisions/0011-confirmation-requires-turnstile.md) - Confirmation only with Turnstile, one per address per hour.
* [Microsoft 365 SMTP with OAuth](decisions/0012-m365-smtp-oauth.md) - XOAUTH2 client-credentials for Microsoft 365 SMTP.
* [Per-language forms and REST locale](decisions/0013-per-language-forms-rest-locale.md) - REST switches to the page locale; one Form per language.
* [Stale cached forms validate against their revision](decisions/0014-stale-markup-revision-validation.md) - Cached markup carries a content hash; server validates against the matching revision.
* [Form styling with block design tools](decisions/0015-form-styling-with-block-design-tools.md) - Style variations, block supports, Field colours and widths on the Form block.
* [Domain language](references/domain-language.md) - Canonical terms; see GLOSSARY.md.
* [Template facts](references/template-facts.md) - wp-plugin-template v1.4.0 conventions.
* [Phone library evaluation](references/phone-library-evaluation.md) - Measured libphonenumber facts.
* [WordPress core facts](references/wordpress-core-facts.md) - Verified 7.1.4 REST and block behaviours.
* [Deployment and tooling facts](references/deployment-and-tooling-facts.md) - WP 6.6 JSX floor, M365 SMTP AUTH timeline, planned platform topology.
