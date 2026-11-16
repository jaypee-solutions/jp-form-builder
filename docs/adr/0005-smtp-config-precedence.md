---
status: accepted
---

# SMTP config precedence and secret storage

Constants beat the option beats the default, and constants lock the UI field. Stored secrets are sodium-encrypted with a key derived from `wp_salt('secure_auth')`; a salt rotation requires re-entry, and the settings page says so. Secrets are never printed and SMTP debug output is never enabled.
