# Branding assets

Drop branded image files here. Everything under `public/branding/` is served
directly at the matching URL (no Vite processing, no import statements needed).
Files can be referenced anywhere in the app:

| Location          | How to reference                                       |
| ----------------- | ------------------------------------------------------ |
| Inertia/TSX pages | `<img src="/branding/sentinel-bay/logo.svg" />`        |
| Blade templates   | `{{ asset('branding/sentinel-bay/logo.svg') }}`        |
| Contract PDFs     | Same `/branding/...` URL (server-rendered HTML)        |
| Emails            | Same, absolute URL via `config('app.url')`             |

## Folder convention

| Folder          | Use for                                                  |
| --------------- | -------------------------------------------------------- |
| `sentinel-bay/` | The bundled demo pack (logo + stock imagery).            |
| `venues/`       | Per-venue logos and photos, subdivided by venue slug.    |
| `stock/`        | Generic background imagery for the root and login pages. |

The `stock/` folder backs the configurable login/root background, selected via
the `branding.stock_background_folder` system setting. Add your own images
there (`.webp` or `.jpg`), then point the setting at the folder under
**System Settings -> Branding**.

## Naming

- Lowercase, kebab-case: `grand-ballroom-aerial.jpg`, not `Grand Ballroom (1).JPG`
- Include intent in the name: `seal-color.svg`, `seal-monochrome-white.svg`
- Variant suffixes: `-light`, `-dark`, `-monochrome`, `-color`, `-square`, `-wide`
