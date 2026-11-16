---
status: accepted
---

# No entry storage in v1

v1 stores no submissions; a mail failure loses the submission. The failure log keeps redacted errors only. The confirmation mail echoes no submitted content, which prevents the form from being used to relay arbitrary text to arbitrary addresses (confirmed at review 2026-11-13); [ADR 0011](0011-confirmation-requires-turnstile.md) limits when it is sent at all. Backups contain forms and non-secret settings, never submissions.
