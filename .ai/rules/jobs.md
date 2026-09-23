---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Use updateOrCreate()/firstOrNew() for external-data sync
When syncing records from external APIs (Blizzard, Discord, Warcraft Logs, RaidHelper), use Model::updateOrCreate() keyed on the external id. Use firstOrNew() (not raw find()+save()) only when you need to inspect/diff fields before saving. Never use bare Model::find()-then-save().
