---
paths:
  - 'tests/Feature/Database/Seeders/**'
---

# Seeders

## Seeder tests use a random GameVersion, never a named state
Tests for game-version-scoped seeders create a random `GameVersion::factory()->create()` and point the seeder at it through an overridable slug (e.g. a public `$gameVersionSlug` property). Never use a named factory state such as `tbc()` or hardcode a slug: GameVersionSeeder slugs change over time.
