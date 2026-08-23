<?php

namespace Database\Seeders;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\LootPriority;
use Illuminate\Database\Seeder;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\ForbiddenException;

class PrioritySeeder extends Seeder implements HasBlizzardIcons
{
    public function __construct(
        private RenderConnector $renderConnector,
    ) {}

    /**
     * @var array<int, array{type: string, title: string, icon_name: string}>
     */
    protected static array $priorities = [
        // Roles
        ['type' => 'Role', 'title' => 'Tank', 'icon_name' => 'inv_shield_04'],
        ['type' => 'Role', 'title' => 'Backup tank', 'icon_name' => 'inv_shield_09'],
        ['type' => 'Role', 'title' => 'Healer', 'icon_name' => 'spell_holy_heal'],
        ['type' => 'Role', 'title' => 'Melee DPS', 'icon_name' => 'inv_sword_04'],
        ['type' => 'Role', 'title' => 'Caster DPS', 'icon_name' => 'spell_nature_elementalprecision_1'],

        // Classes
        ['type' => 'Class', 'title' => 'Druid', 'playable_class_id' => 11, 'icon_name' => 'classicon_druid'],
        ['type' => 'Class', 'title' => 'Hunter', 'playable_class_id' => 3, 'icon_name' => 'classicon_hunter'],
        ['type' => 'Class', 'title' => 'Mage', 'playable_class_id' => 8, 'icon_name' => 'classicon_mage'],
        ['type' => 'Class', 'title' => 'Paladin', 'playable_class_id' => 2, 'icon_name' => 'classicon_paladin'],
        ['type' => 'Class', 'title' => 'Priest', 'playable_class_id' => 5, 'icon_name' => 'classicon_priest'],
        ['type' => 'Class', 'title' => 'Rogue', 'playable_class_id' => 4, 'icon_name' => 'classicon_rogue'],
        ['type' => 'Class', 'title' => 'Shaman', 'playable_class_id' => 7, 'icon_name' => 'classicon_shaman'],
        ['type' => 'Class', 'title' => 'Warlock', 'playable_class_id' => 9, 'icon_name' => 'classicon_warlock'],
        ['type' => 'Class', 'title' => 'Warrior', 'playable_class_id' => 1, 'icon_name' => 'classicon_warrior'],

        // Specs - Druid
        ['type' => 'Spec', 'title' => 'Balance Druid', 'playable_class_id' => 11, 'icon_name' => 'spell_nature_starfall'],
        ['type' => 'Spec', 'title' => 'Feral DPS Druid', 'playable_class_id' => 11, 'icon_name' => 'ability_druid_catform'],
        ['type' => 'Spec', 'title' => 'Feral Tank Druid', 'playable_class_id' => 11, 'icon_name' => 'ability_racial_bearform'],
        ['type' => 'Spec', 'title' => 'Restoration Druid', 'playable_class_id' => 11, 'icon_name' => 'spell_nature_healingtouch'],

        // Specs - Hunter
        ['type' => 'Spec', 'title' => 'Beast Mastery Hunter', 'playable_class_id' => 3, 'icon_name' => 'ability_hunter_beasttaming'],
        ['type' => 'Spec', 'title' => 'Marksmanship Hunter', 'playable_class_id' => 3, 'icon_name' => 'ability_marksmanship'],
        ['type' => 'Spec', 'title' => 'Survival Hunter', 'playable_class_id' => 3, 'icon_name' => 'ability_hunter_swiftstrike'],

        // Specs - Mage
        ['type' => 'Spec', 'title' => 'Arcane Mage', 'playable_class_id' => 8, 'icon_name' => 'spell_arcane_blast'],
        ['type' => 'Spec', 'title' => 'Fire Mage', 'playable_class_id' => 8, 'icon_name' => 'spell_fire_flamebolt'],
        ['type' => 'Spec', 'title' => 'Frost Mage', 'playable_class_id' => 8, 'icon_name' => 'spell_frost_frostbolt02'],

        // Specs - Paladin
        ['type' => 'Spec', 'title' => 'Holy Paladin', 'playable_class_id' => 2, 'icon_name' => 'spell_holy_holybolt'],
        ['type' => 'Spec', 'title' => 'Protection Paladin', 'playable_class_id' => 2, 'icon_name' => 'spell_holy_devotionaura'],
        ['type' => 'Spec', 'title' => 'Retribution Paladin', 'playable_class_id' => 2, 'icon_name' => 'spell_holy_auraoflight'],

        // Specs - Priest
        ['type' => 'Spec', 'title' => 'Discipline Priest', 'playable_class_id' => 5, 'icon_name' => 'spell_holy_powerwordshield'],
        ['type' => 'Spec', 'title' => 'Holy Priest', 'playable_class_id' => 5, 'icon_name' => 'spell_holy_guardianspirit'],
        ['type' => 'Spec', 'title' => 'Shadow Priest', 'playable_class_id' => 5, 'icon_name' => 'spell_shadow_shadowwordpain'],

        // Specs - Rogue
        ['type' => 'Spec', 'title' => 'Assassination Rogue', 'playable_class_id' => 4, 'icon_name' => 'ability_rogue_eviscerate'],
        ['type' => 'Spec', 'title' => 'Combat Rogue', 'playable_class_id' => 4, 'icon_name' => 'ability_backstab'],
        ['type' => 'Spec', 'title' => 'Subtlety Rogue', 'playable_class_id' => 4, 'icon_name' => 'ability_stealth'],

        // Specs - Shaman
        ['type' => 'Spec', 'title' => 'Elemental Shaman', 'playable_class_id' => 7, 'icon_name' => 'spell_nature_lightning'],
        ['type' => 'Spec', 'title' => 'Enhancement Shaman', 'playable_class_id' => 7, 'icon_name' => 'spell_nature_lightningshield'],
        ['type' => 'Spec', 'title' => 'Restoration Shaman', 'playable_class_id' => 7, 'icon_name' => 'spell_nature_magicimmunity'],

        // Specs - Warlock
        ['type' => 'Spec', 'title' => 'Affliction Warlock', 'playable_class_id' => 9, 'icon_name' => 'spell_shadow_deathcoil'],
        ['type' => 'Spec', 'title' => 'Demonology Warlock', 'playable_class_id' => 9, 'icon_name' => 'spell_shadow_metamorphosis'],
        ['type' => 'Spec', 'title' => 'Destruction Warlock', 'playable_class_id' => 9, 'icon_name' => 'spell_shadow_rainoffire'],

        // Specs - Warrior
        ['type' => 'Spec', 'title' => 'Arms Warrior', 'playable_class_id' => 1, 'icon_name' => 'ability_warrior_savageblow'],
        ['type' => 'Spec', 'title' => 'Fury Warrior', 'playable_class_id' => 1, 'icon_name' => 'ability_warrior_innerrage'],
        ['type' => 'Spec', 'title' => 'Protection Warrior', 'playable_class_id' => 1, 'icon_name' => 'ability_warrior_defensivestance'],

        // Custom
        ['type' => 'Custom', 'title' => 'Feral Druid', 'playable_class_id' => 11, 'icon_name' => 'ability_druid_mangle2'],
        ['type' => 'Custom', 'title' => 'Fire Warlock', 'playable_class_id' => 9, 'icon_name' => 'spell_fire_burnout'],
        ['type' => 'Custom', 'title' => 'Shadow Warlock', 'playable_class_id' => 9, 'icon_name' => 'spell_shadow_shadowbolt'],
        ['type' => 'Custom', 'title' => 'Healing Priest', 'playable_class_id' => 5, 'icon_name' => 'spell_holy_greaterheal'],
        ['type' => 'Custom', 'title' => 'DPS Warrior', 'playable_class_id' => 1, 'icon_name' => 'ability_rogue_ambush'],

        // Disenchant
        ['type' => 'Meme', 'title' => 'Disenchant', 'icon_name' => 'inv_enchant_voidcrystal'],
    ];

    /**
     * @return array<int, array>
     */
    public static function priorities(): array
    {
        return self::$priorities;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update 'Ranged DPS' to 'Caster DPS'.
        // This needs to happen before seeding the priorities, otherwise we will end up with duplicates.
        LootPriority::where(['type' => 'Role', 'title' => 'Ranged DPS'])->update(['title' => 'Caster DPS']);

        // Seed the priorities
        $this->seedPriorities();
    }

    protected function seedPriorities(): void
    {
        foreach (self::priorities() as $priority) {
            $iconName = $priority['icon_name'];

            $model = LootPriority::query()->updateOrCreate(
                ['title' => $priority['title']],
                [
                    'type' => $priority['type'],
                    'title' => $priority['title'],
                    'playable_class_id' => $priority['playable_class_id'] ?? null,
                ]
            );

            if ($model->hasMedia('blizzard_icons')) {
                continue;
            }

            $iconFileName = $iconName.'.'.self::DEFAULT_MEDIA_FILE_EXTENSION;

            try {
                $response = $this->renderConnector->send(new FetchIconRequest($iconName));

                $model->addMediaFromString($response->body())
                    ->usingFileName($iconFileName)
                    ->withCustomProperties(['size' => self::DEFAULT_MEDIA_SIZE])
                    ->toMediaCollection('blizzard_icons');
            } catch (ForbiddenException $e) {
                AttachBlizzardIconToModel::dispatch(LootPriority::class, $model->id, $e->getPendingRequest()->getUrl())
                    ->delay(now()->addMinutes(5));
                $this->command?->warn("  ⚠ [{$model->title}] Icon deferred (403) — retrying in 5 min");
            } catch (MediaNotFoundException|RequestException $e) {
                $this->command?->warn("  ⚠ [{$model->title}] Icon skipped — {$e->getMessage()}");
            }
        }
    }
}
