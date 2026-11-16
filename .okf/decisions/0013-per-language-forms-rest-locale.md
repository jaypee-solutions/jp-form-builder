---
type: Decision
title: Per-language forms and REST locale
description: REST switches to the page locale; one Form per language.
tags:
  - i18n
  - rest
timestamp: 2026-11-13T00:00:00Z
---

# Overview

REST responses and mails use the locale of the page that embeds the Form, passed as `data-locale` and accepted only if installed. Multilingual sites build one Form per language; WPML/Polylang integration is deferred.

# Citations

[1] ADR file: `docs/adr/0013-per-language-forms-rest-locale.md`
