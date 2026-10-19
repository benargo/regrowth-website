<?php

namespace App\Actions\GameVersion;

use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lists every game version, newest release first, with media and the
 * Warcraft Logs guild loaded.
 * The raw rows are cached for a week under the `game-versions` tag, and
 * the hydrated result is memoised for the duration of the request.
 */
class ListSelectableGameVersions implements CachesSelectableGameVersions
{
    use AsAction;

    private const string CACHE_KEY = 'game-versions:selectable-with-guilds';

    /**
     * @return Collection<int, GameVersion>
     */
    public function handle(): Collection
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::REQUEST_KEY)) {
            $attributes->set(self::REQUEST_KEY, $this->hydrate(
                Cache::tags([self::CACHE_TAG])->remember(self::CACHE_KEY, now()->addWeek(), fn (): array => $this->query()),
            ));
        }

        return $attributes->get(self::REQUEST_KEY);
    }

    /**
     * Fetch the raw attributes of every game version, of its media and of
     * its Warcraft Logs guild.
     *
     * @return array{versions: list<array<string, mixed>>, media: list<array<string, mixed>>, guilds: list<array<string, mixed>>}
     */
    private function query(): array
    {
        $gameVersions = GameVersion::with(['media', 'warcraftLogsGuild'])
            ->orderByDesc('release_date')
            ->orderBy('id')
            ->get();

        return [
            'versions' => $gameVersions->map->getAttributes()->all(),
            'media' => $gameVersions->flatMap->media->map->getAttributes()->values()->all(),
            'guilds' => $gameVersions->pluck('warcraftLogsGuild')->filter()->unique('id')->map->getAttributes()->values()->all(),
        ];
    }

    /**
     * Rebuild the game version models from their raw attributes, with each
     * version's media set as its `media` relation and its guild, or null,
     * set as its `warcraftLogsGuild` relation.
     *
     * @param  array{versions: list<array<string, mixed>>, media: list<array<string, mixed>>, guilds: list<array<string, mixed>>}  $rows
     * @return Collection<int, GameVersion>
     */
    private function hydrate(array $rows): Collection
    {
        $media = Media::hydrate($rows['media']);
        $guilds = Guild::hydrate($rows['guilds'])->keyBy('id');

        return GameVersion::hydrate($rows['versions'])->each(function (GameVersion $gameVersion) use ($media, $guilds): void {
            $gameVersion->setRelation('media', $media
                ->where('model_id', $gameVersion->getKey())
                ->each->setRelation('model', $gameVersion)
                ->values());
            $gameVersion->setRelation('warcraftLogsGuild', $guilds->get($gameVersion->warcraft_logs_guild_id));
        });
    }
}
