---
status: accepted
---

# Layout blocks in Forms

Editors need to arrange Fields side by side or in sections. The core Columns (with Column) and Group blocks, including their Row, Stack and Grid variations, are allowed in a Form and inside a Conditional group, nested in each other, and hold Fields and pass-through blocks. Conditional groups stay direct children of the Form: never inside a layout block, never nested. The front end renders each layout block through WordPress core, so layout classes, gap, colours and spacing are kept, and the plugin's Field markup is slotted in through `pre_render_block`. Flow containers (Column, flow and constrained Group) reuse the Field grid so Field widths of 50 % and 33 % keep working. Validation, conditions, mails and Field keys see Fields at any depth in document order.

## Considered options

- Own layout blocks: rejected. They duplicate the core design tools editors already know.
- Conditional groups inside layout blocks: rejected. The editor cannot stop group-in-group nesting through a Column.
- Rendering layout markup ourselves: rejected. It loses layout support classes and generated gap styles.
