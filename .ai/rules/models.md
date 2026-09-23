---
paths:
  - app/Models/GuildRank.php
  - 'app/Models/*.php'
---

# Models

## GuildRank.sort_order is 0-based and tied to Blizzard's rank index
GuildRank.sort_order deliberately starts at 0, unlike other sortable models (Boss, EventAssignment*, which are 1-based via the Spatie package default). This is intentional: sort_order doubles as the Blizzard guild-roster rank index, consumed directly in FetchGuildRoster, AddonController, and GuildRosterMemberCollection. Do not "fix" this to match other models' 1-based ordering — that would require an offset translation everywhere the value is compared against Blizzard API data. See the overridden setHighestOrderNumber() in GuildRank.php.

## Use PHP attributes for fillable/hidden, not legacy properties
Declare mass-assignable and hidden attributes with #[Fillable([...])] / #[Hidden([...])] class attributes (Illuminate\Database\Eloquent\Attributes). Never use protected $fillable, protected $guarded, or protected $hidden.

## Use Attribute class for accessors/mutators
Define accessors/mutators only via protected function name(): Attribute { return Attribute::make(...); }. Never use legacy getXxxAttribute()/setXxxAttribute() methods.

## Use #[Scope] attribute for local scopes
Define local query scopes as #[Scope] protected function name(Builder $query, ...): void. Do not use the legacy scopeXxx() naming convention.

## DatasetModel marker interface pairs with #[UsePolicy(DatasetPolicy::class)]
Simple admin-managed lookup/dataset models implement App\Contracts\Models\DatasetModel and declare #[UsePolicy(DatasetPolicy::class)] together — the marker interface lets DatasetPolicy type-hint generically across all of them.

## Primary key mechanism follows ID origin
Use the HasUuids trait for models whose ID is internally generated. For a model keyed by an externally-sourced ID (Discord snowflake, slug, etc.), use #[Table(keyType: 'string', incrementing: false)] alone instead — do not generate a UUID for an ID that already comes from outside the app.
