---
type: Decision
title: Spam pipeline order
description: Ordered checks so floods never trigger outbound calls; proxy-aware rate limit.
tags:
  - spam
  - turnstile
  - privacy
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Checks run in the order size, form, rate limit, honeypot, token, Turnstile, validation, so floods never cause outbound calls; Turnstile fails closed. The rate limit counts every attempt (10 per 600 s) per HMAC-hashed client IP, taken from `X-Forwarded-For` behind trusted proxy CIDRs, with a settings notice when proxies look untrusted. Without Turnstile keys, forms run on the remaining checks.

# Citations

[1] ADR file: `docs/adr/0003-spam-pipeline.md`
