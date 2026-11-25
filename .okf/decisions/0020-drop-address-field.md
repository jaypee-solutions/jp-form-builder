---
type: Decision
title: Drop the Address Field
description: Composite Address removed in 2.0.0; Street, Postcode, City and Country are separate Fields.
tags:
  - forms
  - fields
  - breaking-change
timestamp: 2026-11-25T00:00:00Z
---

# Overview

With Layout blocks (ADR 0017) editors arrange Street, Postcode, City and Country themselves, and the composite Address Field fixed that order and layout. Address is removed in 2.0.0. Street and City are Text Fields; Postcode and Country are Fields of their own (see ADR 0019 for the Postcode rule). There is no migration, deprecation or shim: no production site used a Form yet, so saved test Forms lose their address block (the parser ignores unknown block types). The multi-part Field values only Address produced, and the rule that a preselected Country does not count as filled, are removed with it. Country is a Select-like Field for Conditions (*is*, *is not*, *is one of*).

# Citations

[1] ADR file: `docs/adr/0020-drop-address-field.md`
