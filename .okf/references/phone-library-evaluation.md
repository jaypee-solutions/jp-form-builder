---
type: Reference
title: libphonenumber evaluation
description: Measured size, license and compatibility of giggsey/libphonenumber-for-php.
tags:
  - phone
  - dependencies
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Measured 2026-11-13.

- `giggsey/libphonenumber-for-php-lite` 9.0.43: 2.6 MB, 521 files.
- Full `libphonenumber-for-php`: 21 MB, 1107 files.
- License Apache-2.0 (GPL-compatible).
- Both require PHP ^8.1; the template minimum is 8.0.
- Both require `symfony/polyfill-mbstring`, which declares global functions that conflict with `scoper.inc.php`'s `exclude-functions /.*/`.

Outcome: own lightweight normaliser (DE/AT/CH national forms, E.164 output).

# Citations

[1] ADR file: `docs/adr/0004-lightweight-phone-normalizer.md`

