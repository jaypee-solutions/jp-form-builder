---
status: accepted
---

# Spam pipeline order

The pipeline order is size, form, rate limit, honeypot, token, Turnstile, validation, so floods never trigger outbound calls. Turnstile fails closed and checks hostname + action, with no `remoteip`. IPs are HMAC-hashed. Behind proxies, `JP_FORM_BUILDER_TRUSTED_PROXIES` lists proxy IPs/CIDRs and the client IP is the rightmost `X-Forwarded-For` entry outside them; the settings page warns when a private `REMOTE_ADDR` arrives with `X-Forwarded-For` and no trusted proxies are set, because all visitors would then share one bucket. The rate limit counts every attempt (default 10 per 600 s), and view.js pre-validates with the server's rules so typos rarely cost an attempt. When Turnstile keys are not configured, forms still render and run on honeypot, fill-time token and rate limit, and the settings page shows an actionable notice (decided at review 2026-11-13).
