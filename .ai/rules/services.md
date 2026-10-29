---
paths:
  - 'app/Services/**/*Data.php'
  - 'app/Services/**'
---

# Services

## DTOs extend Spatie Data, not manual Arrayable+JsonSerializable
Value objects/DTOs anywhere in the app (not just app/Http/Integrations/**/Data) extend Spatie\LaravelData\Data as final readonly-property classes. Override toArray()/jsonSerialize() only when the output shape must differ from the raw constructor properties. This supersedes the older Arrayable+JsonSerializable convention, which no longer appears anywhere in the codebase.

## Prefer Laravel's helpers over facades for framework primitives
Where Laravel provides a global helper for a framework primitive, use it: `now()`, `config()`, `session()`, `redirect()` / `back()`, `route()`, `response()`, `abort()` / `abort_if()`. This follows laravel-best-practices `style.md` and Spatie's "use the `config()` helper". Reach for a facade only when no helper covers the call, or the helper would hide which method is being used. Examples: `Log::warning()`, `Storage::disk()`, `Cache::remember()`, `Route::` in route files.

This is about general app code (controllers, actions, jobs, services). It does not change the separate policy of preferring dependency injection over both helpers and facades for third-party API service/connector classes (Blizzard, Discord, WarcraftLogs, RaidHelper).

## Use updateOrCreate()/firstOrNew() for external-data sync
When syncing records from external APIs (Blizzard, Discord, Warcraft Logs, RaidHelper), use Model::updateOrCreate() keyed on the external id. Use firstOrNew() (not raw find()+save()) only when you need to inspect/diff fields before saving. Never use bare Model::find()-then-save().
