<?php

namespace App\Actions\Raid;

use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * List the given game version's phases that have raids, in release order,
 * each with its raids (and their media) ordered by name.
 * The raw rows are cached for a week under the `raiding` tag.
 */
class ListPhasesWithRaids
{
    use AsAction;

    /**
     * @return Collection<int, Phase>
     */
    public function handle(GameVersion $gameVersion): Collection
    {
        return $this->hydrate(Cache::tags(['raiding'])->remember(
            "raids:phases-with-raids:{$gameVersion->id}",
            now()->addWeek(),
            fn (): array => $this->query($gameVersion),
        ));
    }

    /**
     * Fetch the raw attributes of the game version's raids, of their media
     * and of the phases they belong to.
     *
     * @return array{phases: list<array<string, mixed>>, raids: list<array<string, mixed>>, media: list<array<string, mixed>>}
     */
    private function query(GameVersion $gameVersion): array
    {
        $raids = Raid::inGameVersion($gameVersion)->with('media')->orderBy('name')->get();

        $phases = Phase::whereKey($raids->pluck('phase_id')->unique())
            ->inReleaseOrder()
            ->get();

        return [
            'phases' => $phases->map->getAttributes()->all(),
            'raids' => $raids->map->getAttributes()->all(),
            'media' => $raids->flatMap->media->map->getAttributes()->values()->all(),
        ];
    }

    /**
     * Rebuild the phase models from their raw attributes, with each phase's
     * raids set as its `raids` relation and each raid's media set as its
     * `media` relation.
     *
     * @param  array{phases: list<array<string, mixed>>, raids: list<array<string, mixed>>, media: list<array<string, mixed>>}  $rows
     * @return Collection<int, Phase>
     */
    private function hydrate(array $rows): Collection
    {
        $media = Media::hydrate($rows['media']);

        $raids = Raid::hydrate($rows['raids'])->each(function (Raid $raid) use ($media): void {
            $raid->setRelation('media', $media
                ->where('model_id', $raid->getKey())
                ->each->setRelation('model', $raid)
                ->values());
        });

        return Phase::hydrate($rows['phases'])
            ->each(fn (Phase $phase) => $phase->setRelation('raids', $raids->where('phase_id', $phase->id)->values()));
    }
}
