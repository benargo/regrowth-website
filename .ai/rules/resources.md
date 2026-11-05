---
paths:
  - 'app/Http/Resources/**'
---

# Resources

## Guard embedded relations with whenLoaded()
When a Resource embeds a relationship as a nested Resource/Collection, wrap the access in $this->whenLoaded('relation', fn () => ...) rather than accessing it unconditionally.

## Map loaded relations to Resource classes
When a Resource includes a loaded related model (or collection of models), map it to another Resource class rather than returning the raw model/array. Prefer `$this->relation->toResource()` / `->toResourceCollection()` (usually inside `whenLoaded()`). Only instantiate a specific resource (`new FooResource(...)` / `FooResource::collection(...)`) when an override of the model's default resource is required.

## Derived flags go in a nested meta block
Computed state flags for the frontend (e.g. PlannedAbsence `has_started`/`has_ended`) go under a nested `'meta' => $this->when(..., fn () => [...])` key inside `toArray()`, in snake_case, not alongside the model's data keys. Don't use a per-item `with()` for them: `ResourceCollection::toArray()` and `->resolve()` drop it. Use `with()` only when the resource is the top-level response.

## Definition-array keys are alphabetical
Keys in definition arrays (e.g. a Resource's `scopeDefinitions()`) go in alphabetical order. Add new keys in that order, and write `array_keys` assertions in alphabetical order. When a test's expected key order disagrees with alphabetical code, fix the test, not the code.

## Don't call `->value` on enums inside Resource arrays

PHP's `json_encode` and Laravel's HTTP layer serialize backed enums to their value automatically at the final serialization boundary. A Resource's `toArray()` returns a plain PHP array internally, so the correct contract at that layer is the enum **case** itself, not its string value.

When asserting on resource output via `->resolve()` in unit tests, compare against the enum case (e.g. `SignupStatus::Confirmed`), not `SignupStatus::Confirmed->value`. Only use `->value` when explicitly building a string for storage, display, or a non-JSON context.
