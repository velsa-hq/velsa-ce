---
title: Partitioned spaces
section: Bookings
order: 14
---

Some venues have spaces that can be subdivided by movable walls - a
**Grand Ballroom** with four sections is the canonical example.
Bookings need to be able to take any valid subdivision (e.g. "Section
2 only" vs. "the whole ballroom"), and the booking form has to refuse
selections that are physically impossible.

## The model

Each subdivided room is modeled as a **hierarchy of spaces** using a
parent-child link between adjacent sections:

- Every section is its own atomic space - no "Section 1-2" composite
  pseudospaces.
- The parent-child link captures **adjacency** ("these two share a
  wall and can be combined"), not containment.
- A booking that spans multiple sections attaches to each atomic
  section it covers.

A representative Grand Ballroom layout looks like this in the tree:

```text
Grand Ballroom Section 1
+-- Grand Ballroom Section 2
    +-- Grand Ballroom Section 3
    +-- Grand Ballroom Section 4
```

That structure encodes three real-world adjacencies:

1. Section 1 is adjacent to Section 2 (independent wall).
2. Section 2 is adjacent to Section 3 *and* Section 4 - and the wall
   between Section 2 and "the 3/4 column" is **a single piece**, so
   joining Section 2 with either 3 or 4 requires joining with both.
3. Section 3 and Section 4 are siblings (independent wall between
   them, can be combined or kept separate).

Smaller two-half rooms follow the same pattern: model Half 2 as a
child of Half 1.

## The valid-subset rule

When a booking is saved, the system checks the selected set against
two rules:

1. **All-or-none children**: if a selected space has any of its direct
   children selected, **all** its direct children must be selected.
2. **Connected roots**: the "roots" of the selection (selected spaces
   whose parent is not in the selection) must all share the same
   parent. This blocks disconnected picks like "Section 1+EH + Section
   3" without Section 2 between them.

Together those rules accept exactly the eight physically-possible
combinations of the Grand Ballroom:

| Selected | Why valid |
| --- | --- |
| {1} | leaf |
| {2} | leaf |
| {3} | leaf |
| {4} | leaf |
| {1, 2} | parent Section 1 + all its direct children (just 2) |
| {3, 4} | siblings under Section 2; no parent included so no rule applies |
| {2, 3, 4} | parent 2 + all its direct children {3, 4} |
| {1, 2, 3, 4} | every parent-child level satisfied |

And rejects the impossible ones:

| Rejected | Why |
| --- | --- |
| {2, 3} | 2 selected, 3 is its child, but 4 (the other child) missing |
| {2, 4} | same, with roles swapped |
| {1, 3} | Section 1 selected as root, 3 selected as root - different branches |
| {1, 3, 4} | same disconnect |
| {1, 2, 3} | 2 selected with only one of its children |

## What this changes in the booking form

The space picker on `/bookings/create` renders the spaces as an
**indented tree**, mirroring the structure above. Sub-spaces sit
visually beneath their parents. Picking an invalid subset returns a
form error with a clear message - either "every sub-space must be
included" or "different branches of the venue layout, so they can't be
booked as a single combination."

For "the full Grand Ballroom" the user checks all four boxes.

## Adding a partitioned venue

Partitioned-space configuration is set up during venue onboarding -
contact your implementation team to add a new partitioned room or
adjust the adjacency tree on an existing one. The validation rules
above apply automatically once the tree is in place.
