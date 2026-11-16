---
type: Decision
title: Block build with @wordpress/scripts
description: wp-scripts builds src/blocks into gitignored blocks/; WordPress 6.6 minimum.
tags:
  - blocks
  - build
  - template
timestamp: 2026-11-13T00:00:00Z
---

# Overview

`@wordpress/scripts` builds `src/blocks` into gitignored `blocks/`, delivered through the template's optional `uses_blocks` Copier question instead of local edits to template-owned files. Its JSX output needs `react-jsx-runtime`, so the block variant and this plugin require WordPress 6.6.

# Citations

[1] ADR file: `docs/adr/0006-wp-scripts-block-build.md`
