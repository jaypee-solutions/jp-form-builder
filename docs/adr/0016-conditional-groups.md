---
status: accepted
---

# Conditional groups

Forms need Fields that appear only after an earlier choice. A Conditional group block inside the Form holds Fields, paragraphs and headings and is shown only while one condition on an earlier Field holds (select or radio is / is not / is one of, checkboxes include, consent ticked or not, any Field filled or empty); groups do not nest, and new Radio buttons and Checkboxes Fields provide the choices. The server evaluates every condition against the submitted values in document order and never trusts the browser: Fields of a hidden group are neither required nor validated, and their values are dropped from mails. Hidden groups carry the `hidden` attribute and appear right after their trigger without moving focus.

## Considered options

- Conditions on every single Field: rejected. A group states the rule once for several Fields and is visible as a block in the editor.
- Several conditions combined with AND/OR: deferred; one condition per group covers contact forms and keeps the rule readable.
- Client-side hiding only: rejected. Bots could submit hidden Fields, and a required hidden Field would block legitimate Submissions.
