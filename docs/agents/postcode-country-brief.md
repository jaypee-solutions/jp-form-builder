# Brief: Postcode and Country Fields, drop Address (issue #10)

Decisions: `docs/adr/0019-postcode-follows-nearest-country-field.md`, `docs/adr/0020-drop-address-field.md`. Terms: `GLOSSARY.md` (Postcode, Country). Read `docs/agents/testing.md` before running wp-env, integration or E2E. Template for a new single-control Field: `phone` (every place it is registered; grep `phone` in `includes/`, `src/`, `tests/`, `README.md`).

Work in the checkout at the absolute path you were given. Another agent may be idle in a different task, so do not touch `apm.lock.yaml` and never `git add -A`; commit only your own files after each green step (`git add <paths>` then `git commit`).

## Contract (decided, do not revisit)

**Country Field** (type `country`)
- `<select>` of `Countries::all()` (32, translated names). Attributes: `label`, `fieldName`, `required`, `helpText`, `width`, `defaultCountry` (default `DE`, may be empty = a "Please choose…" placeholder option; empty + required forces a pick; empty + optional = empty value). `defaultCountry` equal to the default is not stored (see `includes/class-block-defaults.php`).
- Value: the country code string; invalid code gives "Please choose a valid country." Mail shows the translated country name.
- Conditions: Select operators *is*, *is not*, *is one of*, options from `Countries::all()` (PHP `Condition_Evaluator`, JS `src/shared/condition.js`, `tests/fixtures/conditions.json`, editor Condition panel, README table).

**Postcode Field** (type `postcode`)
- Single `<input type="text">`, attributes `label`, `fieldName`, `required`, `helpText`, `width`, `placeholder`. `autocomplete="postal-code"`.
- Server rule: pattern = `Countries::postcode_pattern(<country>)`, message "Please enter a valid postcode." (existing msgid). Value is trimmed as for other Fields.
- Country resolution (PHP only, computed once per Form from document order): the nearest preceding Country Field, else the nearest following one, else DE. A Country Field hidden by a Conditional group, or whose value is empty, counts as absent (falls back to DE). If the Country value is invalid, skip the Postcode pattern check (the Country reports its own error). Validation needs the Form's cleaned sibling values: extend `Field_Validator` so the Postcode gets the resolved Country code; keep the per-field loop otherwise.
- Renderer: with a Country Field resolved, write `data-country-source="<country field key>"` and `data-postcode-patterns='{CC: pattern}'` (as Address did on its fieldset), `maxlength="10"`, no `inputmode`. Without one: `pattern`, `maxlength="5"`, `inputmode="numeric"`, DE rule. The client reads the source key and looks up the select's value at validation time (replace the `closest('fieldset')` logic in `src/blocks/embed/front/validation.js`); empty or unknown Country value means DE. Same message. Do not reimplement the nearest-Country resolution in JS.
- Mail preview sample value `86150`.

**Address removal**: delete the type everywhere with no shim: `Form_Blocks::FIELD_TYPES`, parser subkeys, validator (`validate_address`, `validate_parts`, `MAX_POSTCODE_LENGTH` use), renderer fieldset branch, `Field_Labels` part labels, `Submission_Handler` address mail value, mail-preview sample, `Blocks::enqueue_editor_data` (`address.countries` becomes the Country block's data), `src/blocks/address/`, `src/shared/labels.js` (`addressPartLabels`), `form-fields.js`, both `ALLOWED_BLOCKS` lists (`src/blocks/form/edit.js`, `src/blocks/conditional/edit.js`; add `postcode` and `country`), compound-value code (`partsOf` object branch in `src/shared/condition.js`, address-only-country rule in `src/blocks/embed/front/values.js` and `Condition_Evaluator`/`Submission_Handler`), and the msgids "Address", "Street and number", "Example Street 1", "Sampletown", "Default country" if unused. Tests and fixtures using address are rewritten onto Country/Postcode or deleted. `docs/implementation-plan.md` is historical: leave it. Update README (field list line 7, Conditions table, a short "Address in German order" recipe with Layout blocks: Street Text, Postcode, City Text, Country), `docs/prd.md`.

**i18n**: new msgids "Postcode", "Country", "Please choose…" (check for an existing equivalent), the placeholder sample for Postcode; German entries in both `languages/jp-form-builder-de_DE.po` and `-de_DE_formal.po` (formal Sie), regenerate `.pot` (`npm run i18n:pot`) and the JSON files.

**Release bookkeeping**: version 2.0.0 (plugin header in `jp-form-builder.php`, `readme.txt`, `package.json`/`composer.json` where the 1.4.0 appears), CHANGELOG `## [2.0.0]` with Added (Postcode, Country, Country Condition operators) and Removed (Address, breaking; saved Forms lose the address block) sections. Also add a line to `README.md`.

## Steps (one commit each, only after green)

1. Country Field, end to end (PHP, JS block, editor data, conditions, labels, tests, i18n).
2. Postcode Field with the nearest-Country binding (validator, renderer, front validation, tests incl. cross-field cases: following Country, two Countries, hidden Country, invalid Country, empty Country, no Country; JS test for the front-end lookup if feasible, shared fixture preferred).
3. Remove Address (everything above), docs, version, CHANGELOG.
4. Final verification (below), then E2E: add one Playwright test in `tests/e2e/submission.spec.ts` (Postcode + Country form: Austrian 4-digit code valid, 5 digits invalid for AT, DE-only fallback with no Country).

## Done criterion (required; overrides generic "skip builds and tests" guidance)

Report these lines verbatim, from your own runs after the last commit:
- `composer test` (PHPUnit unit): `OK (N tests, M assertions)`, exit 0
- `npm run test:js`: all pass, exit 0
- `npm run test:integration`: `OK (N tests, M assertions)`, exit 0
- `composer lint`: exit 0
- `npm run test:e2e:run`: Playwright summary `N passed`, no failures
- `git status --short` (only `apm.lock.yaml` may remain modified besides nothing else)

A report without these lines counts as unfinished. Commit after each green step; if you run out of turns, the commits carry the state.
