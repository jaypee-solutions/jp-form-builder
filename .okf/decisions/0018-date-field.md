---
type: Decision
title: Date field
description: Text input plus native picker behind an icon; Date offsets resolved on the server in the site timezone.
tags:
  - forms
  - fields
  - date
timestamp: 2026-11-25T00:00:00Z
---

# Overview

The Date Field takes typed input in the Form language's format (`TT.MM.JJJJ` for `de*`, else `YYYY-MM-DD`) and offers a calendar icon that opens a hidden native date input via `showPicker()`; without `showPicker` only typing remains. The wire value is ISO. Earliest, latest and Opening date are fixed dates or Date offsets (`now`, `[+-]N(d|w|m|y)`, no chaining, month-end clamping), years 1900-2100. "Today" is the site timezone's date: the server resolves offsets at validation and view.js receives `today` in the token response, so cached pages never hold stale bounds. The server is authoritative; an inverted window rejects every value; Date Fields are not a Condition source.

# Citations

[1] ADR file: `docs/adr/0018-date-field.md`
[2] Issue: jaypee-solutions/jp-form-builder#9
