<?php

namespace Database\Seeders;

use App\Contracts\HasBlizzardIcons;
use App\Enums\PlayableSpecRole;
use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use Illuminate\Database\Seeder;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;

class SpecializationSeeder extends Seeder implements HasBlizzardIcons
{
    public function __construct(
        private RenderConnector $renderConnector,
    ) {}

    /**
     * @var array<string, array<int, array{name: string, role: PPlayableSpecRole, icon: string}>>
     */
    private array $specializations = [
        'Druid' => [
            ['name' => 'Balance', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_nature_starfall'],
            ['name' => 'Feral', 'role' => PlayableSpecRole::tank, 'icon_name' => 'ability_racial_bearform'],
            ['name' => 'Feral', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_druid_catform'],
            ['name' => 'Restoration', 'role' => PlayableSpecRole::healer, 'icon_name' => 'spell_nature_healingtouch'],
        ],
        'Hunter' => [
            ['name' => 'Beast Mastery', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_hunter_beasttaming'],
            ['name' => 'Marksmanship', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_marksmanship'],
            ['name' => 'Survival', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_hunter_swiftstrike'],
        ],
        'Mage' => [
            ['name' => 'Arcane', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_arcane_blast'],
            ['name' => 'Fire', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_fire_flamebolt'],
            ['name' => 'Frost', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_frost_frostbolt02'],
        ],
        'Paladin' => [
            ['name' => 'Holy', 'role' => PlayableSpecRole::healer, 'icon_name' => 'spell_holy_holybolt'],
            ['name' => 'Protection', 'role' => PlayableSpecRole::tank, 'icon_name' => 'spell_holy_devotionaura'],
            ['name' => 'Retribution', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_holy_auraoflight'],
        ],
        'Priest' => [
            ['name' => 'Discipline', 'role' => PlayableSpecRole::healer, 'icon_name' => 'spell_holy_powerwordshield'],
            ['name' => 'Holy', 'role' => PlayableSpecRole::healer, 'icon_name' => 'spell_holy_guardianspirit'],
            ['name' => 'Shadow', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_shadow_shadowwordpain'],
        ],
        'Rogue' => [
            ['name' => 'Assassination', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_rogue_eviscerate'],
            ['name' => 'Combat', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_backstab'],
            ['name' => 'Subtlety', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_stealth'],
        ],
        'Shaman' => [
            ['name' => 'Elemental', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_nature_lightning'],
            ['name' => 'Enhancement', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_nature_lightningshield'],
            ['name' => 'Restoration', 'role' => PlayableSpecRole::healer, 'icon_name' => 'spell_nature_magicimmunity'],
        ],
        'Warlock' => [
            ['name' => 'Affliction', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_shadow_deathcoil'],
            ['name' => 'Demonology', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_shadow_metamorphosis'],
            ['name' => 'Destruction', 'role' => PlayableSpecRole::damage, 'icon_name' => 'spell_shadow_rainoffire'],
        ],
        'Warrior' => [
            ['name' => 'Arms', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_warrior_savageblow'],
            ['name' => 'Fury', 'role' => PlayableSpecRole::damage, 'icon_name' => 'ability_warrior_innerrage'],
            ['name' => 'Protection', 'role' => PlayableSpecRole::tank, 'icon_name' => 'ability_warrior_defensivestance'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $keys = PlayableClass::select('id', 'name')->orderBy('name')->get()->keyBy('name');

        foreach ($this->specializations as $className => $specs) {
            if (! $keys->has($className)) {
                $this->command?->warn("Playable class [{$className}] not found — skipping its specializations");

                continue;
            }

            $playableClass = $keys->get($className);

            foreach ($specs as $spec) {
                $model = PlayableSpecialization::updateOrCreate(
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
                $iconFileName = $iconName.'.'.self::DEFAULT_MEDIA_FILE_EXTENSION;

                try {
                    $response = $this->renderConnector->send(new FetchIconRequest($iconName));

                    $model->addMediaFromString($response->body())
                        ->usingFileName($iconFileName)
                        ->withCustomProperties(['size' => self::DEFAULT_MEDIA_SIZE])
                        ->toMediaCollection('blizzard_icons');
                } catch (ForbiddenException $e) {
                    AttachBlizzardIconToModel::dispatch(PlayableSpecialization::class, $model->id, $e->getPendingRequest()->getUrl())
                        ->delay(now()->addMinutes(5));
                    $this->command?->warn("  ⚠ [{$model->title}] Icon deferred (403) — retrying in 5 min");
                } catch (MediaNotFoundException|RequestException $e) {
                    $this->command?->warn("  ⚠ [{$model->title}] Icon skipped — {$e->getMessage()}");
                }
            }
        }
    }
}
