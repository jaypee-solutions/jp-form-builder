---
type: Decision
title: HTML mail with full templating
description: HTML multipart mail; layout, site defaults and per-form Body templates edited in the admin.
tags:
  - mail
  - templating
  - security
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Mails are `multipart/alternative` (HTML + derived plain text). The Mail layout, site-default and per-form Body templates are all edited in the admin area with the core code editor and a sandboxed preview; there is no theme-file override. Body templates use a small single-pass placeholder syntax restricted by kses, the `{all_fields}` table is generated, submitted values are always escaped and never re-parsed, and no remote assets are used.

# Citations

[1] ADR file: `docs/adr/0010-html-mail-templating.md`
