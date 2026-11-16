---
status: accepted
---

# HTML mail with full templating

Mails are `multipart/alternative` (HTML + derived plain text), and everything that shapes them is edited in the admin area: the Mail layout and site-default subjects/bodies on the settings page, per-form Body templates in a Form editor modal, each with the core code editor, a placeholder inserter and a sandboxed preview. Body templates use a small single-pass placeholder syntax (`{key}`, `{key:label}`, `{#key}…{/key}`, `{all_fields}`) restricted by kses; the layout has its own kses profile and must contain `{content}`; the `{all_fields}` table is generated. Submitted values are always escaped and never re-parsed, the confirmation context contains no submitted values, and no remote assets are used.

## Considered options

- Plain text only (original plan): rejected at review, HTML with full templating is a v1 requirement.
- Layout and field table as theme-overridable PHP templates: rejected at review round 3; templates must be editable online, and one override path is enough (developers keep the `jp_form_builder_mail_layout` filter).
- Visual (WYSIWYG) Body template editor: deferred; it needs TinyMCE or a custom RichText build.
- Third-party template engine (Mustache/Twig): rejected; bundled packages complicate scoping and the syntax needed is tiny.
