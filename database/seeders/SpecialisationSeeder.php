<?php

namespace Database\Seeders;

use App\Contracts\HasBlizzardIcons;
use App\Enums\CharacterRole;
use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\CharacterSpecialisation;
use App\Models\PlayableClass;
use Illuminate\Database\Seeder;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;

class SpecialisationSeeder extends Seeder implements HasBlizzardIcons
{
    public function __construct(
        private RenderConnector $renderConnector,
    ) {}

    /**
     * @var array<string, array<int, array{name: string, role: CharacterRole, icon: string}>>
     */
    private array $specialisations = [
        'Druid' => [
            ['name' => 'Balance', 'role' => CharacterRole::damage, 'icon_name' => 'spell_nature_starfall'],
            ['name' => 'Feral', 'role' => CharacterRole::tank, 'icon_name' => 'ability_racial_bearform'],
            ['name' => 'Feral', 'role' => CharacterRole::damage, 'icon_name' => 'ability_druid_catform'],
            ['name' => 'Restoration', 'role' => CharacterRole::healer, 'icon_name' => 'spell_nature_healingtouch'],
        ],
        'Hunter' => [
            ['name' => 'Beast Mastery', 'role' => CharacterRole::damage, 'icon_name' => 'ability_hunter_beasttaming'],
            ['name' => 'Marksmanship', 'role' => CharacterRole::damage, 'icon_name' => 'ability_marksmanship'],
            ['name' => 'Survival', 'role' => CharacterRole::damage, 'icon_name' => 'ability_hunter_swiftstrike'],
        ],
        'Mage' => [
            ['name' => 'Arcane', 'role' => CharacterRole::damage, 'icon_name' => 'spell_arcane_blast'],
            ['name' => 'Fire', 'role' => CharacterRole::damage, 'icon_name' => 'spell_fire_flamebolt'],
            ['name' => 'Frost', 'role' => CharacterRole::damage, 'icon_name' => 'spell_frost_frostbolt02'],
        ],
        'Paladin' => [
            ['name' => 'Holy', 'role' => CharacterRole::healer, 'icon_name' => 'spell_holy_holybolt'],
            ['name' => 'Protection', 'role' => CharacterRole::tank, 'icon_name' => 'spell_holy_devotionaura'],
            ['name' => 'Retribution', 'role' => CharacterRole::damage, 'icon_name' => 'spell_holy_auraoflight'],
        ],
        'Priest' => [
            ['name' => 'Discipline', 'role' => CharacterRole::healer, 'icon_name' => 'spell_holy_powerwordshield'],
            ['name' => 'Holy', 'role' => CharacterRole::healer, 'icon_name' => 'spell_holy_guardianspirit'],
            ['name' => 'Shadow', 'role' => CharacterRole::damage, 'icon_name' => 'spell_shadow_shadowwordpain'],
        ],
        'Rogue' => [
            ['name' => 'Assassination', 'role' => CharacterRole::damage, 'icon_name' => 'ability_rogue_eviscerate'],
            ['name' => 'Combat', 'role' => CharacterRole::damage, 'icon_name' => 'ability_backstab'],
            ['name' => 'Subtlety', 'role' => CharacterRole::damage, 'icon_name' => 'ability_stealth'],
        ],
        'Shaman' => [
            ['name' => 'Elemental', 'role' => CharacterRole::damage, 'icon_name' => 'spell_nature_lightning'],
            ['name' => 'Enhancement', 'role' => CharacterRole::damage, 'icon_name' => 'spell_nature_lightningshield'],
            ['name' => 'Restoration', 'role' => CharacterRole::healer, 'icon_name' => 'spell_nature_magicimmunity'],
        ],
        'Warlock' => [
            ['name' => 'Affliction', 'role' => CharacterRole::damage, 'icon_name' => 'spell_shadow_deathcoil'],
            ['name' => 'Demonology', 'role' => CharacterRole::damage, 'icon_name' => 'spell_shadow_metamorphosis'],
            ['name' => 'Destruction', 'role' => CharacterRole::damage, 'icon_name' => 'spell_shadow_rainoffire'],
        ],
        'Warrior' => [
            ['name' => 'Arms', 'role' => CharacterRole::damage, 'icon_name' => 'ability_warrior_savageblow'],
            ['name' => 'Fury', 'role' => CharacterRole::damage, 'icon_name' => 'ability_warrior_innerrage'],
            ['name' => 'Protection', 'role' => CharacterRole::tank, 'icon_name' => 'ability_warrior_defensivestance'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $keys = PlayableClass::select('id', 'name')->orderBy('name')->get()->keyBy('name');

        foreach ($this->specialisations as $className => $specs) {
            if (! $keys->has($className)) {
                $this->command?->warn("Playable class [{$className}] not found — skipping its specialisations");

                continue;
            }

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

                if ($model->hasMedia('blizzard_icons')) {
                    continue;
                }

                $iconName = $spec['icon_name'];
                $iconFileName = $iconName.'.'.self::BLIZZARD_ICON_FILE_EXTENSION;

                try {
                    $response = $this->renderConnector->send(new FetchAssetRequest($iconName));

                    $model->addMediaFromString($response->body())
                        ->usingFileName($iconFileName)
                        ->withCustomProperties(['size' => self::BLIZZARD_ICON_SIZE])
                        ->toMediaCollection('blizzard_icons');
                } catch (ForbiddenException $e) {
                    AttachBlizzardIconToModel::dispatch(CharacterSpecialisation::class, $model->id, $e->getPendingRequest()->getUrl())
                        ->delay(now()->addMinutes(5));
                    $this->command?->warn("  ⚠ [{$model->title}] Icon deferred (403) — retrying in 5 min");
                } catch (MediaNotFoundException|RequestException $e) {
                    $this->command?->warn("  ⚠ [{$model->title}] Icon skipped — {$e->getMessage()}");
                }
            }
        }
    }
}
