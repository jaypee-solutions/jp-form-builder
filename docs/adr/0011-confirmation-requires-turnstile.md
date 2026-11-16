---
status: accepted
---

# Confirmation mail requires Turnstile

The Confirmation goes to an address the Submitter chooses, so even without echoed content bots could use it to mail-bomb third parties from the client's domain. A Form can enable it only while Turnstile is configured, and each address gets at most one Confirmation per hour (HMAC-hashed address in a transient). If the Turnstile keys are removed, Confirmations stop and the settings page lists the affected Forms.

## Considered options

- Confirmation guarded by honeypot, fill-time token and IP limit only: rejected. Distributed bots bypass per-IP limits, and the damage lands on third parties and the client's sender reputation.
