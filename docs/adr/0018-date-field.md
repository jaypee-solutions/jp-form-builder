---
status: accepted
---

# Date field: native picker, offsets resolved on the server

The Date Field is a text input with the expected format as placeholder, so visitors can type and get live validation, plus a calendar icon. The icon opens a hidden native `<input type="date">` through `showPicker()`; browsers without it get no icon and keep typing. The display format follows the Form's language (`TT.MM.JJJJ` for `de*`, `YYYY-MM-DD` otherwise); the wire value is always ISO `YYYY-MM-DD`, and mails show the display format.

A Date window (earliest and latest) and an Opening date are each a fixed ISO date or a Date offset: `now`/`today` or `[+-]N(d|w|m|y)`, one unit, no chaining, month and year steps clamp to the month end. Years are bounded to 1900-2100. "Today" is the site timezone's date. Pages are cached (ADR 0002), so offsets are never baked into markup: the server resolves them at validation, and view.js gets the same "today" from the token response it already fetches on first interaction. The server is authoritative and validates against the Field definition of the matching Form revision (ADR 0014). The Opening date only chooses where the calendar opens; it never fills the Field. An inverted window rejects every value. Date Fields are not a Condition source.

## Considered options

- A third-party calendar library (flatpickr and similar): rejected. Weight and styling effort for a feature the browser already ships, and no fit with the lightweight line of ADR 0004.
- Own popover calendar: rejected. A large accessible widget to build and maintain.
- Browser-local "today": rejected. It disagrees with the server at midnight and across timezones.
- Fetching "today" on page load: rejected. It costs an uncached request per page view (ADR 0002).
- Offsets resolved when the page is rendered: rejected. Cached pages would show stale bounds.
