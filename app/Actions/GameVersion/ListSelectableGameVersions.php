<?php

namespace App\Actions\GameVersion;

use App\Contracts\Actions\GameVersion\CachesSelectableGameVersions;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lists every game version, newest release first, with media loaded.
 * The raw rows are cached for a week under the `game-versions` tag, and
 * the hydrated result is memoised for the duration of the request.
 */
class ListSelectableGameVersions implements CachesSelectableGameVersions
{
    use AsAction;

    private const string CACHE_KEY = 'game-versions:selectable';

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
     * Fetch the raw attributes of every game version and of its media.
     *
     * @return array{versions: list<array<string, mixed>>, media: list<array<string, mixed>>}
     */
    private function query(): array
    {
        $gameVersions = GameVersion::with('media')
            ->orderByDesc('release_date')
            ->orderBy('id')
            ->get();

        return [
            'versions' => $gameVersions->map->getAttributes()->all(),
            'media' => $gameVersions->flatMap->media->map->getAttributes()->values()->all(),
        ];
    }

    /**
     * Rebuild the game version models from their raw attributes, with each
     * version's media set as its `media` relation.
     *
     * @param  array{versions: list<array<string, mixed>>, media: list<array<string, mixed>>}  $rows
     * @return Collection<int, GameVersion>
     */
    private function hydrate(array $rows): Collection
    {
        $media = Media::hydrate($rows['media']);

        return GameVersion::hydrate($rows['versions'])->each(function (GameVersion $gameVersion) use ($media): void {
            $gameVersion->setRelation('media', $media
                ->where('model_id', $gameVersion->getKey())
                ->each->setRelation('model', $gameVersion)
                ->values());
        });
    }
}
