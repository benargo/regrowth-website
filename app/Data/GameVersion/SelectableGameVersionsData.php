<?php

namespace App\Data\GameVersion;

use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

final class SelectableGameVersionsData extends Data
{
    /**
     * @param  list<array<string, mixed>>  $versions
     * @param  list<array<string, mixed>>  $media
     * @param  list<array<string, mixed>>  $guilds
     */
    public function __construct(
        public readonly array $versions,
        public readonly array $media,
        public readonly array $guilds,
    ) {}

    /**
     * Describes the raw attributes of the given game versions, of their media
     * and of their distinct Warcraft Logs guilds.
     *
     * @param  Collection<int, GameVersion>  $gameVersions
     */
    public static function fromGameVersions(Collection $gameVersions): self
    {
        return new self(
            versions: $gameVersions->map->getAttributes()->all(),
            media: $gameVersions->flatMap->media->map->getAttributes()->values()->all(),
            guilds: $gameVersions->pluck('warcraftLogsGuild')->filter()->unique('id')->map->getAttributes()->values()->all(),
        );
    }
}
