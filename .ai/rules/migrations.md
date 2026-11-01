---
paths:
  - 'database/migrations/**'
---

# Migrations

## Use foreignIdFor(Model::class) for standard model FKs
For a *_id column referencing a model's default id PK, use $table->foreignIdFor(Model::class)->constrained() (import the model), not foreignId('col')->constrained('table') with string names. Reserve manual ->foreign()->references()->on() for non-standard keys (composite/string PKs, or references to a column other than id).

## Every migration must be reversible
Every migration has a down() that fully reverses its up(), including restoring transformed or back-filled data (e.g. re-adding a dropped column and filling it from the new structure). An up()-only or irreversible migration is allowed only when the user has explicitly agreed to it beforehand. Never write an empty or fake down().
