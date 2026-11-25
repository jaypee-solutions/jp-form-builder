---
status: accepted
---

# Drop the Address Field

With Layout blocks (ADR 0017) editors arrange Street, Postcode, City and Country themselves, and the composite Address Field fixed that order and layout. Address is removed in 2.0.0. Street and City are Text Fields; Postcode and Country are Fields of their own (ADR 0018). There is no migration, deprecation or shim: no production site used a Form yet, so saved test Forms lose their address block (the parser ignores unknown block types). The multi-part Field values only Address produced, and the rule that a preselected Country does not count as filled, are removed with it. Country is a Select-like Field for Conditions (*is*, *is not*, *is one of*).

## Considered options

- Keep Address next to the new Fields: rejected. Two ways to ask for an address, and two postcode validations to maintain.
- Deprecate first, remove in a later major version: rejected. It keeps compound-value code alive for no existing user.
- Migrate address blocks into four Fields: rejected. There is nothing to migrate in production.
