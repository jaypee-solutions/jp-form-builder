---
status: accepted
---

# Postcode follows the nearest Country field

A Postcode Field validates against the postcode rule of a Country, and a Country is a Field of its own. The Postcode has no setting that names its Country. The server resolves the Country Field in document order: the nearest preceding one, otherwise the nearest following one (German address order puts the Country after the Postcode). Without a usable Country the rule is Germany: exactly 5 digits (`^\d{5}$`). A Country Field hidden by a Conditional group, or left empty, counts as absent. An invalid Country value gives that Field's own error and skips the Postcode pattern check. The renderer writes the resolved Country Field key and the per-country patterns into the Postcode markup (`data-country-source`, `data-postcode-patterns`), so the front end mirrors the server without repeating the resolution. The rules per Country come from `Countries::postcode_pattern()`: 5 digits for DE, 4 digits for AT, CH and LI, a loose alphanumeric rule for the others.

## Considered options

- An explicit "Country field" setting on the Postcode: rejected for now. It needs an inspector control and handling of dangling keys, and the nearest rule covers a single address and side-by-side billing and shipping blocks. Add it if the nearest rule proves too implicit.
- Preceding Country only: rejected. It breaks the usual order of Postcode, City, Country.
- Keeping the composite Address Field: rejected, see ADR 0020.
- Germany only, no Country binding: rejected. Address already validated per Country, and dropping that would be a regression.
