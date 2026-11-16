---
type: Reference
title: Deployment and tooling facts
description: Facts from the client deployment repos and upstream tooling that constrain plugin defaults.
tags:
  - deployment
  - mail
  - blocks
timestamp: 2026-11-13T00:00:00Z
---

# Overview

1. `@wordpress/scripts` 28+ compiles JSX to the `react-jsx-runtime` script, which WordPress registers only from 6.6. The template's `uses_blocks` variant ships `@wordpress/scripts` ^37.1 but still declares `Requires at least: 6.4`, so blocks fail to register on 6.4/6.5.
2. Exchange Online disables SMTP AUTH Basic authentication by default for existing tenants at the end of December 2026; tenants created later have it unavailable; the final removal date follows in the second half of 2027.
3. `schlemmer-maul.com` mail is hosted on Microsoft 365 (Microsoft DKIM CNAMEs in its deploy repo). No SMTP host settings were found in any deploy repo.
4. The planned multi-customer platform (`deploy-hetzner-infra`) is k0s on Hetzner with a Hetzner load balancer, Traefik ingress, Varnish and `wordpress:php8.3-apache`; DNS is on Cloudflare. `REMOTE_ADDR` there is an in-cluster address, not the visitor.
5. No multisite, no content staging/promotion workflow and no per-site plugin inventory were found in the deploy repos. The platform's `test` stack is infrastructure-only.
6. The sibling plugin jp-catalog-suite ships `wpml-config.xml`, so WPML is in use on at least one client site.

# Citations

[1] Web: https://make.wordpress.org/core/2024/06/06/jsx-in-wordpress-6-6
[2] Web: https://github.com/WordPress/gutenberg/issues/62202
[3] Web: https://techcommunity.microsoft.com/blog/exchange/updated-exchange-online-smtp-auth-basic-authentication-deprecation-timeline/4489835
[4] Repo file: `~/GitHub/wp-plugin-template-blocks/template/package.json.jinja`, `template/{{plugin_slug}}.php.jinja`
[5] Repo file: `~/GitHub/deploy-schlemmer-maul-com/__main__.py`
[6] Repo file: `~/GitHub/deploy-hetzner-infra/docs/adr/ADR-022-cluster-topology.md`, `docs/adr/ADR-009-dns-provider.md`
[7] Repo file: `~/GitHub/jp-catalog-suite/wpml-config.xml`
