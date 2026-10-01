---
paths:
    - "app/Http/Controllers/**"
---

# Controllers

## Use Form Request classes for validation

Validate via a dedicated Form Request class (app/Http/Requests, mirroring the controller subdirectory) rather than inline $request->validate(). Only use inline validate() for trivial single/two-field checks that don't warrant a dedicated class.

## Invokable controllers for single-purpose endpoints only

Use a single-action \_\_invoke controller for endpoints that perform one action (e.g. a webhook handler, a single API operation). Use a plain multi-method controller (index/show/store/update/destroy-style) for anything with more than one related action, rather than Route::resource.

## Prefer Actions over private controller methods for business logic

Extract shared multi-step business logic into a dedicated class in app/Actions (invoked via handle()/execute()/\_\_invoke()) rather than leaving it as private/protected helper methods on the controller. Reach for app/Services only when the logic is genuinely reused across multiple controllers. Organise actions by domains as the `app/Actions` directory will get bloated over time.

This is a deliberate forward-looking convention: most existing controllers still keep this logic as private helper methods, and app/Actions currently has only one class. New work should default to Actions to grow that pattern over time — do not treat the current codebase majority as the rule here.

## Assign middleware via the #[Middleware] attribute

Declare controller middleware with the #[Middleware(...)] PHP attribute (Illuminate\Routing\Attributes\Controllers\Middleware) on the class or method, not implements HasMiddleware and not ->middleware() chains in routes/\*.php.

## Authorize via the #[Authorize] attribute

Authorize controller actions with the #[Authorize('ability', Model::class)] PHP attribute on the method (or class), not $this->authorize() or Gate::authorize() calls inside the method body. Reserve ->can() for conditional checks that affect response shape rather than gating access.

## Prefer facades over global helpers for framework primitives

Use facade syntax (Config::, Auth::, Route::, Response::, Session::, etc.) instead of global helper functions (config(), route(), response(), session(), etc.) for framework primitives.

This is a deliberate forward-looking convention: most existing code still uses helpers (Auth:: is currently the only facade in real use here), but new work should default to facades. This is about general app code (controllers, actions, jobs, services) — it does not change the separate, already-documented policy of preferring dependency injection over facades specifically for third-party API service/connector classes (Blizzard, Discord, WarcraftLogs, RaidHelper).
