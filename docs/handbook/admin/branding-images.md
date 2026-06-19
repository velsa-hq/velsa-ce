---
title: Background images
section: Admin
order: 74
surfaces:
  - route: /admin/branding-images
    method: GET
  - route: /admin/branding-images
    method: POST
  - route: /admin/branding-images/{brandingImage}
    method: PUT
  - route: /admin/branding-images/{brandingImage}
    method: DELETE
  - component: admin/branding-images/index
tour_ids:
  - branding-image-upload
  - branding-image-gallery
  - branding-image-active-toggle
---

The public **welcome** and **sign-in** pages show a full-bleed background
photo, picked at random from a pool each visit. **Background images** lets an
admin manage that pool from the app - upload new photos, deactivate ones you
don't want shown, and remove them - with no server access or file drop needed.

:::video branding-images

## Managing the pool

**Admin -> Background images** shows the current pool as a gallery
(`data-tour-id="branding-image-gallery"`). Each photo has an **Active** toggle
(`data-tour-id="branding-image-active-toggle"`) - only active photos appear in
the welcome/sign-in rotation, so you can stage a photo or pull one without
deleting it - and a **Remove** action that deletes it for good.

To add one, use the **upload** control (`data-tour-id="branding-image-upload"`):
pick an image file (JPEG, PNG, or WebP), give it an optional caption for your own
reference, and save. It joins the rotation immediately if active.

> Tip: wide, landscape photos that read well behind centered text work best -
> the image is cropped to fill and sits behind a darkening overlay.

## How the rotation is chosen

When the managed pool has any active images, the welcome and sign-in pages draw
from it. With an empty pool, the app falls back to the stock images shipped in
the configured branding folder (see **System settings -> Branding**), so a fresh
install still looks finished before you've uploaded anything.
