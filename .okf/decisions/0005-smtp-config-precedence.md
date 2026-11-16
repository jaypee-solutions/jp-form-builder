---
type: Decision
title: SMTP config precedence and secret storage
description: Constants beat option beats default; encrypted stored secrets.
tags:
  - smtp
  - secrets
  - settings
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Constants beat the option beats the default, and constants lock the UI field. Stored secrets are sodium-encrypted with a key derived from `wp_salt('secure_auth')`; a salt rotation requires re-entry, and the settings page says so. Secrets are never printed and SMTP debug output is never enabled.

# Citations

[1] ADR file: `docs/adr/0005-smtp-config-precedence.md`
