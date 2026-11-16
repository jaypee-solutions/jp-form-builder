---
type: Decision
title: No entry storage in v1
description: Submissions are mailed, not stored.
tags:
  - privacy
  - mail
timestamp: 2026-11-13T00:00:00Z
---

# Overview

v1 stores no Submissions, so a mail failure loses the Submission and the failure log keeps redacted errors only. The Confirmation echoes no submitted content, and Backups hold Forms and non-secret settings, never Submissions.

# Citations

[1] ADR file: `docs/adr/0008-no-entry-storage.md`
