---
type: Decision
title: Layout blocks in Forms
description: Core Columns and Group blocks hold Fields in a Form; rendered by core with Fields slotted in.
tags:
  - forms
  - blocks
  - layout
timestamp: 2026-11-24T00:00:00Z
---

# Overview

Core Columns, Column and Group blocks (including Row, Stack and Grid) may sit in a Form and in a Conditional group, nested in each other, and hold Fields and pass-through blocks; Conditional groups stay top-level. Core renders the layout block and `pre_render_block` slots the plugin's Field markup into it. Flow containers reuse the Field grid. Validation, conditions, mails and Field keys see Fields at any depth in document order.

# Citations

[1] ADR file: `docs/adr/0017-layout-blocks-in-forms.md`
