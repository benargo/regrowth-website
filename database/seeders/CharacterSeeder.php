<?php

namespace Database\Seeders;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\CharacterNotFoundException;
use App\Http\Integrations\Blizzard\Exceptions\InvalidRaceException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Services\Blizzard\Exceptions\BlizzardRequestException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CharacterSeeder extends Seeder
{
    public function __construct(
        private BlizzardConnector $blizzard,
    ) {}

    public function run(): void
    {
        $characters = Character::whereNull('playable_class_id')->orWhereNull('playable_race')->get();

        $characters->each(function (Character $character) {
            try {
                $profile = $this->blizzard->send(new GetCharacterProfileRequest(
                    $this->blizzard->defaultRealmSlug(),
                    Str::lower($character->name),
                ))->dto();

                $playableClass = PlayableClass::find($profile->characterClass->id ?? null);
                $raceId = $profile->race->id ?? null;

                $character->update([
                    'playable_class_id' => $playableClass?->id,
                    'playable_race' => $raceId !== null
                        ? $this->blizzard->send(new GetPlayableRaceRequest($raceId))->dto()
                        : null,
                ], ['touch' => false]);
            } catch (CharacterNotFoundException|InvalidRaceException|BlizzardRequestException $e) {
                Log::warning("Failed to fetch profile for character {$character->name}. Skipping.", ['error' => $e->getMessage()]);
            }
        });
    }
}
