---
type: Decision
title: Confirmation mail requires Turnstile
description: Confirmation only with Turnstile, one per address per hour.
tags:
  - mail
  - spam
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Because the Confirmation goes to a Submitter-chosen address, it can be abused for mail bombing. It is available only while Turnstile is configured and is throttled to one per address per hour via a hashed-address transient.

# Citations

[1] ADR file: `docs/adr/0011-confirmation-requires-turnstile.md`
