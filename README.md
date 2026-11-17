# JayPee Solutions Form Builder

A privacy-first WordPress plugin for contact-style forms, built as a lean replacement for WPForms Lite and WP Mail SMTP.

## What it does

- **Forms as blocks.** Forms are edited in the block editor under **Forms** and placed on pages with the **Form** block. Fields: Name (single or first/last), Email, Phone (normalised to E.164), Address (German order, 32 countries), Text, Select, Textarea and Consent with a privacy-policy link. Every Field has a label, required flag, help text and, where it fits, a placeholder.
- **Accessible and theme-friendly.** Labels, fieldsets, `aria-describedby`/`aria-invalid`, a focused error summary with links, a `role="status"` success message, full keyboard use. The CSS inherits fonts and colours; the button uses `wp-element-button`.
- **Mail by SMTP.** Notification to the Recipients with Reply-To set to the Submitter, an optional Confirmation to the Submitter, HTML mail with a plain-text alternative, editable Mail layout and Body templates, test mail, redacted failure log.
- **Spam protection that works behind full-page caches.** Cloudflare Turnstile, honeypot, signed minimum fill time, per-IP rate limit, payload limit, header-injection guard. Submissions use a public REST route without a nonce.
- **No stored submissions.** A Submission lives in memory until its mails are sent. The only stored personal data are short-lived hashes (see [Privacy](#privacy-dsgvo)).
- **Operator friendly.** Every setting can be a constant in `wp-config.php` or set with WP-CLI; Backup and restore of Forms; clean uninstall.

## Requirements

- PHP 8.0 or newer
- WordPress 6.6 or newer, **single site only** (network activation is not supported)
- An SMTP account for reliable delivery (optional: without a host the default WordPress mailer is used)

## Installation

1. Download `jp-form-builder-vX.Y.Z.zip` from the [latest release](https://github.com/jaypee-solutions/jp-form-builder/releases/latest).
2. Upload it under **Plugins → Add New → Upload Plugin**, or run `wp plugin install jp-form-builder-vX.Y.Z.zip --activate`.
3. Open **Forms → Settings**, configure SMTP and Turnstile (or define the constants below), and press **Send test mail**.
4. Create a Form under **Forms → Add Form**, then add a **Form** block to a page and pick the Form.

Updates arrive through the plugin list (see [Updates](#updates)).

## Configuration

Settings live under **Forms → Settings**, grouped into SMTP, Sender, Spam protection (Cloudflare Turnstile), Recipients, Mail, Backup & restore and Recent mail failures. Per-Form options (Recipients, subject, Body templates, Confirmation) are in the Form editor.

**A constant always wins.** A setting defined as a constant is shown read-only with "Set by constant." and cannot be changed on the settings page or with WP-CLI. Define constants in `wp-config.php` above `/* That's all, stop editing! */`, for example from the environment of a Docker Compose service:

```php
define( 'JP_FORM_BUILDER_SMTP_HOST', 'smtp.example.com' );
define( 'JP_FORM_BUILDER_SMTP_USER', getenv( 'SMTP_USER' ) );
define( 'JP_FORM_BUILDER_SMTP_PASSWORD', getenv( 'SMTP_PASSWORD' ) );
```

Secrets (`smtp_password`, `turnstile_secret_key`) are never printed back. When you save them on the settings page or with WP-CLI they are encrypted at rest (libsodium secretbox, key derived from the site's `secure_auth` salt). Changing the salts makes them undecryptable: enter them again. A secret defined as a constant is stored wherever you keep `wp-config.php`.

### Constants reference

Setting keys are what `wp jp-form-builder settings` uses.

| Constant | Setting key | Default | Effect |
| --- | --- | --- | --- |
| `JP_FORM_BUILDER_SMTP_HOST` | `smtp_host` | empty | SMTP server. Empty means SMTP is off and the default WordPress mailer is used. |
| `JP_FORM_BUILDER_SMTP_PORT` | `smtp_port` | `587` | SMTP port (1–65535). |
| `JP_FORM_BUILDER_SMTP_ENCRYPTION` | `smtp_encryption` | `starttls` | `none`, `starttls` (port 587) or `smtps` (implicit TLS, port 465). With `none`, PHPMailer's opportunistic TLS is switched off too. |
| `JP_FORM_BUILDER_SMTP_AUTH` | `smtp_auth` | `true` | Authenticate with user and password. |
| `JP_FORM_BUILDER_SMTP_USER` | `smtp_user` | empty | SMTP user name. |
| `JP_FORM_BUILDER_SMTP_PASSWORD` | `smtp_password` (secret) | empty | SMTP password. |
| `JP_FORM_BUILDER_FROM_EMAIL` | `from_email` | empty | Sender address; also used as the envelope sender. Without `FORCE_FROM` it only replaces WordPress's default `wordpress@your-domain`. |
| `JP_FORM_BUILDER_FROM_NAME` | `from_name` | empty | Sender name, applied together with the address. |
| `JP_FORM_BUILDER_FORCE_FROM` | `force_from` | `false` | Override the sender of every mail WordPress sends, not only the default one. |
| `JP_FORM_BUILDER_TURNSTILE_SITE_KEY` | `turnstile_site_key` | empty | Turnstile site key. Turnstile is active only when both keys are set. |
| `JP_FORM_BUILDER_TURNSTILE_SECRET_KEY` | `turnstile_secret_key` (secret) | empty | Turnstile secret key. |
| `JP_FORM_BUILDER_DEFAULT_RECIPIENT` | `default_recipient` | empty | Recipient (comma-separated list allowed) for Forms that set none. If empty, the site administrator email is used. |
| `JP_FORM_BUILDER_MAX_PAYLOAD_BYTES` | constant only | `32768` | Largest accepted Submission body; larger requests get 413. |
| `JP_FORM_BUILDER_RATE_LIMIT` | constant only | `10` | Attempts per client IP and window. Every attempt counts; the next gets 429. |
| `JP_FORM_BUILDER_RATE_WINDOW` | constant only | `600` | Rate-limit window in seconds. |
| `JP_FORM_BUILDER_MIN_FILL_SECONDS` | constant only | `3` | Minimum seconds between activating a Form and submitting it. The filter `jp_form_builder_min_fill_seconds` can override it. |
| `JP_FORM_BUILDER_TRUSTED_PROXIES` | constant only | empty | Comma-separated IPs and CIDR ranges of your reverse proxies; see [Trusted proxies](#trusted-proxies). |
| `JP_FORM_BUILDER_MAX_BACKUP_BYTES` | constant only | `2097152` | Largest Backup file the settings page accepts. |
| `JP_FORM_BUILDER_SMTP_AUTH_METHOD`, `JP_FORM_BUILDER_M365_TENANT_ID`, `JP_FORM_BUILDER_M365_CLIENT_ID`, `JP_FORM_BUILDER_M365_CLIENT_SECRET` | `smtp_auth_method`, `m365_*` | — | Reserved for Microsoft 365 OAuth, which is not supported yet. They have no effect. |

The mail texts (`mail_layout`, `notification_subject`, `notification_body`, `confirmation_subject`, `confirmation_body`) are settings without constants; edit them on the settings page.

Filters: `jp_form_builder_capability` (capability needed to edit Forms, default `manage_options`) and `jp_form_builder_turnstile_hostnames` (hostnames Turnstile may report, default the home URL host).

## WP-CLI

```bash
wp jp-form-builder mail test <to>
wp jp-form-builder settings list [--format=<table|json|csv|yaml>]
wp jp-form-builder settings set <key> [<value>]
wp jp-form-builder settings delete <key>
wp jp-form-builder form list [--format=<table|json|csv|yaml>]
wp jp-form-builder backup export [<file>]
wp jp-form-builder backup restore <file> [--overwrite] [--dry-run]
```

- `mail test` sends the test mail through the configured SMTP and exits non-zero with the error on failure.
- `settings list` prints key, value and source (`constant`, `option` or `default`); secrets show as `********` or `not set`.
- `settings set` stores the value. Omit `<value>` to read it from STDIN, which keeps secrets out of the process list and shell history: `printf '%s' "$SMTP_PASSWORD" | wp jp-form-builder settings set smtp_password`. Yes/no settings take `true`, `false`, `1`, `0`, `yes`, `no`, `on` or `off`. It fails for unknown keys, for settings locked by a constant and for values a setting cannot take.
- `settings delete` removes the saved value, so the default applies again.
- `form list` prints ID, title and status of every Form.
- `backup export` writes a Backup to `<file>`, or to stdout without a file; `backup restore` is described under [Backup, restore and uninstall](#backup-restore-and-uninstall).

## Spam protection and Turnstile

Every Submission passes these checks in order, so floods never reach a paid or external service:

1. Payload size, then the Form lookup.
2. Rate limit per client IP (default 10 attempts per 600 s).
3. Honeypot: a filled hidden field gets a normal-looking answer and no mail.
4. Signed token: it proves the Form was loaded and left alone for the minimum fill time (default 3 s).
5. Cloudflare Turnstile.
6. Field validation, with server-side rules that never trust the browser.

**Set up Turnstile**

1. In the Cloudflare dashboard open **Turnstile**, add a widget and list every hostname the site is served from.
2. Copy the site key and the secret key into **Forms → Settings → Spam protection**, or define `JP_FORM_BUILDER_TURNSTILE_SITE_KEY` and `JP_FORM_BUILDER_TURNSTILE_SECRET_KEY`.
3. Load a page with a Form and focus a field: only then does the browser fetch Cloudflare's script. Before that, the page makes no request to Cloudflare.

The server verifies the response with Cloudflare and accepts it only for the site's home URL host and the plugin's own action. Use `jp_form_builder_turnstile_hostnames` to allow more hostnames. Turnstile **fails closed**: if Cloudflare cannot be reached, the visitor sees a retry message and nothing is sent.

Without keys, Forms still render and rely on honeypot, minimum fill time and rate limit. The settings page then shows a notice, and **Confirmations are not sent**: a Confirmation goes to an address the Submitter chooses, so it needs Turnstile, and each address gets at most one per hour.

Visitors without JavaScript see a notice and the submit button stays disabled, because Turnstile has no non-JavaScript mode.

## Hosting notes

### Page caches

Form pages can be cached as full pages. Submissions are public REST calls without nonces, and the token request answers with `Cache-Control: no-store`.

- **Exclude `/wp-json/jp-form-builder/` from page caches** such as Varnish, Nginx FastCGI cache or CDN rules. Sites without pretty permalinks use `?rest_route=/jp-form-builder/` instead; exclude that too.
- Purging the cache after editing a Form is optional: a cached page that still shows an older version of the Form is validated against that version's Fields (the server keeps the last 10 revisions). Recipients, subject, Body templates and Confirmation always come from the current Form. If no matching version exists, the visitor is asked to reload.

### Several WordPress replicas

The rate limit and the Confirmation throttle count in transients. Replicas need a **shared object cache** (Redis, Memcached) or the default database transients, so that all replicas see the same counters. A per-container file or in-memory cache gives every replica its own limit.

### Trusted proxies

Behind a reverse proxy, `REMOTE_ADDR` is the proxy, so all visitors would share one rate limit. List your proxies in `JP_FORM_BUILDER_TRUSTED_PROXIES` as comma-separated IPs or CIDR ranges. The client IP is then the rightmost `X-Forwarded-For` entry that is not a trusted proxy. The settings page warns while a private `REMOTE_ADDR` arrives with `X-Forwarded-For` and no proxies are set.

```php
// Traefik or nginx in the same Docker Compose network: use that network's subnet.
// Find it with: docker network inspect <project>_default
define( 'JP_FORM_BUILDER_TRUSTED_PROXIES', '172.18.0.0/16' );

// Several proxies, IPv4 and IPv6, or all private Docker ranges.
define( 'JP_FORM_BUILDER_TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,fd00::/8' );
```

Add Cloudflare's published ranges as well when the site sits behind Cloudflare's proxy. Only list proxies you control: a trusted address can set `X-Forwarded-For` freely.

### Multilingual sites

Create **one Form per language** and place each on the pages of its language. A Form answers and mails in the locale of the page it is embedded in. WPML/Polylang integration is not part of this version.

## Mail

Use any SMTP service with a password: your mail provider, a transactional mail service, or a relay in your own network. **Microsoft 365 / Exchange Online mailboxes are not supported yet.** They need OAuth because Basic authentication is switched off there (planned as phase P2b, [ADR 0012](docs/adr/0012-m365-smtp-oauth.md)). Until then use an SMTP relay.

If a mail cannot be sent, the visitor sees a generic error and keeps what they typed, and the failure appears under **Recent mail failures** (last 10, addresses removed) and in the PHP error log. Nothing is stored, so a failed Submission is lost.

## Privacy (DSGVO)

What the plugin processes:

- Submitted values: in memory only, sent by mail, never stored.
- Rate limit: an HMAC-SHA256 hash of the client IP (keyed with a site salt, truncated) in a transient for the rate-limit window, 600 s by default.
- Confirmation throttle: an HMAC hash of the Submitter's address in a transient for one hour.
- Cloudflare Turnstile: the browser loads Cloudflare's script after the visitor interacts with a Form; Cloudflare processes IP and browser signals. The server sends the secret and the response token only, never the IP.
- Mail failure log: time, Form ID and error message with addresses removed.
- Backups: Forms and non-secret settings, no Submissions, no secrets.

WordPress's own privacy guide (**Settings → Privacy → Policy Guide**) lists a suggested text from this plugin. Add this snippet for Turnstile to your privacy policy; adapt it to your site and check the transfer basis at publication time. It is not legal advice.

**Deutsch (Sie-Form)**

> **Spam-Schutz mit Cloudflare Turnstile**
>
> Zum Schutz unserer Formulare vor Spam und Missbrauch nutzen wir Cloudflare Turnstile, einen Dienst der Cloudflare, Inc., 101 Townsend St., San Francisco, CA 94107, USA. Sobald Sie ein Formular anklicken oder darin tippen, lädt Ihr Browser ein Skript von Cloudflare (challenges.cloudflare.com). Cloudflare verarbeitet dabei Ihre IP-Adresse sowie technische Angaben zu Ihrem Browser und Gerät, um zu erkennen, ob die Eingabe von einem Menschen oder einem Bot stammt. Beim Absenden übermittelt unser Server nur den Prüf-Token und einen geheimen Schlüssel an Cloudflare, nicht Ihre IP-Adresse. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO; unser berechtigtes Interesse ist der Schutz unserer Formulare vor Spam und Missbrauch. Weitere Informationen finden Sie in der Datenschutzerklärung von Cloudflare.
>
> Ihre Formulareingaben werden per E-Mail an uns übermittelt und nicht auf dieser Website gespeichert. Zur Missbrauchsabwehr wird ein pseudonymisierter Wert, der aus Ihrer IP-Adresse abgeleitet ist, kurzzeitig (standardmäßig zehn Minuten) im System vorgehalten.

**English**

> **Spam protection with Cloudflare Turnstile**
>
> To protect our forms from spam and abuse we use Cloudflare Turnstile, a service of Cloudflare, Inc., 101 Townsend St., San Francisco, CA 94107, USA. As soon as you click into a form or start typing, your browser loads a script from Cloudflare (challenges.cloudflare.com). Cloudflare processes your IP address and technical information about your browser and device to tell whether the input comes from a human or a bot. When you submit the form, our server sends only the verification token and a secret key to Cloudflare, not your IP address. The legal basis is Art. 6(1)(f) GDPR; our legitimate interest is protecting our forms from spam and abuse. Further information is in Cloudflare's privacy policy.
>
> What you enter into our forms is sent to us by email and is not stored on this website. To prevent abuse, a pseudonymised value derived from your IP address is kept briefly (ten minutes by default).

### Outbound connections

The plugin makes only these requests:

| Destination | From | When | Purpose |
| --- | --- | --- | --- |
| Your SMTP server | server | mail is sent | Delivering Notifications, Confirmations and test mail. |
| `https://challenges.cloudflare.com/turnstile/v0/siteverify` | server | a Submission with Turnstile keys set reaches the Turnstile step | Verifying the Turnstile response (secret and token only). |
| `https://challenges.cloudflare.com/turnstile/v0/api.js` | visitor's browser | a visitor focuses a Form, only with Turnstile keys set | The Turnstile widget. |
| `github.com/jaypee-solutions/jp-form-builder` (GitHub API) | server | periodic update checks in the background and on the Plugins screen | Plugin Update Checker for plugin updates ([ADR 0007](docs/adr/0007-update-checker-exception.md)). Sends no visitor data. |

The plugin loads no fonts, analytics or other third-party assets. The mail layout allows no remote images or stylesheets.

## Migrating from WPForms and WP Mail SMTP

1. **Deactivate WP Mail SMTP first**, so two plugins do not configure PHPMailer at once, and keep its constants until step 2 is done.
2. **Map the SMTP constants.** WP Mail SMTP's names are listed in its documentation, [Securing SMTP Settings With Constants](https://wpmailsmtp.com/docs/how-to-secure-smtp-settings-by-using-constants/); the snippets there load dynamically, so the names below were checked against the plugin source, [`src/Options.php`](https://github.com/awesomemotive/WP-Mail-SMTP/blob/master/src/Options.php).

   | WP Mail SMTP | Form Builder |
   | --- | --- |
   | `WPMS_ON`, `WPMS_MAILER` (`smtp`) | not needed; setting `JP_FORM_BUILDER_SMTP_HOST` turns SMTP on |
   | `WPMS_SMTP_HOST` | `JP_FORM_BUILDER_SMTP_HOST` |
   | `WPMS_SMTP_PORT` | `JP_FORM_BUILDER_SMTP_PORT` |
   | `WPMS_SSL` (`''`, `tls`, `ssl`) | `JP_FORM_BUILDER_SMTP_ENCRYPTION` (`none`, `starttls`, `smtps`) |
   | `WPMS_SMTP_AUTH` | `JP_FORM_BUILDER_SMTP_AUTH` |
   | `WPMS_SMTP_USER` | `JP_FORM_BUILDER_SMTP_USER` |
   | `WPMS_SMTP_PASS` | `JP_FORM_BUILDER_SMTP_PASSWORD` |
   | `WPMS_MAIL_FROM` | `JP_FORM_BUILDER_FROM_EMAIL` |
   | `WPMS_MAIL_FROM_NAME` | `JP_FORM_BUILDER_FROM_NAME` |
   | `WPMS_MAIL_FROM_FORCE`, `WPMS_MAIL_FROM_NAME_FORCE` | `JP_FORM_BUILDER_FORCE_FROM` (one switch for both) |
   | `WPMS_SMTP_AUTOTLS`, `WPMS_SET_RETURN_PATH` | no equivalent; STARTTLS follows the encryption setting and the envelope sender is the From email |

   Constants for API mailers (SendGrid, Mailgun, Gmail, Outlook and others) have no equivalent. Use the provider's SMTP credentials.
3. **Rebuild each Form** under **Forms → Add Form**; there is no importer. Set the Recipients, subject and, if wanted, the Confirmation, then send a test Submission.
4. **Find the embeds** of the old forms and replace them with the **Form** block:

   ```bash
   wp post list --post_type=page,post --s='wp:wpforms/form-selector' --fields=ID,post_title
   wp post list --post_type=page,post --s='[wpforms' --fields=ID,post_title
   ```

   The second command finds shortcodes. Check widgets, templates and patterns in the Site Editor as well.
5. **Remove WPForms data.** WPForms keeps its forms, settings and entries when you delete the plugin, unless you first enable **WPForms → Settings → Misc → Uninstall WPForms** ([documentation](https://wpforms.com/docs/a-complete-guide-to-wpforms-settings/)). Export the entries you still need, enable the option, then deactivate and delete WPForms and WP Mail SMTP. Check what is left with `wp db query "SHOW TABLES LIKE '%wpforms%'"` and `wp option list --search='wpforms*' --fields=option_name`.

## Backup, restore and uninstall

Deleting the plugin from the Plugins screen runs `uninstall.php`, which removes **every Form** (any status, including trashed ones, with their revisions), the settings, the mail failure log, the stored GitHub token, the update checker state and all transients. Embed blocks in page content stay and render nothing. Download a Backup first.

- **Download:** Forms → Settings → *Backup & restore* → *Download backup*, or the *Back up forms* link in the Plugins list. WP-CLI: `wp jp-form-builder backup export [<file>]` (stdout when no file is given).
- **Contents:** a JSON file with every Form (ID, title, status, slug, content) and the settings that are neither secrets (`smtp_password`, `turnstile_secret_key`, `m365_client_secret`) nor locked by a constant. It contains no submissions. Treat it as configuration: it holds Recipients and Body templates. Re-enter secrets after restoring.
- **Restore:** Forms → Settings → *Backup & restore* (file up to `JP_FORM_BUILDER_MAX_BACKUP_BYTES`, default 2 MiB), or `wp jp-form-builder backup restore <file> [--overwrite] [--dry-run]`. Every Form must be exactly one Form block with allowed Field blocks, otherwise it is rejected and reported. A Form keeps its ID when that ID is free, so existing embeds keep working. If another post holds the ID, the Form gets a new ID and the report shows `old → new`; embeds are not rewritten. A Form with the same ID is skipped unless you overwrite. `--dry-run` validates and reports without writing.
- **Delete the Forms but keep the plugin:** `wp post delete $(wp post list --post_type=jp_form --post_status=any --format=ids) --force`.

## Translations

The plugin ships a German translation (`de_DE`, formal "Sie"; `de_DE_formal` is a byte-identical copy) for PHP and the block scripts. `.mo` files are compiled by `scripts/build-release.sh` and are not committed; the `.pot`, `.po` and script `.json` files are.

After changing a translatable string, with wp-env running:

```bash
npm run build        # the extractor reads the built blocks/ and admin/build/, not src/
npm run i18n:pot     # regenerate languages/jp-form-builder.pot
# update languages/jp-form-builder-de_DE.po, then copy it to jp-form-builder-de_DE_formal.po
npm run i18n:json    # regenerate the script translations (languages/*.json)
```

German terms follow [`GLOSSARY.md`](GLOSSARY.md): Formular, Feld, Feldschlüssel, Einsendung, Einsender, Empfänger, Benachrichtigung, Bestätigung, Mail-Layout, Textvorlage, Sicherung.

## Development

Scaffolded from [jaypee-solutions/wp-plugin-template](https://github.com/jaypee-solutions/wp-plugin-template).
Scaffolding changes (CI, scripts, hooks, lint/test config) arrive as pull requests
from the `template-update.yml` workflow, which runs `copier update` — edit them in
the template, not here.

Requirements: Composer, Node 22.22+ (wp-env 12), and Docker or Podman (for `wp-env`).

```bash
composer install
npm install

composer lint          # PHPCS (WordPress Coding Standards)
composer lint:fix      # PHPCBF
composer test          # PHPUnit

npm run build          # blocks: src/blocks → blocks/, settings page: src/admin → admin/build/ (both gitignored)
npm run test:js        # Node test runner: Field keys and the phone check, against the fixtures PHPUnit uses
npm run lint:js
npm run lint:css

npm run env:start      # main checkout uses 8888; linked worktrees use isolated ports
npm run test:integration
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
changed option even when a test fails. The development environment maps
`tests/e2e/mu-plugins/capture-mail.php`, which captures mail instead of sending it and
defines `JP_FORM_BUILDER_SMTP_PORT` so the E2E suite can assert a constant-locked field.

`composer lint` also validates `languages/*.po` with `msgfmt -c` and checks that every
`add_action`/`add_filter` name is fired by WordPress, starts with
`jp_form_builder_`, or matches a pattern in `scripts/hook-allowlist.txt`, where
third-party hooks go. The hook check reads the source from the wp-env cache and skips
until `npm run env:start` has downloaded it; CI fetches it.

`npm install` runs Husky, which points Git at `.husky/`; `composer hooks:check` verifies
that. `.husky/pre-commit` runs `composer lint`; `.husky/pre-push` runs `composer test`.

Design records are in [`docs/`](docs): the [PRD](docs/prd.md), the [implementation plan](docs/implementation-plan.md) and the [ADRs](docs/adr).

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
