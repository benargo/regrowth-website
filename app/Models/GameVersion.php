<?php

namespace App\Models;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use Database\Factories\GameVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameVersion extends Model
{
    /** @use HasFactory<GameVersionFactory> */
    use HasFactory;

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
            'blizzard_namespace' => BlizzardNamespace::class,
            'warcraftlogs_guild' => 'integer',
            'warcraftlogs_expansion' => 'integer',
        ];
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
