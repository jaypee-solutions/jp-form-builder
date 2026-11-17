---
type: Decision
title: Conditional groups
description: A group block shown only while a condition on an earlier Field holds; evaluated on the server.
tags:
  - forms
  - blocks
  - validation
timestamp: 2026-11-17T00:00:00Z
---

# Overview

A Conditional group block holds Fields and is shown only while one condition on an earlier Field holds; groups do not nest, and Radio buttons and Checkboxes Fields supply the choices. The server evaluates conditions in document order against submitted values, skips validation of hidden Fields and drops their values from mails; hidden groups use the `hidden` attribute.

# Citations

[1] ADR file: `docs/adr/0016-conditional-groups.md`
