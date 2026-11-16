---
status: accepted
---

# Form backup, restore and clean uninstall

Uninstall deletes all plugin data including every `jp_form` post. Because that is destructive, the settings page, the Plugins-list row and WP-CLI offer a JSON backup download (forms + non-secret settings, never secrets or submissions) and a restore function. Restore reuses the original post ID when free so embed blocks keep working, otherwise it creates a new form and reports the ID mapping; every form is validated to be one `jp-form-builder/form` block before insert.

## Considered options

- Keep `jp_form` posts on uninstall: rejected at review; leaves orphaned data after removal.
- Prompt during uninstall: not possible, WordPress uninstall runs non-interactively.
