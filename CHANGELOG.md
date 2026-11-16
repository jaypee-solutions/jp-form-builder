# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Every `v*` tag needs a matching `## [X.Y.Z]` section here — the release workflow
extracts it as the GitHub release notes and fails without it.

## [Unreleased]

### Added
- Added a protected Forms editor with configurable capability and REST read access.
- Added a settings page with constant-locked fields and encrypted secret storage.
- Added SMTP configuration, HTML mail layout previews, test mail, redacted failure logging, and WP-CLI mail and settings commands.
- Added the `/mail-preview` REST endpoint for authorized template previews.
- Added the reusable Form block, Field blocks, and server-rendered accessible Form embeds.
- Added Form parsing, defaults, published-Form lookup with revision resolution, country data, and the authenticated Forms picker endpoint.
- Added the block editor UI: Field previews and inspector controls (Field key, required, help text, type-specific options, Select options editor), Form settings with recipient checks and Turnstile-gated Confirmation, and the Form embed picker with a server-side preview.

## [0.1.0] - 2026-11-16

### Added
- Initial scaffolding from [wp-plugin-template](https://github.com/jaypee-solutions/wp-plugin-template).
