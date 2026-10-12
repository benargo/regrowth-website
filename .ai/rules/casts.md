---
paths:
  - 'app/Casts/*.php'
---

# Casts

## Dedicated cast classes for non-trivial attribute casting
Any casting logic beyond a built-in cast string or a backed enum class goes in a dedicated App\Casts\As* class implementing CastsAttributes (add SerializesCastableAttributes when JSON serialization must differ from storage). Do not fake casting with Attribute::make(get:/set:). A `Spatie\LaravelData\Data` value object may serve as its own cast by overriding `castUsing()` to return an anonymous `CastsAttributes` class (e.g. `App\Data\PlannedAbsenceReasonData`), when the stored format isn't laravel-data's default JSON.
