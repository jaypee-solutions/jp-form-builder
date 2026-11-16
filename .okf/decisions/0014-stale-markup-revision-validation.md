---
type: Decision
title: Stale cached forms validate against their revision
description: Cached markup carries a content hash; the server validates against the matching revision.
tags:
  - caching
  - forms
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Because full-page caches serve old Form markup after an edit, the embed renders a content hash (`data-rev`) and the server validates against the Fields of the matching current or recent revision. Mail settings always come from the current Form; an unknown hash returns 409 asking the visitor to reload.

# Citations

[1] ADR file: `docs/adr/0014-stale-markup-revision-validation.md`
