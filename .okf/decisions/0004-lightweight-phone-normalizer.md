---
type: Decision
title: Lightweight phone normaliser
description: Own E.164 normaliser instead of libphonenumber.
tags:
  - phone
  - dependencies
timestamp: 2026-11-13T00:00:00Z
---

# Overview

Own normaliser instead of `giggsey/libphonenumber-for-php(-lite)`. Measured 2026-11-13: lite 9.0.43 is 2.6 MB / 521 files, full is 21 MB / 1107 files, Apache-2.0. Both require PHP ^8.1 (template min 8.0) and `symfony/polyfill-mbstring`, whose global functions conflict with `scoper.inc.php`'s `exclude-functions`. Contact forms only need a callback number.

# Citations

[1] ADR file: `docs/adr/0004-lightweight-phone-normalizer.md`
