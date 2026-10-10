<?php

namespace App\Models;

use App\Casts\AsSlug;
use App\Casts\AsTheme;
use App\Contracts\Models\DatasetModel;
use App\Contracts\Models\EditLockable;
use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Support\NameSlug;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\TracksUsage;
use App\Models\WarcraftLogs\Guild;
use App\Observers\GameVersionObserver;
use App\Policies\DatasetPolicy;
use Carbon\CarbonInterface;
use Database\Factories\GameVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

#[ObservedBy([GameVersionObserver::class])]
#[Fillable([
    'title',
    'slug',
    'realm',
    'guild_name',
    'uses_surnames',
    'faction',
    'release_date',
    'theme',
    'blizzard_namespace',
    'warcraft_logs_guild_id',
])]
#[UsePolicy(DatasetPolicy::class)]
class GameVersion extends Model implements DatasetModel, EditLockable
{
    use HasEditLock;

    /** @use HasFactory<GameVersionFactory> */
    use HasFactory;

    use TracksUsage;

    /**
     * Relationships whose existing rows mark this game version as in use.
     *
     * @return list<string>
     */
    public function usageRelations(): array
    {
        return ['phases', 'items', 'characters', 'guildRanks'];
    }

    // ============ Custom attributes and casts ===========

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slug' => AsSlug::class,
            'faction' => Faction::class,
            'release_date' => 'datetime',
            'uses_surnames' => 'boolean',
            'theme' => AsTheme::class,
            'blizzard_namespace' => BlizzardNamespace::class,
        ];
    }

    /**
     * @return Attribute<string>
     */
    protected function guildSlug(): Attribute
    {
        return Attribute::make(
            get: fn (): string => NameSlug::from($this->guild_name),
        );
    }

    /**
     * @return Attribute<string|null>
     */
    protected function realmSlug(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->realm === null ? null : Str::slug($this->realm),
        );
    }

    /**
     * Scope to versions released on or before the given moment (default: now).
     */
    #[Scope]
    protected function released(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->where('release_date', '<=', $at ?? now());
    }

    // ============ Guild roster ===========

    /**
     * Scope to versions whose guild roster Blizzard can return: released, with
     * a namespace, and with a realm where the namespace needs one.
     */
    #[Scope]
    protected function withFetchableRoster(Builder $query): void
    {
        $realmlessNamespaces = collect(BlizzardNamespace::cases())
            ->reject(fn (BlizzardNamespace $namespace): bool => $namespace->requiresRealm())
            ->all();

        $query->whereNotNull('blizzard_namespace')
            ->released()
            ->where(fn (Builder $query) => $query->whereNotNull('realm')
                ->orWhereIn('blizzard_namespace', $realmlessNamespaces));
    }

    /**
     * Get the versions that own a guild roster. Versions sharing a namespace,
     * realm and guild with a later release are deprecated and left out. The
     * dedupe runs in PHP because rosters are matched on slugs.
     *
     * @return Collection<int, static>
     */
    public static function currentRosters(): Collection
    {
        return static::query()
            ->withFetchableRoster()
            ->orderByDesc('release_date')
            ->get()
            ->unique(fn (GameVersion $gameVersion): string => $gameVersion->rosterKey());
    }

    /**
     * Get the version whose roster is shown by default: the most recently
     * released of the current rosters. The newest version always survives the
     * dedupe, so it is read straight from the query.
     */
    public static function defaultRoster(): ?static
    {
        return static::query()
            ->withFetchableRoster()
            ->orderByDesc('release_date')
            ->first();
    }

    /**
     * Determine whether this version owns a current guild roster.
     */
    public function ownsCurrentRoster(): bool
    {
        return static::currentRosters()->contains($this);
    }

    /**
     * Build the key that identifies this version's guild roster on Blizzard's side.
     */
    public function rosterKey(): string
    {
        return "{$this->blizzard_namespace?->value}|{$this->realm_slug}|{$this->guild_slug}";
    }

    // ============ Relationships ===========

    /**
     * @return BelongsTo<Guild, $this>
     */
    public function warcraftLogsGuild(): BelongsTo
    {
        return $this->belongsTo(Guild::class, 'warcraft_logs_guild_id');
    }

    /**
     * @return HasMany<Phase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
    }

    /**
     * @return HasManyThrough<Raid, Phase, $this>
     */
    public function raids(): HasManyThrough
    {
        return $this->hasManyThrough(Raid::class, Phase::class);
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return HasMany<Character, $this>
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * @return HasMany<GuildRank, $this>
     */
    public function guildRanks(): HasMany
    {
        return $this->hasMany(GuildRank::class);
    }

    /**
     * @return BelongsToMany<PlayableRace, $this>
     */
    public function playableRaces(): BelongsToMany
    {
        return $this->belongsToMany(PlayableRace::class, 'pivot_game_versions_playable_races')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<PlayableClass, $this>
     */
    public function playableClasses(): BelongsToMany
    {
        return $this->belongsToMany(PlayableClass::class, 'pivot_game_versions_playable_classes')
            ->withTimestamps();
    }
}
