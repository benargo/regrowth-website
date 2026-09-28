<?php

namespace App\Models;

use App\Casts\AsTheme;
use App\Contracts\Models\DatasetModel;
use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\WarcraftLogs\GuildTag;
use App\Policies\DatasetPolicy;
use Database\Factories\GameVersionFactory;
use Illuminate\Cache\Lock;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'realm',
    'guild_name',
    'faction',
    'release_date',
    'theme',
    'blizzard_namespace',
    'warcraftlogs_guild',
    'warcraftlogs_namespace',
])]
#[UsePolicy(DatasetPolicy::class)]
class GameVersion extends Model implements DatasetModel
{
    /** @use HasFactory<GameVersionFactory> */
    use HasFactory;

    /**
     * Relationships whose existing rows mark this game version as in use.
     *
     * @var list<string>
     */
    public const array USAGE_RELATIONS = ['phases', 'items', 'characters'];

    /**
     * How long an officer keeps the edit lock after their last active visit or poll.
     */
    public const int EDIT_LOCK_SECONDS = 300;

    // ============ Custom attributes and casts ===========

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'faction' => Faction::class,
            'release_date' => 'datetime',
            'theme' => AsTheme::class,
            'blizzard_namespace' => BlizzardNamespace::class,
            'warcraftlogs_guild' => 'integer',
            'warcraftlogs_namespace' => WarcraftLogsNamespace::class,
        ];
    }

    /**
     * @return Attribute<string>
     */
    protected function guildSlug(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Str::slug($this->guild_name),
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

    // ============ Editing lock ===========

    /**
     * Determine whether any dataset record still references this game version.
     */
    public function isInUse(): bool
    {
        return collect(self::USAGE_RELATIONS)
            ->contains(fn (string $relation): bool => $this->{$relation}()->exists());
    }

    /**
     * The atomic lock that gives one officer at a time the right to edit this game version.
     */
    public function editLock(User $user): Lock
    {
        return Cache::lock($this->editLockKey('editing'), self::EDIT_LOCK_SECONDS, (string) $user->id);
    }

    /**
     * Take or extend the edit lock for the user, returning whether they hold it.
     */
    public function acquireEditLock(User $user): bool
    {
        $lock = $this->editLock($user);

        if (! $lock->get() && ! $lock->refresh()) {
            return false;
        }

        Cache::put($this->editLockKey('editor'), $user->id, self::EDIT_LOCK_SECONDS);

        return true;
    }

    /**
     * Determine whether an officer other than the given user holds the edit lock.
     */
    public function isLockedForEditingBy(User $user): bool
    {
        $lock = $this->editLock($user);

        return $lock->isLocked() && ! $lock->isOwnedByCurrentProcess();
    }

    /**
     * The officer who last took or refreshed the edit lock, for display only.
     */
    public function editor(): ?User
    {
        return User::find(Cache::get($this->editLockKey('editor')));
    }

    /**
     * Build a cache key scoped to this game version's edit lock.
     */
    private function editLockKey(string $suffix): string
    {
        return "game-versions.{$this->id}.{$suffix}";
    }

    // ============ Relationships ===========

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
     * @return HasManyThrough<GuildTag, Phase, $this>
     */
    public function guildTags(): HasManyThrough
    {
        return $this->hasManyThrough(GuildTag::class, Phase::class);
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
