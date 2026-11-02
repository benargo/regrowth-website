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
