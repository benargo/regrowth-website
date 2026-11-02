---
paths:
  - 'app/Http/Integrations/RaidHelper/**'
  - 'app/Jobs/RaidHelper/**'
  - 'tests/Feature/Jobs/RaidHelper/**'
---

# Raid Helper

## List-endpoint sign-ups only carry className
Raid Helper's list endpoint (`/servers/{serverId}/events`) returns sign-ups with `className` only; `cClassName` is absent (null). The single-event endpoint (`/events/{eventId}`) returns both. Code reading sign-ups from the list payload (e.g. FetchEvents bench sync) must filter on `className`, and tests must build list-style sign-ups with `className`, not `cClassName`.
