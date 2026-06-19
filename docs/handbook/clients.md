---
title: Clients
section: Clients
order: 25
surfaces:
  - /clients (pages/clients/index)
  - /clients/create (pages/clients/create)
  - /clients/archive (pages/clients/archive)
  - /clients/{id} (pages/clients/show)
  - /clients/{id}/edit (pages/clients/edit)
tour_ids:
  - client-new
  - client-create-form
  - client-add-contact
  - client-retire
  - client-archive-search
---

A **client** is the organization or individual on the other side of a
booking, lead, or contract. Every booking has a client; every lead
belongs to a client; every contract is bound to a booking whose client
it represents.

:::video clients-overview

## The clients list

`/clients` lists every client with their primary contact, industry,
contact count, number of leads, and current open pipeline value (sum of
weighted estimated value across all their open leads). Use the search
box to filter by name, or the Type dropdown to filter by client type.
**+ New client** (top-right) opens the create form; **Retired** opens
the archive of retired clients.

Five client types are supported:

| Type | Examples |
| --- | --- |
| Individual | Smith-Andersen Wedding, Garcia Quinceañera |
| Business | Northstar Industries Inc, Riverside Construction Co |
| Government | Pinecrest Schools District, County Veterans Group |
| Non-profit | Riverside Conservancy, Heartland Cattlemen Assn |
| Educational | Northstar Community College |

## Creating a client

**+ New client** opens `/clients/create`. Capture the name, type, and
optionally the industry/vertical, source, **address**, **tax ID**,
free-form **custom fields** (ad-hoc key/value details), and a
**primary contact** (name, role, email, phone). You can add more
contacts afterward from the client detail page. Creating a client whose
name matches an existing one isn't blocked, but you'll get a heads-up
toast so you can check for duplicates first.

## The client detail page

Click any client name to open `/clients/{id}` - a single scrollable
view of everything we know about this client. A **<- Clients** link at
the top returns to the list.

- **Summary card** - booking count + total confirmed revenue, open
  leads, total contracts, primary contact
- **Contacts** - every contact attached to this client. Add, edit,
  remove, and set the primary right here (see below)
- **Notes** - internal notes (free-form text)
- **Bookings / Leads / Contracts** - the client's full history; each
  row links to the underlying record

The booking + lead + contract sections are the most common entry
points - opening a client to find their last booking and pulling up
that booking is two clicks from the clients list.

### Managing contacts

A client can have any number of contacts. On the detail page:

- **+ Add contact** opens a dialog for name, role/title, email, phone,
  and an optional "primary contact" toggle. The first contact you add
  becomes primary automatically.
- **Set primary** on any non-primary contact promotes it (and demotes
  the previous primary).
- **Edit** / **Remove** manage an existing contact. Removing the
  primary clears the client's primary-contact pointer.

## Editing

Click **Edit** on the detail page to open `/clients/{id}/edit`. All
client fields are editable here: name, type, industry/vertical, source,
**address**, **tax ID**, **custom fields**, and notes. (Contacts are
managed on the detail page, not here.)

## Retiring + the archive

Clients are never hard-deleted. **Retire** (on the detail page) soft-
deletes a client: it drops off the active list but keeps all its
bookings, leads, contracts, and contacts intact. Retired clients live
under **Retired** (`/clients/archive`) - searchable by name, with a
**Restore** button that brings a client back to the active list. A
retired client's detail page shows a red **Retired** badge and a
**Restore** action.

## Documents

The client detail page has a **Documents** panel for attaching files that
should live permanently with the record - emails, client RFPs, Word/Excel
files, PDFs, scans. Give an optional label, choose a file, and attach;
each document opens in a new tab and can be removed. The same panel
appears on each **event (booking)** detail page for event-specific
paperwork. Managing client documents needs the *clients.manage*
permission; event documents need *bookings.edit*.

## Where clients appear elsewhere

The client's name is a link everywhere it appears - Bookings index +
detail, Lead detail, Pipeline cards, and the Clients index - so you can
jump to the full portfolio in one shot.
