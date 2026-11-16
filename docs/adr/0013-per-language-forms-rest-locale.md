---
status: accepted
---

# Per-language forms and REST locale

Anonymous REST requests run in the site locale, so on a multilingual site messages and translated defaults would come out in the site's main language. A Form embed renders `data-locale` from the page's locale, and the REST routes switch to that locale when it is installed; multilingual sites use one Form per language, embedded manually. WPML/Polylang integration (translatable `jp_form`, automatic Form lookup) is deferred.

## Considered options

- WPML/Polylang integration in v1: deferred. It needs `wpml-config.xml`, block-attribute translation of the embed and per-plugin testing, while manual per-language Forms already work.
