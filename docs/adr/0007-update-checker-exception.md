---
status: accepted
---

# Update checker as documented outbound exception

The template's Plugin Update Checker stays on and polls the user's own GitHub repo. Beyond Turnstile and SMTP it is the only unconditional outbound request; the other exception is the Microsoft token endpoint, contacted only when Microsoft 365 OAuth is configured ([ADR 0012](0012-m365-smtp-oauth.md)). Both are documented in the README.
