---
status: accepted
---

# Form styling with block design tools

The v1 Form only inherited the theme, which looked plain on patterned or coloured backgrounds, and anything else needed custom CSS. Styling lives on the Form block that embeds a Form (per placement), with site-wide defaults in Site Editor → Styles → Blocks → Form, using WordPress's own design tools: four block style variations (Standard, Soft, Underlined, Rounded), the core colour, typography, spacing and border supports, Field-specific colours (background, border, focus/accent) and Field border radius/width as block attributes, and a width per Field (100 %, 50 %, 33 %) that stacks on narrow containers. Everything reaches the markup as `--jpfb-*` CSS custom properties whose defaults derive from the block's text and background colours, and theme developers can set them site-wide through `theme.json` `settings.custom`. The Form itself stays content only.

## Considered options

- A styling section on the plugin's settings page: rejected. It duplicates the Site Editor, cannot vary per page and is invisible to theme.json.
- Styling stored on the Form (`jp_form` post): rejected. The same Form often sits on differently coloured pages.
