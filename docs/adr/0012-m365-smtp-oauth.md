---
status: accepted
---

# Microsoft 365 SMTP with OAuth

Exchange Online disables SMTP AUTH Basic authentication by default at the end of December 2026 and removes it later, and at least one client mails through Microsoft 365. v1 therefore supports SMTP XOAUTH2 for Microsoft 365 with the OAuth client-credentials flow, through PHPMailer's `OAuthTokenProvider`; the plugin fetches and caches the access token itself. This adds `login.microsoftonline.com` as an outbound host, used only when `smtp_auth_method` is `m365_oauth`. Password SMTP stays the default for every other provider, and Google OAuth is out of scope because app passwords still work.

## Considered options

- Password SMTP only, with M365 clients on a relay: rejected. Each relay adds a processor per client, and re-enabling Basic auth ends with Microsoft's removal date.
- Microsoft Graph `sendMail`: rejected. It needs its own transport beside PHPMailer and `graph.microsoft.com` as another outbound host.
- `league/oauth2-client`: rejected. It bundles a dependency (and scoping risk) for a single token request.
