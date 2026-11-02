---
paths:
  - 'app/Models/Scopes/**'
  - 'app/Models/Concerns/**'
  - 'app/Models/*.php'
---

# Scopes

## Scope classes only for constraints on every query
Use a `make:scope <Name>Scope` class (in `app/Models/Scopes`, registered with `#[ScopedBy]`) only when a constraint must apply to every query on the model, such as tenancy-style filtering shared by several models. A constraint needed on only some queries is a `#[Scope]` local scope on each model (see models.md). Never put a `#[Scope]` local-scope method in a shared trait under `app/Models/Concerns`.
