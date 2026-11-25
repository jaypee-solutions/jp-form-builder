---
type: Decision
title: Postcode follows the nearest Country field
description: Postcode validates against the nearest Country Field in document order, else Germany (5 digits).
tags:
  - forms
  - validation
  - postcode
timestamp: 2026-11-25T00:00:00Z
---

# Overview

A Postcode Field validates against the postcode rule of a Country, and a Country is a Field of its own. The Postcode has no setting that names its Country. The server resolves the Country Field in document order: the nearest preceding one, otherwise the nearest following one (German address order puts the Country after the Postcode). Without a usable Country the rule is Germany: exactly 5 digits (`^\d{5}$`). A Country Field hidden by a Conditional group, or left empty, counts as absent. An invalid Country value gives that Field's own error and skips the Postcode pattern check. The renderer writes the resolved Country Field key and the per-country patterns into the Postcode markup (`data-country-source`, `data-postcode-patterns`), so the front end mirrors the server without repeating the resolution. The rules per Country come from `Countries::postcode_pattern()`: 5 digits for DE, 4 digits for AT, CH and LI, a loose alphanumeric rule for the others.

# Citations

[1] ADR file: `docs/adr/0018-postcode-follows-nearest-country-field.md`
