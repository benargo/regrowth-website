<?php

namespace App\Http\Controllers;

use App\Actions\Characters\MapCharacterPortraits;
use App\Actions\Characters\RefreshCharacterPortrait;
use App\Actions\Race\BuildRaceStandings;
use App\Contracts\HasCharacterMedia;
use App\Models\Character;
use App\Models\GameVersion;
use Inertia\Inertia;
use Inertia\Response;

class RaceController extends Controller
{
    /**
     * Render the Race to Level 60 page, queueing a portrait fetch for each
     * racer without one. The optional `character_portraits` prop maps each
     * racer's ID to their portrait URL, or null.
     */
    public function __invoke(): Response
    {
        $forever = GameVersion::forever();

        return Inertia::render('Race/Index', [
            'race' => fn (): ?array => $forever ? $this->standings($forever) : null,
            'character_portraits' => Inertia::optional(fn (): object => $forever ? MapCharacterPortraits::run($forever->characters()) : (object) []),
        ]);
    }

    /**
     * Build the race standings and queue a portrait fetch for every racer without a portrait.
     *
     * @return array<string, mixed>
     */
    private function standings(GameVersion $forever): array
    {
        $standings = BuildRaceStandings::run($forever);

        $standings['racers']->collection
            ->map(fn ($resource): Character => $resource->resource)
            ->filter(fn (Character $character): bool => $character->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION) === null)
            ->reject(fn (Character $character): bool => $character->isPortraitKnownMissing())
            ->each(fn (Character $character) => RefreshCharacterPortrait::dispatch($character->withoutRelations()));

        return $standings;
    }
}
