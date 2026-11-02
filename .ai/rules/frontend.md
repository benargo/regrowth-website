---
paths:
  - 'resources/**'
---

# Frontend

## Theme assets stay in resources/, never public/
Images and fonts (e.g. `bg_camelot.webp`, `friz-quadrata-regular-os.woff`) live in `resources/`. Never move them to `public/`, even to address payload-size or caching concerns. If a fix seems to need a file in `public/`, ask how to serve it from `resources/` (Vite import, a route, or inlining) first.

## Never render the "Thunderstrike" tagline
Regrowth appears without the realm tagline. Don't print the realm name (`config('services.blizzard.realm.name')`) alongside the guild name in footers, headers or titles on new or redesigned pages (JSX or Blade); use just "Regrowth". Where a page used to show it, add `assertDontSee('Thunderstrike')`.
