<?php

namespace App\Models;

use App\Contracts\HasCharacterMedia;
use App\Enums\Gender;
use App\Observers\CharacterObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy([CharacterObserver::class])]
#[Fillable(['id', 'game_version_id', 'name', 'surname', 'level', 'rank_id', 'playable_class_id', 'playable_race_id', 'gender', 'user_id', 'is_main', 'is_loot_councillor'])]
#[Hidden(['created_at', 'updated_at', 'surname_key'])]
class Character extends Model implements HasCharacterMedia, HasMedia
{
    use HasFactory, InteractsWithMedia, Prunable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_loot_councillor' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'is_main' => 'boolean',
            'is_loot_councillor' => 'boolean',
        ];
    }

    // ============ Media ============

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::MEDIA_COLLECTION)->singleFile();
        $this->addMediaCollection(self::MEDIA_COLLECTION_RENDER)->singleFile();
    }

    // ============ Custom attributes ============

    /**
     * Stores the name with surrounding whitespace trimmed.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::trim($value),
        );
    }

    /**
     * Stores the surname with surrounding whitespace trimmed, or null when it is blank.
     */
    protected function surname(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                if ($value === null) {
                    return null;
                }

                $trimmed = Str::trim($value);

                return $trimmed === '' ? null : $trimmed;
            },
        );
    }

    /**
     * The name followed by the surname, or the name alone when there is no surname.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->surname === null ? $this->name : "{$this->name} {$this->surname}",
        );
    }

    /**
     * URL-safe slug derived from the full name; used in route bindings.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->full_name),
        );
    }

    /**
     * Get the main character from the linked characters.
     */
    protected function mainCharacter(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->linkedCharacters()->where('is_main', true)->first(),
        );
    }

    // ============ Scopes ============

    /**
     * Order the query by name, then surname, with a null surname first.
     */
    #[Scope]
    protected function orderByFullName(Builder $query): void
    {
        $query->orderBy($query->qualifyColumn('name'))->orderBy($query->qualifyColumn('surname'));
    }

    // ============ Name splitting ============

    /**
     * Split a name into its first word and the rest as the surname when the game
     * version uses surnames, otherwise return the whole trimmed name. A name that
     * is not valid UTF-8 is returned whole with a null surname.
     *
     * @return array{name: string, surname: ?string}
     */
    public static function splitName(string $name, bool $usesSurnames): array
    {
        $trimmed = Str::trim($name);

        if (! $usesSurnames) {
            return ['name' => $trimmed, 'surname' => null];
        }

        $parts = preg_split('/\s+/u', $trimmed, 2);

        if ($parts === false) {
            return ['name' => $name, 'surname' => null];
        }

        return ['name' => $parts[0], 'surname' => $parts[1] ?? null];
    }

    // ========== Relationships ============

    /**
     * Get the game version this character belongs to.
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /**
     * Get the characters linked to this character, via `character_links` rows whose
     * `linked_character_id` is this character. Links are one-directional; a two-way
     * link requires a row in each direction.
     */
    public function linkedCharacters(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'character_links', 'linked_character_id', 'character_id');
    }

    /**
     * Get the planned absences for the character.
     */
    public function plannedAbsences(): HasMany
    {
        return $this->hasMany(PlannedAbsence::class);
    }

    /**
     * Get the playable class associated with the character.
     */
    public function playableClass(): BelongsTo
    {
        return $this->belongsTo(PlayableClass::class);
    }

    /**
     * Get the playable race associated with the character.
     */
    public function playableRace(): BelongsTo
    {
        return $this->belongsTo(PlayableRace::class);
    }

    /**
     * Get the character's race placement.
     *
     * @return HasOne<RacePlacement, $this>
     */
    public function racePlacement(): HasOne
    {
        return $this->hasOne(RacePlacement::class);
    }

    /**
     * Get the guild rank associated with the character.
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(GuildRank::class, 'rank_id');
    }

    /**
     * Get the playable specializations associated with the character.
     */
    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(PlayableSpecialization::class, 'pivot_character_specializations', 'character_id', 'playable_specialization_id')
            ->withPivot('is_raid_spec')
            ->withTimestamps();
    }

    /**
     * Get the Discord user who owns the character.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Warcraft Logs reports this character participated in.
     */
    public function warcraftLogsReports(): BelongsToMany
    {
        return $this->belongsToMany(Report::class, 'pivot_characters_raid_reports', 'character_id', 'raid_report_id')
            ->using(CharacterReport::class)
            ->withPivot('presence', 'is_loot_councillor');
    }

    // ========== Portrait ============

    /**
     * Remember for three days that Blizzard has no portrait for this character.
     */
    public function rememberPortraitMissing(): void
    {
        Cache::put($this->portraitMissingCacheKey(), true, HasCharacterMedia::PORTRAIT_MISSING_FOR_SECONDS);
    }

    /**
     * Whether Blizzard was recently found to have no portrait for this character.
     */
    public function isPortraitKnownMissing(): bool
    {
        return Cache::has($this->portraitMissingCacheKey());
    }

    private function portraitMissingCacheKey(): string
    {
        return "character-portrait:missing:{$this->id}";
    }

    // ========== Prunable ============

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        return static::where('updated_at', '<=', now()->subDays(14))
            ->whereDoesntHave('racePlacement');
    }
}
