---
paths:
  - 'database/migrations/**'
---

# Migrations

## Use foreignIdFor(Model::class) for standard model FKs
For a *_id column referencing a model's default id PK, use $table->foreignIdFor(Model::class)->constrained() (import the model), not foreignId('col')->constrained('table') with string names. Reserve manual ->foreign()->references()->on() for non-standard keys (composite/string PKs, or references to a column other than id).

## Default to up()-only; add down() only when safely reversible
Follow Spatie's guideline: do not write down() methods by default. Only add one when the change can be fully and safely reversed (e.g. a plain create/dropIfExists). Never write a down() that drops populated columns or cannot restore transformed data (rename/backfill/type-change migrations) — prefer a forward-fix migration instead. Never write an empty or fake down().
