---
type: Reference
title: WordPress 7.1.4 core behaviours
description: Verified core behaviours behind the REST and block designs.
tags:
  - wordpress
  - rest
  - blocks
timestamp: 2026-11-13T00:00:00Z
---

# Overview

1. `rest_cookie_check_errors()` with no nonce calls `wp_set_current_user( 0 )` and treats the request as unauthenticated. A public REST submit needs no nonce and never goes stale behind a page cache.
2. `WP_REST_Posts_Controller::check_read_permission()` makes any `publish` post of a `show_in_rest` type readable anonymously. A non-public CPT still needs a custom REST controller to hide content.
3. `register_block_script_handle()` calls `wp_set_script_translations( $handle, $textdomain )` without a path, so bundled `languages/*.json` need an explicit call with `PLUGIN_DIR . 'languages'`. `parse_blocks()` omits attributes left at defaults, so defaults must be merged from `block.json`.
4. `wp_mail()` resets `Body` and `AltBody` before `phpmailer_init`, so a plugin only sets `AltBody` for its own mail. The default sender is `wordpress@` + the `network_home_url()` host with a leading `www.` removed; `wp_mail_from` runs before `wp_mail_from_name`.
5. A `pre_wp_mail` short-circuit skips `phpmailer_init` and fires no `wp_mail_failed`; failures it causes must be logged by the caller. With an `AltBody`, PHPMailer sends `multipart/alternative`, so tests assert the HTML part in the sent MIME body, not `ContentType === 'text/html'`.
6. Hooks bind to object instances: a second `new Mailer()` that is not hooked never sees `phpmailer_init` or `wp_mail_failed`, so plugin mail goes through the one instance `Plugin::init()` registers.

# Citations

[1] ADR file: `docs/adr/0001-forms-as-locked-cpt.md`
[2] ADR file: `docs/adr/0002-cache-safe-submission.md`
[3] Core file: `wp-includes/pluggable.php` (`wp_mail`, WordPress 7.1)

