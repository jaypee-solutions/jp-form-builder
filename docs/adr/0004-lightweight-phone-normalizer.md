---
status: accepted
---

# Lightweight phone normaliser

Own normaliser instead of `giggsey/libphonenumber-for-php(-lite)`. Measured 2026-11-13: lite 9.0.43 is 2.6 MB / 521 files, full is 21 MB / 1107 files, Apache-2.0. Both require PHP ^8.1 (template min 8.0) and `symfony/polyfill-mbstring`, whose global functions conflict with `scoper.inc.php`'s `exclude-functions`. Contact forms only need a callback number.

## Considered options

- libphonenumber-lite: rejected for the PHP floor, scoper conflict and size.
