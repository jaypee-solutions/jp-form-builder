---
status: accepted
---

# Stale cached forms validate against their revision

Full-page caches such as Varnish keep serving a Form's old markup after the Form is edited, so a visitor could face required Fields the page never showed. The Form embed renders a content hash as `data-rev`; the server validates a Submission against the Fields of the matching content (current or one of the last 10 `jp_form` revisions), while Recipients, subject, Body templates and Confirmation settings always come from the current Form. Without a match the server answers 409 `jpfb_form_changed`, and the README still advises purging caches after editing a Form.

## Considered options

- Only document "purge the cache after editing a Form": rejected. Visitors stay stuck until someone purges.
- Treat Fields missing from the payload as not shown: rejected. It silently weakens what "required" guarantees.
