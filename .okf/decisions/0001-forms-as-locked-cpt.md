---
type: Decision
title: Forms are a locked custom post type
description: Forms stored as non-public CPT posts, admin-only by default.
tags:
  - forms
  - security
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Forms are non-public `jp_form` posts; the server resolves a Form only from published `jp_form` content, by post ID. Editing requires `manage_options` unless a site widens it with the `jp_form_builder_capability` filter, and a REST controller blocks anonymous reads. Forms have a top-level admin menu that also holds the settings page.

# Citations

[1] ADR file: `docs/adr/0001-forms-as-locked-cpt.md`
