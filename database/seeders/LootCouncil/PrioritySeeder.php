<?php

namespace Database\Seeders\LootCouncil;

use App\Models\LootCouncil\Priority;
use Illuminate\Database\Seeder;

class PrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priorities = [
            // Roles
            ['type' => 'Role', 'title' => 'Tank', 'media' => ['media_name' => 'inv_shield_04']],
            ['type' => 'Role', 'title' => 'Backup tank', 'media' => ['media_name' => 'inv_shield_09']],
            ['type' => 'Role', 'title' => 'Healer', 'media' => ['media_name' => 'spell_holy_heal']],
            ['type' => 'Role', 'title' => 'Melee DPS', 'media' => ['media_name' => 'inv_sword_04']],
            ['type' => 'Role', 'title' => 'Ranged DPS', 'media' => ['media_name' => 'spell_nature_elementalprecision_1']],

            // Classes
            ['type' => 'Class', 'title' => 'Druid', 'media' => ['media_type' => 'playable-class', 'media_id' => 11]],
            ['type' => 'Class', 'title' => 'Hunter', 'media' => ['media_type' => 'playable-class', 'media_id' => 3]],
            ['type' => 'Class', 'title' => 'Mage', 'media' => ['media_type' => 'playable-class', 'media_id' => 8]],
            ['type' => 'Class', 'title' => 'Paladin', 'media' => ['media_type' => 'playable-class', 'media_id' => 2]],
            ['type' => 'Class', 'title' => 'Priest', 'media' => ['media_type' => 'playable-class', 'media_id' => 5]],
            ['type' => 'Class', 'title' => 'Rogue', 'media' => ['media_type' => 'playable-class', 'media_id' => 4]],
            ['type' => 'Class', 'title' => 'Shaman', 'media' => ['media_type' => 'playable-class', 'media_id' => 7]],
            ['type' => 'Class', 'title' => 'Warlock', 'media' => ['media_type' => 'playable-class', 'media_id' => 9]],
            ['type' => 'Class', 'title' => 'Warrior', 'media' => ['media_type' => 'playable-class', 'media_id' => 1]],

            // Specs - Druid
            ['type' => 'Spec', 'title' => 'Balance Druid', 'media' => ['media_name' => 'spell_nature_starfall']],
            ['type' => 'Spec', 'title' => 'Feral DPS Druid', 'media' => ['media_name' => 'ability_druid_catform']],
            ['type' => 'Spec', 'title' => 'Feral Tank Druid', 'media' => ['media_name' => 'ability_racial_bearform']],
            ['type' => 'Spec', 'title' => 'Restoration Druid', 'media' => ['media_name' => 'spell_nature_healingtouch']],

            // Specs - Hunter
            ['type' => 'Spec', 'title' => 'Beast Mastery Hunter', 'media' => ['media_name' => 'ability_hunter_beasttaming']],
            ['type' => 'Spec', 'title' => 'Marksmanship Hunter', 'media' => ['media_name' => 'ability_marksmanship']],
            ['type' => 'Spec', 'title' => 'Survival Hunter', 'media' => ['media_name' => 'ability_hunter_swiftstrike']],

            // Specs - Mage
            ['type' => 'Spec', 'title' => 'Arcane Mage', 'media' => ['media_name' => 'spell_arcane_blast']],
            ['type' => 'Spec', 'title' => 'Fire Mage', 'media' => ['media_name' => 'spell_fire_flamebolt']],
            ['type' => 'Spec', 'title' => 'Frost Mage', 'media' => ['media_name' => 'spell_frost_frostbolt02']],

            // Specs - Paladin
            ['type' => 'Spec', 'title' => 'Holy Paladin', 'media' => ['media_name' => 'spell_holy_holybolt']],
            ['type' => 'Spec', 'title' => 'Protection Paladin', 'media' => ['media_name' => 'spell_holy_devotionaura']],
            ['type' => 'Spec', 'title' => 'Retribution Paladin', 'media' => ['media_name' => 'spell_holy_auraoflight']],

            // Specs - Priest
            ['type' => 'Spec', 'title' => 'Discipline Priest', 'media' => ['media_name' => 'spell_holy_powerwordshield']],
            ['type' => 'Spec', 'title' => 'Holy Priest', 'media' => ['media_name' => 'spell_holy_guardianspirit']],
            ['type' => 'Spec', 'title' => 'Shadow Priest', 'media' => ['media_name' => 'spell_shadow_shadowwordpain']],

            // Specs - Rogue
            ['type' => 'Spec', 'title' => 'Assassination Rogue', 'media' => ['media_name' => 'ability_rogue_eviscerate']],
            ['type' => 'Spec', 'title' => 'Combat Rogue', 'media' => ['media_name' => 'ability_backstab']],
            ['type' => 'Spec', 'title' => 'Subtlety Rogue', 'media' => ['media_name' => 'ability_stealth']],

            // Specs - Shaman
            ['type' => 'Spec', 'title' => 'Elemental Shaman', 'media' => ['media_name' => 'spell_nature_lightning']],
            ['type' => 'Spec', 'title' => 'Enhancement Shaman', 'media' => ['media_name' => 'spell_nature_lightningshield']],
            ['type' => 'Spec', 'title' => 'Restoration Shaman', 'media' => ['media_name' => 'spell_nature_magicimmunity']],

            // Specs - Warlock
            ['type' => 'Spec', 'title' => 'Affliction Warlock', 'media' => ['media_name' => 'spell_shadow_deathcoil']],
            ['type' => 'Spec', 'title' => 'Demonology Warlock', 'media' => ['media_name' => 'spell_shadow_metamorphosis']],
            ['type' => 'Spec', 'title' => 'Destruction Warlock', 'media' => ['media_name' => 'spell_shadow_rainoffire']],

            // Specs - Warrior
            ['type' => 'Spec', 'title' => 'Arms Warrior', 'media' => ['media_name' => 'ability_warrior_savageblow']],
            ['type' => 'Spec', 'title' => 'Fury Warrior', 'media' => ['media_name' => 'ability_warrior_innerrage']],
            ['type' => 'Spec', 'title' => 'Protection Warrior', 'media' => ['media_name' => 'ability_warrior_defensivestance']],
        ];

        foreach ($priorities as $priority) {
            Priority::query()->updateOrCreate(
                ['type' => $priority['type'], 'title' => $priority['title']],
                $priority
            );
        }
    }
}
