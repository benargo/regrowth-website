---
paths:
    - "app/**/*.php"
---

# App

## Use PascalCase case names in enums

Name enum cases in PascalCase (e.g. `case GuildRanks = 'guild_ranks';`), not UPPERCASE or snake_case. The backing value keeps whatever format the external system or storage expects. Some existing enums still use UPPERCASE cases; leave them alone unless you are doing a coordinated rename of the whole enum and its callers.

## Inject dependencies; use app() only where injection is impossible

Use constructor property promotion for dependencies an object needs throughout its lifetime, and method injection (e.g. a job's `handle()`) for dependencies one container-invoked method needs. Use `app(Class::class)` only where injection is structurally unavailable, such as Eloquent model accessors and job `failed()` callbacks. Never use `resolve()`.

## Use DB::transaction() closures, not manual begin/commit/rollback

Wrap multi-write operations in `DB::transaction(function () { ... })`. Never call `DB::beginTransaction()`, `commit()` or `rollBack()` by hand.

## foreach for side effects, collection pipelines for transforms

Use `foreach` for side-effecting iteration (DB writes, building up state across iterations); a higher-order `$models->each->method()` is fine for a single call on each item of an existing collection. Use `collect()->map()/filter()/...` pipelines to transform data, in preference to raw `array_map`/`array_filter` when the pipeline reads more clearly.

## Use now() for the current instant

Use the `now()` and `today()` helpers rather than `Carbon::now()`/`Carbon::today()`. Keep `Carbon::` for constructing other instants (`Carbon::parse()`, `Carbon::createFromTimestamp()`).
