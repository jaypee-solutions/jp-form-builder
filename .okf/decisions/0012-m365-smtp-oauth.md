---
type: Decision
title: Microsoft 365 SMTP with OAuth
description: XOAUTH2 client-credentials for Microsoft 365 SMTP.
tags:
  - mail
  - smtp
  - oauth
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Microsoft 365 turns SMTP AUTH Basic off by default at the end of 2026, so v1 adds SMTP XOAUTH2 with client credentials via PHPMailer's `OAuthTokenProvider`, keeping PHPMailer as the only transport. The token endpoint `login.microsoftonline.com` is contacted only when `smtp_auth_method` is `m365_oauth`; Graph `sendMail` and Google OAuth were rejected.

# Citations

[1] ADR file: `docs/adr/0012-m365-smtp-oauth.md`
