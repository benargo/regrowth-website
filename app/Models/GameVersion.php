<?php

namespace App\Models;

use App\Casts\AsTheme;
use App\Contracts\Models\DatasetModel;
use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Policies\DatasetPolicy;
use Database\Factories\GameVersionFactory;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'title',
    'realm',
    'faction',
    'release_date',
    'theme',
    'blizzard_namespace',
    'warcraftlogs_guild',
    'warcraftlogs_expansion',
])]
#[UsePolicy(DatasetPolicy::class)]
class GameVersion extends Model implements DatasetModel
{
    /** @use HasFactory<GameVersionFactory> */
    use HasFactory;

    /**
     * Relationships whose rows reference this game version by a nullable
     * foreign key. Deleting the game version would silently orphan them,
     * so any existing row marks the version as in use.
     *
     * @var list<string>
     */
    public const array USAGE_RELATIONS = ['bosses', 'phases', 'raids', 'guildTags', 'zones', 'items', 'characters'];

    /**
     * How long an officer keeps the edit lock after their last active visit or poll.
     */
    public const int EDIT_LOCK_SECONDS = 300;

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
            'warcraftlogs_expansion' => 'integer',
        ];
    }

    /**
     * Determine whether any dataset record still references this game version.
     */
    public function isInUse(): bool
    {
        return collect(self::USAGE_RELATIONS)
            ->contains(fn (string $relation): bool => $this->{$relation}()->exists());
    }

    /**
     * The atomic lock that gives one officer at a time the right to edit this
     * game version. The user's id is the owner, so their own requests can
     * refresh it and other officers' requests see it as taken.
     */
    public function editLock(User $user): Lock
    {
        return Cache::lock($this->editLockKey('editing'), self::EDIT_LOCK_SECONDS, (string) $user->id);
    }

    /**
     * Take or extend the edit lock for the user. Returns whether they hold it.
     * The holder's id is kept alongside it, because a Lock can't report who owns it.
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
     * Get the bosses for this game version.
     *
     * @return HasMany<Boss, $this>
     */
    public function bosses(): HasMany
    {
        return $this->hasMany(Boss::class);
    }

    /**
     * Get the phases for this game version.
     *
     * @return HasMany<Phase, $this>
     */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class);
    }

    /**
     * Get the raids for this game version.
     *
     * @return HasMany<Raid, $this>
     */
    public function raids(): HasMany
    {
        return $this->hasMany(Raid::class);
    }

    /**
     * Get the guild tags for this game version.
     *
     * @return HasMany<GuildTag, $this>
     */
    public function guildTags(): HasMany
    {
        return $this->hasMany(GuildTag::class);
    }

    /**
     * Get the zones for this game version.
     *
     * @return HasMany<Zone, $this>
     */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    /**
     * Get the items for this game version.
     *
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Get the characters for this game version.
     *
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
