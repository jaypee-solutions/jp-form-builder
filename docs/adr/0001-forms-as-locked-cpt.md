---
status: accepted
---

# Forms are a locked custom post type

Forms are non-public `jp_form` posts whose block attributes hold field and notification config. Form ID = post ID and the server resolves only from published `jp_form` content. CPT caps map to `manage_options` by default, widened per site only through the `jp_form_builder_capability` filter, and a REST controller blocks anonymous reads, so authors can't turn a form into a mail relay or read recipients. Forms get their own top-level admin menu, which also holds the settings page. Uninstall deletes the posts; backup and restore are covered by [ADR 0009](0009-form-backup-restore-and-clean-uninstall.md).

## Considered options

- Inline Form block in any post: rejected. Lookup is ambiguous across synced patterns/templates, and low-privilege authors could edit recipients.
- Editors edit fields while recipients stay admin-only: rejected. It needs a server-side save filter on block attributes and still gives only a partial guarantee.
