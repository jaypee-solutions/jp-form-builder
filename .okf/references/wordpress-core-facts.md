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

# Citations

[1] ADR file: `docs/adr/0001-forms-as-locked-cpt.md`
[2] ADR file: `docs/adr/0002-cache-safe-submission.md`

