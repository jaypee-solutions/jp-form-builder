# Date field: shared contract (issue #9)

Decisions are final (grilled with the owner). Do not reopen them. Terms follow `GLOSSARY.md`: **Date offset**, **Date window**, **Opening date**.

## Rules for every agent

- Work in the main checkout `/Users/alten/orca/workspaces/jp-forms/datepicker`, only in the files your brief owns. Use absolute paths. Other agents edit other files concurrently.
- Reuse existing patterns (see the `phone` Field end to end: `src/blocks/phone`, `Field_Validator`, `Form_Renderer`, `front/validation.js`).
- Clean code, meaningful names, inline comments only for non-obvious behaviour. No docs, ADR, glossary, changelog edits: the owner does them.
- Commit nothing. Do not run `git add`/`git commit`.
- Done = the verification commands of your brief are green. Running them is REQUIRED and overrides generic "skip builds and tests" guidance. Your final report MUST contain the exact green summary line of each command and its exit code 0. A report without them counts as unfinished. Bound large outputs (`| tail -20`).
- If you find a bug in a file you do not own, message the owner (`write agent://<id>`) or report it; do not patch it.

## Decisions

1. Typed input is a text field; placeholder = expected format. Calendar icon is a button that opens a hidden native `<input type="date">` via `showPicker()`. No third-party library. If `showPicker` is missing on date inputs, the icon button stays hidden; typing still works.
2. Display format follows the Form's language: locale starting with `de` -> `TT.MM.JJJJ` (parse `d.m.yyyy`, day and month may omit the leading zero, output zero-padded `dd.mm.yyyy`); every other locale -> `YYYY-MM-DD`. No per-field format option, no custom placeholder option. Submitted wire value is always ISO `YYYY-MM-DD`. Mails (Notification, Body template placeholders) show the display format of the locale active at submission.
3. Date window and Opening date attributes: `earliest`, `latest`, `openingDate` (strings, default `''`). Each is empty (none), a fixed ISO date `YYYY-MM-DD`, or a Date offset.
4. Date offset grammar (single unit, no chaining, case-insensitive, surrounding whitespace ignored):
   - `now` or `today` = today
   - `[+-]N(d|w|m|y)` with N = 1..999 digits `\d{1,3}`; a missing sign means `+`; `+0d` allowed. Examples: `-1y`, `+30d`, `+2w`, `6m`.
   - `d` days, `w` = 7 days, `m` and `y` calendar months/years; if the target day does not exist the result clamps to the month end (31 Jan +1m = 28 Feb; 29 Feb +1y = 28 Feb).
   - Anything else is an invalid expression. Fixed dates must be real calendar dates.
5. Year bounds: a valid date has a 4-digit year 1900..2100. A resolved bound outside 1900-01-01..2100-12-31 is clamped to that range. A typed/submitted date outside is invalid ("valid date" error).
6. "Today" is the date in the site timezone (`wp_timezone()`). The server resolves offsets at validation time. The REST token response (`GET jp-form-builder/v1/forms/{id}/token`) gains `today` (`YYYY-MM-DD`, site timezone) next to `token`; view.js uses it for offsets and never uses the browser clock. The server is authoritative.
7. Opening date: only the month/day the calendar opens on while the Field is empty; never prefills or submits. Resolved, then clamped into the Date window. An invalid Opening date expression is ignored. An invalid `earliest`/`latest` expression is ignored (no bound) at render and validation; the editor flags it.
8. Inverted Date window (resolved earliest > latest): the editor warns (resolved with the browser's today, editor only); the server rejects every value of that Field with the out-of-window message for the violated bound.
9. Validation and messages (identical msgids in PHP and JS; `%s` = the limit or format in the display format; text domain `jp-form-builder`):
   - invalid / unparseable / not a real date / year out of 1900-2100: `Please enter a valid date (%s).` with the format placeholder text (`TT.MM.JJJJ` / `YYYY-MM-DD`)
   - before earliest: `Date must not be before %s.`
   - after latest: `Date must not be after %s.`
   - empty + required: the existing required message.
   - Server accepts only ISO `YYYY-MM-DD` from the wire.
   - Live client check: error appears once the typed text reaches full length (de: 10 chars, or the day/month-without-zero forms once a 4-digit year is typed; iso: 10 chars) and is invalid or out of window; incomplete text is flagged on blur only. On blur a valid input is reformatted to the canonical display format.
10. Date Fields are NOT a Condition source (no operators, not offered in the editor; the PHP parser treats a Condition whose source is a date Field as invalid, i.e. the group stays shown).
11. Stale cached markup (ADR 0014) needs no new mechanism: the server resolves offsets from the matching revision's Field definition at validation time.
12. Non-goals: time of day, date ranges, weekday/holiday exclusions, prefilled value, chained offsets, Conditions on dates.

## Contracts between slices

### Block (already written): `src/blocks/date/block.json`
Name `jp-form-builder/date`; attributes `label`, `fieldName`, `required`, `helpText`, `width`, `earliest`, `latest`, `openingDate`. Field type string: `date`. Built output goes to gitignored `blocks/` via `npm run build:blocks`; PHP reads defaults from `blocks/*/block.json`.

### Markup rendered by `Form_Renderer` for a date Field
Standard Field wrapper, `<label for=ID>`, then:

```html
<div class="jp-form-builder__date">
  <input type="text" id="ID" name="KEY" inputmode="numeric" autocomplete="off" maxlength="10"
         placeholder="TT.MM.JJJJ" data-date-format="dmy|iso"
         data-earliest="RAW" data-latest="RAW" data-opening-date="RAW"
         [required] aria-describedby="…">
  <button type="button" class="jp-form-builder__date-picker" hidden aria-label="Choose date from calendar">INLINE-SVG calendar icon, aria-hidden</button>
</div>
```

`data-earliest` / `data-latest` / `data-opening-date` carry the RAW attribute strings (fixed ISO or offset); attributes are omitted when empty. `data-date-format` is `dmy` (de) or `iso`. The icon button is revealed (`hidden` removed) by view.js only when `showPicker` is supported. The native `<input type="date">` is created and managed by view.js (visually hidden, `tabindex="-1"`, `aria-hidden="true"`, no `name`), never submitted.

### Token response
`{ "token": "...", "today": "2026-11-25" }`.

### JS shared pure modules (owned by the front-end agent, imported by the editor agent)
`src/shared/date-offset.js` (no DOM, no WP imports, node-testable):
- `parseDateExpression( text )` -> `{ kind: 'empty' } | { kind: 'fixed', iso } | { kind: 'offset', amount, unit } | { kind: 'invalid' }`. `now`/`today` -> `{ kind: 'offset', amount: 0, unit: 'd' }`; `amount` is signed integer; `unit` in `d|w|m|y`.
- `resolveDateExpression( text, todayIso )` -> ISO string, clamped to 1900-01-01..2100-12-31, or `null` for empty/invalid.
- `isValidIsoDate( iso )` -> boolean (real calendar date, year 1900..2100).
- `clampIso( iso, earliestIso|null, latestIso|null )` -> ISO.

`src/shared/date-format.js` (no DOM):
- `dateFormatFor( locale )` -> `'dmy'` when locale starts with `de`, else `'iso'`.
- `formatDate( iso, format )`, `parseDate( text, format )` -> ISO or `null` (strict; `dmy` allows 1-digit day/month, requires 4-digit year; `iso` requires `YYYY-MM-DD`), `placeholderFor( format )` -> `'TT.MM.JJJJ'` | `'YYYY-MM-DD'`, `isFullLength( text, format )`.

The PHP twin: `includes/class-date-offset.php` (`Date_Offset`: parse/resolve with the same grammar, takes a `DateTimeImmutable` today) and `includes/class-date-format.php` (`Date_Format`: `for_locale( string $locale )`, `placeholder()`, `format( string $iso )`, `parse( string $text )`). Both sides share fixtures: `tests/fixtures/date-offsets.json` (owned by the PHP agent, created first; the JS agent reads it in `tests/js/date-offset.test.mjs`). Fixture shape: `[ { "today": "2026-11-25", "expression": "-1y", "resolved": "2025-11-25" }, … ]` with `resolved: null` for invalid/empty; include month-end clamping, leap day, `now`, `today`, signless, whitespace, uppercase, year clamping, invalid junk.

## Verification commands (done criteria)

| Agent | Commands (all must be green, exit 0) |
|---|---|
| PHP | `composer test` (`OK (N tests, …)`), `composer lint`, then after `npm run build:blocks` is green: `npm run test:integration` (`OK (N tests, M assertions)`) |
| Front JS | `npm run test:js` (all pass, `# fail 0`), `npm run lint:js`, `npm run build` |
| Editor JS | `npm run test:js`, `npm run lint:js`, `npm run build` |

Integration tests are queued behind a lock by the runner; wait for it. If a build fails only because another agent's files are mid-edit, wait a minute and retry; do not touch those files.
