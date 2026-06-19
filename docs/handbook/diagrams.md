---
title: Floor plans
section: Diagrams
order: 50
surfaces:
  - route: /bookings/{booking}/diagram
    method: GET
  - route: /bookings/{booking}/diagram
    method: POST
  - route: /diagrams/{diagram}/apply-template/{layoutTemplate}
    method: POST
  - route: /diagrams/{diagram}/save-as-template
    method: POST
  - component: bookings/diagram
tour_ids:
  - diagram-palette
  - diagram-snap-toggle
  - diagram-apply-template
  - diagram-auto-layout
  - diagram-save-as-template
  - diagram-properties
  - diagram-save
  - diagram-floorplan-upload
  - diagram-export-png
  - diagram-print
---

Every booking can have a **floor plan** for each of its spaces - a
drag-and-drop visual layout showing where chairs, tables, stages,
bars, and other props go. Open one from the booking detail page ->
**Floor plan**, or at `/bookings/{id}/diagram`.

Layouts are versioned: every save creates a restorable snapshot, so
you can revert if an edit goes sideways.

## Adding objects

The right-side **Library** is a flat list of palette items:

- 60" round (seats 8)
- 72" round (seats 10)
- Cocktail
- 6' rect
- 8' rect
- Chair
- Stage 4×8
- Booth 10×10
- Tent 20×20

Click an item to drop it on the canvas at a sensible position.
Drag any placed object to move it; click to select; press
**Delete** or **Backspace** to remove the selected object.

## Selecting + properties

Click any object to select it (selection ring lights up). The
**Properties** panel on the right side shows the selected object's
details and lets you tweak:

- **Position** (x, y in canvas pixels - rarely useful directly, but
  there if a layout needs precise nudging)
- **Width / height** in feet
- **Rotation** in degrees - the older "axis-aligned only" limit is
  gone; any object can be rotated arbitrarily and dragged in its
  rotated state

The panel also shows the object's seat count where applicable so
you can see what each chair / round / row contributes to the
total.

## Snap + grid

A faint 1-foot grid is drawn behind the canvas (10 px per foot at
the default scale). The **Snap** toggle in the header turns object
snapping on or off:

- **On** (default) - dragged objects snap to grid intersections
  *and* to the edges of other objects (alignment guides flash when
  they catch). Layouts stay tidy without manual nudging.
- **Off** - free positioning. Useful for fine-grained
  pixel-precise placement that snap would fight you on.

## Apply template

The **Apply template...** button in the header opens the layout
template picker. Templates are saved arrangements (rounds of 10 ×
12, classroom-style rows, theater-in-the-round, etc.) administered
at `/admin/layout-templates`. Pick one and choose:

- **Replace** - clears the canvas and drops the template in
- **Append** - adds the template's objects to whatever's already
  there

The button is disabled if no templates exist yet or if the diagram
is locked. See [Layout templates](/docs/admin/layout-templates) for
how templates are created and scoped.

## Auto-layout from headcount

:::video floor-plan-auto-layout

**Auto-layout...** opens a dialog that places banquet rounds for a
target headcount with no manual dragging:

| Input | Default | Meaning |
| --- | --- | --- |
| **Headcount** | 80 | Total seats needed |
| **Table type** | 60" round (seats 8) | 60" seats 8, 72" seats 10 |
| **Buffer (ft)** | 4 | Clearance between tables and walls |
| **Include 4×8 stage** | on | Drops a stage along the long wall |

The auto-layouter walks every candidate grid slot, computes each
table's axis-aligned bounding box (rotation-aware), and skips any
slot that would overlap a wall, column, door, or other space
constraint. The result is a layout that honors the room's
permanent infrastructure rather than ignoring it.

After applying, a status banner reports:

- **All requested tables placed** - emerald banner, "placed N
  tables clean of every constraint."
- **Partial fit** - amber banner, "placed M of N tables -
  constraints blocked the rest." Knobs to turn: reduce the buffer,
  switch to larger tables (fewer needed for same headcount), or
  drag the missing ones in manually.

Constraints come from the Space, not the booking - see
[Space constraints](/docs/admin/space-constraints) for how they're
modeled.

## Save as template

**Save as template** captures the current canvas as a new
LayoutTemplate so you (or anyone) can drop the same arrangement on
a future booking. Pick a name and an optional venue scope (global
vs venue-specific). Doesn't affect the current booking - saving a
template is purely additive.

## Stats panel

The counter at the top of the Library aside (right side, below
the palette) shows total objects placed and total seats. Each
seating object has a seat value (60" round = 8, 72" round = 10,
etc.) - the sum is your at-a-glance capacity check against the
booking's estimated attendance.

Two header banners surface when the numbers are off:

- **Over capacity** (rose) - placed seats > the Space's stated
  capacity
- **Short of estimate** (amber) - placed seats < the booking's
  expected attendance

## Constraint backdrop

The Space's constraint set (walls, doors, windows, columns,
outlets, posts) renders as a non-draggable backdrop layer below
every event object. You can see exactly where infrastructure sits
while you lay out tables. The backdrop is read-only here - edit it
at `/admin/spaces/{space}/constraints` (see
[Space constraints](/docs/admin/space-constraints)).

## Uploaded floor-plan backdrop

If you have an existing scale drawing of a space (an architect's plan or a CAD
export saved as an image), use **Upload floor plan** on the toolbar to set it as
a faded backdrop behind the grid, then lay tables, booths, and staging on top of
it. The image belongs to the space, so it's reused across every event there;
**Replace floor plan** swaps it and **Remove floor plan** clears it. Uploading
needs the space-management permission. (PNG / JPEG / WebP.)

## Saving + versioning

Click **Save** to persist the current layout to the booking. Each
save creates a new version so earlier layouts remain available for
restore if you need to roll back. The button reads **Save** when
dirty, **Saved** when up-to-date, and **Locked** if the booking is
in a state that prevents diagram changes.

## What's not (yet) in the editor

- Multi-space layouts in a single view (each space still gets its
  own diagram)
- Custom object types beyond the built-in palette
- Print / PDF export
- Per-table seat assignment (names -> chairs)

## See also

- [Layout templates](/docs/admin/layout-templates) - saved
  arrangements that drop onto the canvas
- [Space constraints](/docs/admin/space-constraints) - the
  walls/columns/outlets backdrop that auto-layout honors
- [Partitioned spaces](/docs/bookings/partitioned-spaces) - picking
  the right combo before laying it out

## Exporting & printing

:::video floor-plan-export

The floor-plan toolbar has **Export PNG** and **Print** actions. *Export PNG*
downloads a high-resolution image of the current layout (named for the booking
reference); *Print* opens the layout in a new tab and sends it to the printer -
handy for the day-of ops packet or sharing a setup diagram with a client or
vendor.
