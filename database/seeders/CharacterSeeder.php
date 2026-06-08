<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\CharacterNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
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
        $characters = Character::where(function ($query) {
            $query->whereNull('playable_class_id')
                ->orWhereNull('playable_race_id')
                ->orWhereNull('gender');
        })->get();

        $characters->each(function (Character $character) {
            try {
                $profile = $this->blizzard->send(new GetCharacterProfileRequest(
                    $this->blizzard->defaultRealmSlug(),
                    Str::lower($character->name),
                ))->dto();

                $playableClass = PlayableClass::find($profile->characterClass->id ?? null);
                $race = PlayableRace::find($profile->race->id ?? null);

                if ($race === null && isset($profile->race->id)) {
                    Log::warning("PlayableRace ID {$profile->race->id} not found for character {$character->name}. Has PlayableRaceSeeder been run?");
                }

                $character->update([
                    'playable_class_id' => $playableClass?->id,
                    'playable_race_id' => $race?->id,
                    'gender' => Gender::tryFrom($profile->gender['name']),
                ], ['touch' => false]);
            } catch (CharacterNotFoundException|BlizzardRequestException $e) {
                Log::warning("Failed to fetch profile for character {$character->name}. Skipping.", ['error' => $e->getMessage()]);
            }
        });
    }
}
