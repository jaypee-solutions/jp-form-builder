---
type: Decision
title: Form backup, restore and clean uninstall
description: Uninstall removes all data incl. forms; JSON backup and restore offered instead of retention.
tags:
  - backup
  - uninstall
  - privacy
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Uninstall deletes all plugin data including every `jp_form` post. Because that is destructive, the settings page, the Plugins-list row and WP-CLI offer a JSON backup download (forms + non-secret settings, never secrets or submissions) and a restore function. Restore reuses the original post ID when free so embed blocks keep working, otherwise it creates a new form and reports the ID mapping; every form is validated to be one `jp-form-builder/form` block before insert.

# Citations

[1] ADR file: `docs/adr/0009-form-backup-restore-and-clean-uninstall.md`
