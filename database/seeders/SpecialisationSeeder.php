<?php

namespace Database\Seeders;

use App\Enums\CharacterRole;
use App\Models\CharacterSpecialisation;
use App\Models\PlayableClass;
use App\Services\Blizzard\MediaService;
use Illuminate\Database\Seeder;

class SpecialisationSeeder extends Seeder
{
    public function __construct(
        /**
         * Inject the MediaService to fetch specialisation icons from the Blizzard API.
         */
        private MediaService $mediaService
    ) {}

    /**
     * @var array<string, array<int, array{name: string, role: CharacterRole, icon: string}>>
     */
    private array $specialisations = [
        'Druid' => [
            ['name' => 'Balance', 'role' => CharacterRole::damage, 'icon' => 'spell_nature_starfall'],
            ['name' => 'Feral', 'role' => CharacterRole::tank, 'icon' => 'ability_racial_bearform'],
            ['name' => 'Feral', 'role' => CharacterRole::damage, 'icon' => 'ability_druid_catform'],
            ['name' => 'Restoration', 'role' => CharacterRole::healer, 'icon' => 'spell_nature_healingtouch'],
        ],
        'Hunter' => [
            ['name' => 'Beast Mastery', 'role' => CharacterRole::damage, 'icon' => 'ability_hunter_beasttaming'],
            ['name' => 'Marksmanship', 'role' => CharacterRole::damage, 'icon' => 'ability_marksmanship'],
            ['name' => 'Survival', 'role' => CharacterRole::damage, 'icon' => 'ability_hunter_swiftstrike'],
        ],
        'Mage' => [
            ['name' => 'Arcane', 'role' => CharacterRole::damage, 'icon' => 'spell_arcane_blast'],
            ['name' => 'Fire', 'role' => CharacterRole::damage, 'icon' => 'spell_fire_flamebolt'],
            ['name' => 'Frost', 'role' => CharacterRole::damage, 'icon' => 'spell_frost_frostbolt02'],
        ],
        'Paladin' => [
            ['name' => 'Holy', 'role' => CharacterRole::healer, 'icon' => 'spell_holy_holybolt'],
            ['name' => 'Protection', 'role' => CharacterRole::tank, 'icon' => 'spell_holy_devotionaura'],
            ['name' => 'Retribution', 'role' => CharacterRole::damage, 'icon' => 'spell_holy_auraoflight'],
        ],
        'Priest' => [
            ['name' => 'Discipline', 'role' => CharacterRole::healer, 'icon' => 'spell_holy_powerwordshield'],
            ['name' => 'Holy', 'role' => CharacterRole::healer, 'icon' => 'spell_holy_guardianspirit'],
            ['name' => 'Shadow', 'role' => CharacterRole::damage, 'icon' => 'spell_shadow_shadowwordpain'],
        ],
        'Rogue' => [
            ['name' => 'Assassination', 'role' => CharacterRole::damage, 'icon' => 'ability_rogue_eviscerate'],
            ['name' => 'Combat', 'role' => CharacterRole::damage, 'icon' => 'ability_backstab'],
            ['name' => 'Subtlety', 'role' => CharacterRole::damage, 'icon' => 'ability_stealth'],
        ],
        'Shaman' => [
            ['name' => 'Elemental', 'role' => CharacterRole::damage, 'icon' => 'spell_nature_lightning'],
            ['name' => 'Enhancement', 'role' => CharacterRole::damage, 'icon' => 'spell_nature_lightningshield'],
            ['name' => 'Restoration', 'role' => CharacterRole::healer, 'icon' => 'spell_nature_magicimmunity'],
        ],
        'Warlock' => [
            ['name' => 'Affliction', 'role' => CharacterRole::damage, 'icon' => 'spell_shadow_deathcoil'],
            ['name' => 'Demonology', 'role' => CharacterRole::damage, 'icon' => 'spell_shadow_metamorphosis'],
            ['name' => 'Destruction', 'role' => CharacterRole::damage, 'icon' => 'spell_shadow_rainoffire'],
        ],
        'Warrior' => [
            ['name' => 'Arms', 'role' => CharacterRole::damage, 'icon' => 'ability_warrior_savageblow'],
            ['name' => 'Fury', 'role' => CharacterRole::damage, 'icon' => 'ability_warrior_innerrage'],
            ['name' => 'Protection', 'role' => CharacterRole::tank, 'icon' => 'ability_warrior_defensivestance'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure PlayableClasses are seeded first so we can reference them when creating specialisations.
        $this->call([
            PlayableClassSeeder::class,
        ]);

        $keys = PlayableClass::select('id', 'name')->orderBy('name')->get()->keyBy('name');

        foreach ($this->specialisations as $className => $specs) {
            assert($keys->has($className), "PlayableClass '{$className}' not found in the database.");

            $playableClass = $keys->get($className);

            foreach ($specs as $spec) {
                $model = CharacterSpecialisation::updateOrCreate(
                    [
                        'playable_class_id' => $playableClass->id,
                        'name' => $spec['name'],
                    ],
                    [
                        'role' => $spec['role'],
                    ]
                );

                $iconUrl = $this->mediaService->get($spec['icon']);

                if ($iconUrl !== null) {
                    $model->clearMediaCollection('blizzard_icons');
                    $model->addMediaFromUrl($iconUrl)->toMediaCollection('blizzard_icons');
                }
            }
        }
    }
}
