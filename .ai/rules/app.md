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

## Start Eloquent queries with a static call, not query()

Start a model query by calling the builder method statically (`Phase::whereHas(...)`, `User::where(...)->first()`). Don't prefix it with `::query()` — Eloquent's `__callStatic()` already forwards the call to a fresh builder, so `query()` is an extra call that adds nothing. Keep `Model::query()` only when you need the bare builder itself, e.g. to return it or pass it on before any constraint is applied.

## Use now() for the current instant

Use the `now()` and `today()` helpers rather than `Carbon::now()`/`Carbon::today()`. Keep `Carbon::` for constructing other instants (`Carbon::parse()`, `Carbon::createFromTimestamp()`).

## Match within game versions; never delete data to reconcile
Characters and reports only ever match against characters in the same game version; never match a name across the whole `characters` table. A report's version is its stored `game_version_id`, derived from its Warcraft Logs guild and `start_time`. An event's version is its stored `game_version_id`, the single version its raids share. Resolve every external name through `App\Actions\Characters\MatchCharacterName`, one version and one name per call.

Reconciling old rows (pivots, attendance, event links) written under earlier matching rules may reassign or flag them, but must never delete them — this is a red line. A character with a null `game_version_id` is missing its match-up, not wrong: never detach, delete or overwrite its rows on that basis.

## Call Actions with ::run(), not dependency injection
Never inject Laravel Actions (`AsAction`) through constructor or method DI. For a single call, use `ActionClassName::run(...)`. When the same action runs multiple times in one scope (e.g. a loop or collection pipeline), resolve it once with `$action = ActionClassName::make()` and call `$action->handle(...)` each time. This overrides the general "inject dependencies" rule for Action classes only.

## Don't reference public constants on other classes
A class must not reference public constants defined on another concrete class (e.g. `OtherClass::SOME_CONSTANT`). When classes need to share a constant, define it on a contract (interface in `app/Contracts/**`) and have each class that needs it implement that contract, then reference it via `self::`/`static::`.

## No static helper classes; bind shared collaborators in a provider
Don't design shared helpers or state holders with static methods or static state (e.g. a static WeakMap registry). Make them instance classes with a container binding and inject them. Bindings shared across several integrations go in AppServiceProvider, not an individual integration's provider.

## Laravel-native mechanisms before new support classes
Solve cross-cutting request concerns (e.g. coalescing cache-tag flushes) with Laravel's own mechanisms first: middleware, events/listeners, `Event::defer()`, the `defer()` helper for post-response work, and `Context` for request-scoped data. Don't invent bespoke helper/service classes under `app/Support` for them. Any plan that introduces a class type outside the usual Actions/Listeners/Middleware/Jobs/etc. must call it out and justify why a framework mechanism doesn't fit.

## Union-Find must be iterative

PHP recursive closures that capture themselves by reference (`&$find`) cause segmentation faults (signal 11) under PHPUnit when called deeply. Always implement Union-Find with an iterative find + path-compression loop:

```php
$find = function (string $id) use (&$parent): string {
    $root = $id;
    while ($parent[$root] !== $root) { $root = $parent[$root]; }
    while ($parent[$id] !== $root) { $next = $parent[$id]; $parent[$id] = $root; $id = $next; }
    return $root;
};
```

## Prefer Laravel attributes over properties and methods
Wherever Laravel provides a PHP attribute for a configuration value, use the attribute instead of the equivalent property or method. For example, on queued jobs use `#[Tries(3)]`, `#[Backoff(300, 300)]`, `#[UniqueFor(3600)]`, `#[Timeout(...)]` and `#[MaxExceptions(...)]` from `Illuminate\Queue\Attributes`, not `public int $tries` or `backoff()`. Before adding a property or method, check whether the framework namespace has an attribute for it (e.g. `ls vendor/laravel/framework/src/Illuminate/*/Attributes`). Tests should assert the attribute via reflection, not the removed property.
