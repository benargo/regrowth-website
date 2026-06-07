<?php

namespace Database\Seeders;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\CharacterNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CharacterSeeder extends Seeder
{
    public function __construct(
        private BlizzardConnector $blizzard,
    ) {}

    public function run(): void
    {
        $characters = Character::whereNull('playable_class_id')->orWhereNull('playable_race_id')->get();

        $characters->each(function (Character $character) {
            try {
                $profile = $this->blizzard->send(new GetCharacterProfileRequest(
                    $this->blizzard->defaultRealmSlug(),
                    Str::lower($character->name),
                ))->dto();

                $playableClass = PlayableClass::find($profile->characterClass->id ?? null);
                $race = PlayableRace::find($profile->race->id ?? null);

                if ($race === null && isset($profile->race->id)) {
                    $this->command?->warn("  ⚠ PlayableRace ID {$profile->race->id} not found for character {$character->name}. Has PlayableRaceSeeder been run?");
                }

                $character->update([
                    'playable_class_id' => $playableClass?->id,
                    'playable_race_id' => $race?->id,
                ], ['touch' => false]);

                $this->command?->line("  <info>✓</info> [{$character->id}] {$character->name}");
            } catch (CharacterNotFoundException|BlizzardRequestException $e) {
                $this->command?->warn("  ⚠ Failed to fetch profile for character {$character->name}. Skipping. ({$e->getMessage()})");
            }
        });
    }
}
