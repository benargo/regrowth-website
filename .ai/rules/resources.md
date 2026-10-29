---
paths:
  - 'app/Http/Resources/**'
---

# Resources

## Guard embedded relations with whenLoaded()
When a Resource embeds a relationship as a nested Resource/Collection, wrap the access in $this->whenLoaded('relation', fn () => ...) rather than accessing it unconditionally.

## Map loaded relations to Resource classes
When a Resource includes a loaded related model (or collection of models), map it to another Resource class rather than returning the raw model/array. Prefer `$this->relation->toResource()` / `->toResourceCollection()` (usually inside `whenLoaded()`). Only instantiate a specific resource (`new FooResource(...)` / `FooResource::collection(...)`) when an override of the model's default resource is required.
