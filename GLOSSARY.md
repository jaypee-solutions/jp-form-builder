# Form Builder

A WordPress plugin for contact-style forms: site admins define forms in the block editor, visitors send them, and the site mails the result to its recipients.

## Language

### Forms

**Form**:
A reusable form definition the site admin maintains; its title, fields and mail settings belong to it (DE: Formular).
_Avoid_: form post, form definition, form block

**Form embed**:
The placement of one Form on a page (DE: Einbettung).
_Avoid_: form block, shortcode, form instance

**Field**:
One input of a Form, such as name, email, phone, postcode, country, text, select, radio buttons, checkboxes, date, message or consent (DE: Feld).
_Avoid_: input, control, element

**Postcode**:
A Field for a postal code; it is checked against the rules of the nearest Country Field, or Germany without one (DE: Postleitzahl, PLZ).
_Avoid_: postal code, zip, zip code

**Country**:
A Field for choosing a country; its choice sets the rules for the nearest Postcode (DE: Land).
_Avoid_: nation, country code

**Date offset**:
A date given relative to today, such as now, -1y or +2w, instead of a fixed date; it bounds or opens a date Field (DE: Datumsversatz).
_Avoid_: relative date, dynamic date, date expression

**Date window**:
The span of dates a date Field accepts, set by an earliest and a latest selectable date, each fixed or a Date offset (DE: Datumsfenster).
_Avoid_: date range, date limits

**Opening date**:
The date a date Field's calendar shows first while the Field is empty; it never fills the Field (DE: Startdatum).
_Avoid_: default date, default value, preset

**Field key**:
The stable identifier of a Field inside its Form, used in mail placeholders (DE: Feldschlüssel).
_Avoid_: field name, slug, id

**Conditional group**:
A part of a Form that is shown only while its Condition holds (DE: Bedingte Gruppe).
_Avoid_: section, step, page, conditional field

**Condition**:
A rule on one earlier Field that decides whether a Conditional group is shown (DE: Bedingung).
_Avoid_: logic, rule set, trigger

**Layout block**:
A core Columns, Column or Group block (including Row, Stack and Grid) that arranges Fields inside a Form (DE: Layout-Block).
_Avoid_: section, container, row field

### Sending

**Submission**:
One send of a Form by a visitor; in v1 it exists only until its mails are sent (DE: Einsendung).
_Avoid_: entry, message, lead

**Entry**:
A stored Submission; not part of v1 (DE: Eintrag).
_Avoid_: submission (for the stored form), record

**Submitter**:
The visitor who sends a Submission (DE: Einsender).
_Avoid_: user, customer, sender, Absender (the mail's From)

**Recipient**:
An address that receives a Form's Notification (DE: Empfänger).
_Avoid_: receiver, admin, to-address

**Notification**:
The mail to the Recipients that carries a Submission (DE: Benachrichtigung).
_Avoid_: admin mail, alert

**Confirmation**:
The optional acknowledgement mail to the Submitter; it never repeats submitted content (DE: Bestätigung).
_Avoid_: autoresponder, receipt, copy

### Mail appearance

**Mail layout**:
The site-wide frame around every mail, edited in the admin area (DE: Mail-Layout).
_Avoid_: mail template (for the frame), theme

**Body template**:
The text of a Notification or Confirmation, with placeholders for Field values; set per Form or as the site default (DE: Textvorlage).
_Avoid_: mail template (for the body), message template

### Administration

**Backup**:
A file that holds every Form and the non-secret settings, used to restore them on the same site (DE: Sicherung).
_Avoid_: export (for the file), dump
