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
One input of a Form, such as name, email, phone, address, text, select, message or consent (DE: Feld).
_Avoid_: input, control, element

**Field key**:
The stable identifier of a Field inside its Form, used in mail placeholders (DE: Feldschlüssel).
_Avoid_: field name, slug, id

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
