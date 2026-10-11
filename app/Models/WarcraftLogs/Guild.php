<?php

namespace App\Models\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\GameVersion;
use App\Models\Report;
use App\Policies\WarcraftLogsGuildPolicy;
use Database\Factories\WarcraftLogs\GuildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

#[UsePolicy(WarcraftLogsGuildPolicy::class)]
#[Fillable(['id', 'namespace'])]
#[Table('warcraft_logs_guilds', incrementing: false)]
class Guild extends Model
{
    /** @use HasFactory<GuildFactory> */
    use HasFactory;

    /**
     * Flush cached API responses when a guild is added or changes namespace.
     */
    protected static function booted(): void
    {
        static::saved(function (Guild $guild): void {
            if (! $guild->isDirty('namespace')) {
                return;
            }

            Cache::tags(['warcraftlogs-api-response'])->flush();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'namespace' => WarcraftLogsNamespace::class,
        ];
    }

    // ============ Relationships ===========

    /**
     * @return HasMany<GameVersion, $this>
     */
    public function gameVersions(): HasMany
    {
        return $this->hasMany(GameVersion::class, 'warcraft_logs_guild_id');
    }

    /**
     * The latest of the guild's game versions released by now.
     *
     * @return HasOne<GameVersion, $this>
     */
    public function currentGameVersion(): HasOne
    {
        return $this->hasOne(GameVersion::class, 'warcraft_logs_guild_id')
            ->ofMany(['release_date' => 'max'], fn (Builder $query) => $query->released());
    }

    /**
     * @return HasMany<GuildTag, $this>
     */
    public function guildTags(): HasMany
    {
        return $this->hasMany(GuildTag::class, 'warcraft_logs_guild_id');
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'warcraft_logs_guild_id');
    }
}
