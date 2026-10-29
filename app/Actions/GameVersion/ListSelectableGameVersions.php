<?php

namespace App\Actions\GameVersion;

use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Data\GameVersion\SelectableGameVersionsData;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
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

    public function __construct(
        protected Request $request,
    ) {}

    /**
     * @return Collection<int, GameVersion>
     */
    public function handle(): Collection
    {
        $attributes = $this->request->attributes;

        if (! $attributes->has(self::REQUEST_KEY)) {
            $cached = Cache::tags([self::CACHE_TAG])->remember(self::CACHE_KEY, now()->addWeek(), fn (): array => $this->query()->toArray());

            $attributes->set(self::REQUEST_KEY, $this->hydrate(SelectableGameVersionsData::from($cached)));
        }

        return $attributes->get(self::REQUEST_KEY);
    }

    /**
     * Fetch the raw attributes of every game version, of its media and of
     * its Warcraft Logs guild.
     */
    private function query(): SelectableGameVersionsData
    {
        return SelectableGameVersionsData::fromGameVersions(
            GameVersion::with(['media', 'warcraftLogsGuild'])
                ->orderByDesc('release_date')
                ->orderBy('id')
                ->get(),
        );
    }

    /**
     * Rebuild the game version models from their raw attributes, with each
     * version's media set as its `media` relation and its guild, or null,
     * set as its `warcraftLogsGuild` relation.
     *
     * @return Collection<int, GameVersion>
     */
    private function hydrate(SelectableGameVersionsData $rows): Collection
    {
        $mediaByModel = Media::hydrate($rows->media)->groupBy('model_id');
        $guilds = Guild::hydrate($rows->guilds)->keyBy('id');

        return GameVersion::hydrate($rows->versions)->each(function (GameVersion $gameVersion) use ($mediaByModel, $guilds): void {
            $gameVersion->setRelation('media', $mediaByModel
                ->get($gameVersion->getKey(), new Collection)
                ->each->setRelation('model', $gameVersion));
            $gameVersion->setRelation('warcraftLogsGuild', $guilds->get($gameVersion->warcraft_logs_guild_id));
        });
    }
}
