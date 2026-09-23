---
paths:
  - 'database/migrations/**'
---

# Migrations

## Use foreignIdFor(Model::class) for standard model FKs
For a *_id column referencing a model's default id PK, use $table->foreignIdFor(Model::class)->constrained() (import the model), not foreignId('col')->constrained('table') with string names. Reserve manual ->foreign()->references()->on() for non-standard keys (composite/string PKs, or references to a column other than id).

## Write real down() reversals, never skip them
Every migration must implement a genuine down() that reverses up(), including multi-step data migrations (rename/backfill/type-change) — not just Schema::dropIfExists where that's the correct reversal, and never an empty or omitted down().
