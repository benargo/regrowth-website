---
paths:
  - 'app/Services/**/*Data.php'
  - 'app/Services/**'
---

# Services

## DTOs extend Spatie Data, not manual Arrayable+JsonSerializable
Value objects/DTOs anywhere in the app (not just app/Http/Integrations/**/Data) extend Spatie\LaravelData\Data as final readonly-property classes. Override toArray()/jsonSerialize() only when the output shape must differ from the raw constructor properties. This supersedes the older Arrayable+JsonSerializable convention, which no longer appears anywhere in the codebase.

## Prefer facades over global helpers for framework primitives
Use facade syntax (Config::, Auth::, Route::, Response::, Session::, etc.) instead of global helper functions (config(), route(), response(), session(), etc.) for framework primitives.

This is a deliberate forward-looking convention: most existing code still uses helpers (Auth:: is currently the only facade in real use here), but new work should default to facades. This is about general app code (controllers, actions, jobs, services) — it does not change the separate, already-documented policy of preferring dependency injection over facades specifically for third-party API service/connector classes (Blizzard, Discord, WarcraftLogs, RaidHelper).

## Use updateOrCreate()/firstOrNew() for external-data sync
When syncing records from external APIs (Blizzard, Discord, Warcraft Logs, RaidHelper), use Model::updateOrCreate() keyed on the external id. Use firstOrNew() (not raw find()+save()) only when you need to inspect/diff fields before saving. Never use bare Model::find()-then-save().
