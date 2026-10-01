---
paths:
    - "app/**/*.php"
---

# App

## Use UPPERCASE case names in enums

Backed enum cases should use UPPERCASE names (e.g. `case RETAIL = 'retail';`), not lowercase or StudlyCase. This applies to the case identifier only — the backing string value's casing is unaffected and should match whatever the external system/API expects.

## Prefer constructor/method injection over app()/resolve() service location

Acquire dependencies via constructor property promotion (or method-injected handle() parameters for jobs) everywhere DI is possible. Reach for app(Class::class) only where constructor injection is structurally unavailable — Eloquent Model accessors and Job failed()/other non-handle() callbacks — and nowhere else.

## Use DB::transaction() closures, not manual begin/commit/rollback

Wrap multi-write operations in DB::transaction(function () { ... }). Never call DB::beginTransaction()/commit()/rollBack() manually.

## foreach for side effects, collect() pipelines for transforms

Use foreach for imperative/side-effecting iteration (DB writes, building up state across iterations). Use collect()->map()/filter()/... pipelines for data transformation. Avoid raw array_map/array_filter where either idiom above fits.

## Prefer Carbon:: over now() for the current instant

Use Carbon::now() (not the now() global helper) to get the current instant, for consistency with other Carbon:: static calls used elsewhere (parsing, constructing from other values, e.g. Carbon::parse(), Carbon::yesterday()/tomorrow()).

This is a deliberate forward-looking convention: most existing code still uses now(), but new work should default to Carbon::now().
