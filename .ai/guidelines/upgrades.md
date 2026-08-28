# Dependency Upgrades

## Build major-version upgrades around the official codemod

When a library ships an official upgrade/migration tool (e.g. `npx @tailwindcss/upgrade`), make running it the first explicit step of the plan, then scope manual work to only what the tool cannot do.

The codemod handles the mechanical bulk (build pipeline, renames, directive swaps) reliably; hand-doing it is error-prone and slower. Document precisely which gaps remain manual — for the Tailwind v4 upgrade that was: regex safelist → `@source inline()`, and v3-default compatibility shims for border/ring/placeholder/cursor.
