# Form Builder knowledge log

## 2026-11-13
* **Creation**: PRD, implementation plan and ADRs 0001–0008 for v1.
* **Review**: Answers 2026-11-13 recorded: country list kept, anti-relay confirmation kept, uninstall deletes forms with backup/restore (ADR 0009), Turnstile-optional fallback (ADR 0003), HTML mail templating in v1 (ADR 0010), block build upstreamed as optional `uses_blocks` template question (ADR 0006).
* **Reference**: Deployment and tooling facts gathered during PRD grilling (WP 6.6 JSX runtime floor, Exchange Online SMTP AUTH timeline, client mail/platform topology).
* **Grilling round 1**: WordPress 6.6 minimum (ADR 0006), capability filter and top-level Forms menu (ADR 0001), text field, lazy activation on first interaction (ADR 0002), Confirmation requires Turnstile (ADR 0011), rate limit 10/600 s with proxy CIDRs (ADR 0003), M365 OAuth (ADR 0012, proposed), page-locale REST (ADR 0013), single-site only, PRD open defaults accepted; `GLOSSARY.md` created.
* **Grilling round 2**: template branch fixes and PR for `v1.5.0`, stale cached markup validated against revisions (ADR 0014), Microsoft 365 via SMTP XOAUTH2 accepted (ADR 0012).

## 2026-11-16
* **Grilling round 3**: Mail layout, site-default and per-form Body templates edited in the admin with code editor and preview; theme-file override dropped (ADR 0010). Template `v1.5.0` released; P1 absorbs the CPT and its locked REST controller so the Forms menu exists from the start.
* **P1 done**: template v1.5.0 adopted with `uses_blocks`; tooling facts 9–12 added to the template facts reference.
* **P2 done**: Mail/SMTP + HTML templating; `wp_mail` facts 4–6 added to the WordPress core facts reference.
* **P3 done**: blocks, editor UI, Body template modal, Form settings document panel; core fact 7 and template fact 13 added.

## 2026-11-17
* **P4 done**: submission pipeline and view.js end to end (E2E 13 passed); template facts 14–15 added.
* **P5 done**: backup/restore/uninstall (E2E 16 passed); core fact 8 added (admin-post redirect target).
* **P6 done**: Privacy text, README, settings sections, German translation (formal Sie, 302 msgids, JS JSON); German terms added to `GLOSSARY.md`.
* **Final review**: two-axis review (Standards 14, Spec 9 findings; blocker: `uninstall.php` missing from the release ZIP) fixed in `737c172..5ea1997`; template facts 16–17 added.
* **Release 1.0.0**: tagged `v1.0.0` on `9a8f651`; the Release workflow published `jp-form-builder-v1.0.0.zip` (contains `uninstall.php`, `admin/build/`, compiled `de_DE.mo`, scoped vendor); template fact 18 added.
* **Styling decision**: after v1.0.1 feedback, ADR 0015: styling on the Form block (style variations, design controls, Field widths), site-wide via Global Styles.
* **Releases 1.0.1–1.2.0**: Form labels instead of Post labels (1.0.1, core fact 9); styling per ADR 0015 (1.1.0); cleaner Standard, Card and Liquid Glass styles, class-qualified Field selectors (1.2.0).
* **Conditional groups decision**: ADR 0016: one Condition per group on an earlier Field, no nesting, server-side evaluation; Radio buttons and Checkboxes Fields; glossary terms Conditional group and Condition.
* **Layout blocks decision**: ADR 0017: core Columns/Group (Row, Stack, Grid) allowed in Forms and Conditional groups, Conditional groups stay top-level; rendered by core with `pre_render_block` slots; glossary term Layout block.
* **Date field decision**: ADR 0018 (issue #9): typed input plus native `showPicker()` icon, Form-language display format with ISO wire value, Date offsets resolved server-side in the site timezone with `today` in the token response, years 1900-2100; glossary terms Date offset, Date window, Opening date.

## 2026-11-25
* **Postcode and Country decision**: issue #10 grilled into ADR 0019 (Postcode follows the nearest Country field, Germany fallback, 5 digits) and ADR 0020 (Address Field dropped in 2.0.0, no migration); glossary terms Postcode and Country.
