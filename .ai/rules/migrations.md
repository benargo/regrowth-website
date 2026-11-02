---
paths:
  - 'database/migrations/**'
---

# Migrations

## Use foreignIdFor(Model::class) for standard model FKs
For a *_id column referencing a model's default id PK, use $table->foreignIdFor(Model::class)->constrained() (import the model), not foreignId('col')->constrained('table') with string names. Reserve manual ->foreign()->references()->on() for non-standard keys (composite/string PKs, or references to a column other than id).

## Every migration must be reversible
Every migration has a down() that fully reverses its up(), including restoring transformed or back-filled data (e.g. re-adding a dropped column and filling it from the new structure). An up()-only or irreversible migration is allowed only when the user has explicitly agreed to it beforehand. Never write an empty or fake down().

## Create migrations with make:migration only
Every new migration is created with `vendor/bin/sail artisan make:migration <name> --no-interaction`, then filled in. Never write a migration file by hand or invent a timestamp (e.g. `2026_11_01_120000`). This applies in plans, subagent briefs and TDD steps too. For several migrations in a set order, run make:migration for each in that order with `sleep 1` between them. If a test loads a migration by filename, use the filename from the Artisan output.
