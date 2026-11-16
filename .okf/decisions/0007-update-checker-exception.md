---
type: Decision
title: Update checker as documented outbound exception
description: PUC polls the user's own GitHub repo; M365 token endpoint only when configured.
tags:
  - updates
  - privacy
timestamp: 2026-11-13T00:00:00Z
---

# Overview

The template's Plugin Update Checker keeps polling the user's own GitHub repo; it is the only unconditional outbound request beyond Turnstile and SMTP. The Microsoft token endpoint is a second, configuration-dependent exception (ADR 0012).

# Citations

[1] ADR file: `docs/adr/0007-update-checker-exception.md`
