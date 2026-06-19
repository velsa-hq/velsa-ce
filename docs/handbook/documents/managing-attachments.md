---
title: Document attachments
section: Clients
order: 26
surfaces:
  - route: /clients/{client}/documents
    method: POST
  - route: /clients/{client}/documents/{media}
    method: DELETE
  - route: /bookings/{booking}/documents
    method: POST
  - route: /bookings/{booking}/documents/{media}
    method: DELETE
  - component: clients/show
  - component: bookings/show
tour_ids:
  - record-documents-section
  - record-documents-list
  - record-documents-download
  - record-documents-remove
  - record-documents-attach
---

The **Documents** panel lets you attach files - signed paperwork, floor
plans, vendor quotes, anything worth keeping with the record - directly
to a client or a booking. The same panel appears on both, so the steps
below work identically wherever you see it.

:::video managing-document-attachments

## Where it lives

The panel sits on a record's detail page, marked by the **paperclip**
heading. On a **client** (`/clients/{id}`) it holds files tied to that
organization; on a **booking** (`/bookings/{id}`) it holds files tied to
that single event. There's nothing to enable - the panel is always
present on these pages.

## The attached-document list

Each attachment is one row showing its **label**, the original
**file name**, and the file **size**. Click a row to open the file in a
new tab. If nothing is attached yet, the panel reads *No documents
attached* until you add the first file.

## Attaching a file

Type an optional **label** (it defaults to the file name if you leave it
blank), choose a file, and click **Attach**. The upload happens in place -
the page scroll is preserved and the new row appears in the list as soon
as it finishes. If the file is rejected (too large, or a disallowed
type), the error shows beneath the form and nothing is stored.

## Opening an attachment

The label of each attached file is a link. Selecting it opens the file in
a new browser tab, leaving the record page untouched behind it.

## Removing an attachment

Use the **trash** button on a row to delete that attachment. You're asked
to confirm first; once confirmed the file is removed from storage and the
row disappears. Removal is permanent, so download a copy first if you
might need it again.

## Permissions

The upload and remove controls only appear when you have manage rights on
the record. Users with view-only access still see the list and can open
the attached files, but the **Attach** form and the trash buttons are
hidden for them.
