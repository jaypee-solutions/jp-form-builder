=== JayPee Solutions Form Builder ===
Contributors: jaypeesolutions
Tags: forms, contact form, smtp, privacy, turnstile
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A privacy-first form plugin: contact forms as blocks, SMTP delivery, Cloudflare Turnstile and no stored submissions.

== Description ==

JayPee Solutions Form Builder replaces WPForms Lite and WP Mail SMTP with one lean plugin. Forms are edited in the block editor, sent by SMTP and protected against spam without a database of personal data.

**Features**

* Forms as blocks: Name, Email, Phone, Address, Text, Select, Radio buttons, Checkboxes, Textarea and Consent fields, placed on pages with the Form block. Conditional groups show Fields only while an earlier answer calls for them.
* Accessible markup: labels, fieldsets, error summary with focus, status message, keyboard operable; inherits your theme's fonts and colours.
* SMTP settings on the settings page, as `JP_FORM_BUILDER_*` constants in `wp-config.php` or with WP-CLI. Constants lock the field; secrets are encrypted and never shown.
* Notification mail with Reply-To set to the sender, optional Confirmation mail, HTML mail layout and Body templates with preview, test mail.
* Spam protection that works behind full-page caches: Cloudflare Turnstile, honeypot, signed minimum fill time, rate limit per client IP, payload limit.
* No stored submissions: entries are sent by mail and not kept; only short-lived hashes are stored for the rate limit.
* Backup and restore of Forms, WP-CLI commands (`mail test`, `settings`, `form list`, `backup`), clean uninstall.
* Privacy policy suggestion text under Settings → Privacy, and a Turnstile snippet in German and English in the README.
* German translation (formal "Sie") for the interface, the editor blocks and visitor-facing messages.

**Outbound connections**

SMTP to your mail server; Cloudflare Turnstile (verification from the server, script in the browser only after a visitor interacts with a Form, only when Turnstile is configured); GitHub for plugin update checks. No analytics, fonts or other third-party assets.

Requires PHP 8.0 and WordPress 6.6. Single-site only. Microsoft 365 mailboxes are not supported yet; use an SMTP relay.

== Installation ==

1. Upload the plugin ZIP under Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Open Forms → Settings, enter the SMTP and Turnstile settings and send a test mail.
4. Create a Form under Forms → Add Form and add the Form block to a page.

== Changelog ==

= 1.3.0 =
* Added: Conditional groups (Fields shown only while a Condition on an earlier Field holds; checked on the server) and the Radio buttons and Checkboxes Fields. Fixed: seamless select caret.

= 1.2.0 =
* Added: Card and Liquid Glass styles. Changed: cleaner Standard look. Fixed: consistent Field shapes against theme/admin input rules; the submit button no longer looks disabled before interaction.

= 1.1.0 =
* Added: styling for the Form block: style variations (Standard, Soft, Underlined, Rounded), color, typography, spacing and border tools, Field colors and borders, Field widths; site-wide in Styles → Blocks → Form or theme.json.

= 1.0.1 =
* Fixed: the Forms admin screens use Form labels ("Add Form") instead of Post labels, also in German.

= 1.0.0 =
* First release: Forms as blocks, SMTP delivery, cache-safe spam protection, HTML mail templates, Backup and restore, German translation.

= 0.1.0 =
* Initial scaffolding.
