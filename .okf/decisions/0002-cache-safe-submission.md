---
type: Decision
title: Cache-safe submission without nonce
description: Public nonce-less REST submit; token and Turnstile load on first interaction.
tags:
  - rest
  - caching
  - privacy
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Submissions use a public REST route without a nonce, so cached pages never carry a stale nonce. view.js fetches the signed fill-time token and renders Turnstile only on the form's first interaction, which keeps cached page views free of PHP requests and Cloudflare contact. Without JS a noscript notice is shown.

# Citations

[1] ADR file: `docs/adr/0002-cache-safe-submission.md`
