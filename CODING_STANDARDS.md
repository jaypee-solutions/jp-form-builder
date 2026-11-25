# Coding standards

Judgement calls for the reviewer and the implementer. Each rule has a one-line why. What a machine can decide is not here: PHPCS, `scripts/check-test-seams.sh`, `scripts/check-i18n.sh`, `scripts/check-hook-names.sh`, `scripts/check-ignored-sources.sh` and `scripts/check-code-rules.sh` run in `composer lint`, and `scripts/verify-release-zip.sh` runs against every release ZIP. When a review keeps finding the same mechanical mistake, add a check instead of a rule.

The template owns everything above "Project rules". The plugin owns that section; keep it last so `copier update` merges cleanly.

## Tests

- **Integration tests drive WordPress through its real paths**: hooks, `rest_get_server()->dispatch()`, `do_blocks()`, `admin-post.php` handlers, `wp_mail()`. Never simulate core with a short-circuit filter such as `pre_wp_mail`, and never build the plugin's own objects (`scripts/check-test-seams.sh` flags that). Why: a short-circuit skips the very hooks (`phpmailer_init`, `wp_mail_failed`) the plugin depends on, so the test stays green while the feature is broken.
- **Every admin screen has a render smoke test** that loads it as the right user and asserts the expected heading and that no raw `<?php` or template source is in the output. Why: an unrendered template or a fatal in a screen callback passes every unit test.
- **Fake only what leaves the machine**: HTTP through `pre_http_request`, time through a filter the plugin owns. Why: everything else is the behaviour under test.

## WordPress

- **A custom post type registers the full label set** plus `post_updated_messages` and `bulk_post_updated_messages`. Why: `register_post_type()` fills every missing label with the Post wording ("Add Post", "Post published."), which users see.
- **Build admin URLs with `admin_url()`**, never `menu_page_url()` (checked). Why: it returns an empty string in admin-post, REST and cron requests.
- **No translatable text as a `block.json` attribute default.** Resolve the default at render time with `__()`. Why: the default is stored and compared as a literal, so it never follows the site or user locale and cannot be extracted reliably.
- **Hooks are registered once, from the object WordPress will call.** Why: a second `new Foo()` is never hooked, so anything that talks to it silently bypasses the plugin.
- **Read the facts before coding against core**: `docs/agents/wordpress-facts.md`. Why: each entry is a behaviour that cost time once.

## Front end

- **Component CSS qualifies element selectors with the block wrapper class** (`.wp-block-x label`, never `label`). Why: theme element rules (`label`, `input`, `button`) otherwise win on specificity or order.
- **Use the theme's classes and tokens** (`wp-element-button`, `var(--wp--preset--*)`) instead of hard-coded colours and fonts. Why: the plugin has to look right in every block theme.

## Structure

- **One source of truth for every shared list** (allowed blocks, field types, release paths, option keys): define it once and derive the rest. Why: a second copy drifts, and the drift shows up as a feature that silently does not apply.
- **A new top-level path gets a decision**: `scripts/release-paths.txt` (ships) or `scripts/release-dev-only.txt` (does not). Why: `verify-release-zip.sh` fails on anything else, which is how a missing `uninstall.php` would have been caught.
- **Source directories never contain ignored files**; anchor `.gitignore` output rules (`/blocks/`) (checked). Why: an unanchored rule hides sources from commits without any warning.

## Project rules

Rules specific to this plugin. Add them here, each as a short rule plus one line of why; the template never edits this section.

- **UI text, docs and translations use the `GLOSSARY.md` terms** with their capitals (Form, Field, Submission, Conditional group, Condition) and its German equivalents. Why: one product language across admin, mails, README and both catalogues.
- **German strings address the user formally ("Sie")**, and `languages/jp-form-builder-de_DE_formal.po` is a byte copy of `jp-form-builder-de_DE.po`. Why: client sites run either locale and must read the same.
- **The server decides which Conditional groups are visible.** `src/shared/condition.js` mirrors `Condition_Evaluator` rule for rule, and both pass `tests/fixtures/conditions.json`. Why: a hidden Field must never be validated or mailed, whatever the browser posts.
- **Field control rules stay class-qualified** at (0,3,0) through `:where()`, with state rules one class higher. Why: theme and wp-admin rules such as `input[type="text"]` otherwise reshape only some controls (the styling E2E checks this).
