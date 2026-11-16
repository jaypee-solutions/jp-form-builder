---
status: accepted
---

# Cache-safe submission without nonce

Submissions go to a public REST route without a nonce, since WP treats nonce-less cookie requests as anonymous. view.js activates a form on its first interaction: only then does it fetch the HMAC fill-time token (uncached) and render Turnstile explicitly, so cached page views cost no PHP request and Cloudflare sees only visitors who use the form. Fill time counts from that first interaction, and view.js holds the submit until it has elapsed. Without JS, only a noscript notice is shown, because Turnstile has no non-JS mode.

## Considered options

- Token fetch and Turnstile on page load: rejected. It costs one uncached request per page view and lets Cloudflare process every visitor.
