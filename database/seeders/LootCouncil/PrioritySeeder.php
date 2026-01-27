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
            ['type' => 'Role', 'title' => 'Tank', 'media' => ['media_type' => 'item', 'media_id' => 1201]],
            ['type' => 'Role', 'title' => 'Healer', 'media' => ['media_type' => 'spell', 'media_id' => 41386]],
            ['type' => 'Role', 'title' => 'Melee DPS', 'media' => ['media_type' => 'item', 'media_id' => 2131]],
            ['type' => 'Role', 'title' => 'Ranged DPS', 'media' => ['media_type' => 'spell', 'media_id' => 30672]],

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
            ['type' => 'Spec', 'title' => 'Balance Druid', 'media' => ['media_type' => 'spell', 'media_id' => 8921]],
            ['type' => 'Spec', 'title' => 'Feral DPS Druid', 'media' => ['media_type' => 'spell', 'media_id' => 768]],
            ['type' => 'Spec', 'title' => 'Feral Tank Druid', 'media' => ['media_type' => 'spell', 'media_id' => 5487]],
            ['type' => 'Spec', 'title' => 'Restoration Druid', 'media' => ['media_type' => 'spell', 'media_id' => 5185]],

            // Specs - Hunter
            ['type' => 'Spec', 'title' => 'Beast Mastery Hunter', 'media' => ['media_type' => 'spell', 'media_id' => 1515]],
            ['type' => 'Spec', 'title' => 'Marksmanship Hunter', 'media' => ['media_type' => 'spell', 'media_id' => 1510]],
            ['type' => 'Spec', 'title' => 'Survival Hunter', 'media' => ['media_type' => 'spell', 'media_id' => 1495]],

            // Specs - Mage
            ['type' => 'Spec', 'title' => 'Arcane Mage', 'media' => ['media_type' => 'spell', 'media_id' => 8921]],
            ['type' => 'Spec', 'title' => 'Fire Mage', 'media' => ['media_type' => 'spell', 'media_id' => 133]],
            ['type' => 'Spec', 'title' => 'Frost Mage', 'media' => ['media_type' => 'spell', 'media_id' => 116]],

            // Specs - Paladin
            ['type' => 'Spec', 'title' => 'Holy Paladin', 'media' => ['media_type' => 'spell', 'media_id' => 635]],
            ['type' => 'Spec', 'title' => 'Protection Paladin', 'media' => ['media_type' => 'spell', 'media_id' => 465]],
            ['type' => 'Spec', 'title' => 'Retribution Paladin', 'media' => ['media_type' => 'spell', 'media_id' => 7294]],

            // Specs - Priest
            ['type' => 'Spec', 'title' => 'Discipline Priest', 'media' => ['media_type' => 'spell', 'media_id' => 17]],
            ['type' => 'Spec', 'title' => 'Holy Priest', 'media' => ['media_type' => 'spell', 'media_id' => 47788]],
            ['type' => 'Spec', 'title' => 'Shadow Priest', 'media' => ['media_type' => 'spell', 'media_id' => 589]],

            // Specs - Rogue
            ['type' => 'Spec', 'title' => 'Assassination Rogue', 'media' => ['media_type' => 'spell', 'media_id' => 2098]],
            ['type' => 'Spec', 'title' => 'Combat Rogue', 'media' => ['media_type' => 'spell', 'media_id' => 53]],
            ['type' => 'Spec', 'title' => 'Subtlety Rogue', 'media' => ['media_type' => 'spell', 'media_id' => 1784]],

            // Specs - Shaman
            ['type' => 'Spec', 'title' => 'Elemental Shaman', 'media' => ['media_type' => 'spell', 'media_id' => 403]],
            ['type' => 'Spec', 'title' => 'Enhancement Shaman', 'media' => ['media_type' => 'spell', 'media_id' => 324]],
            ['type' => 'Spec', 'title' => 'Restoration Shaman', 'media' => ['media_type' => 'spell', 'media_id' => 331]],

            // Specs - Warlock
            ['type' => 'Spec', 'title' => 'Affliction Warlock', 'media' => ['media_type' => 'spell', 'media_id' => 6789]],
            ['type' => 'Spec', 'title' => 'Demonology Warlock', 'media' => ['media_type' => 'spell', 'media_id' => 18697]],
            ['type' => 'Spec', 'title' => 'Destruction Warlock', 'media' => ['media_type' => 'spell', 'media_id' => 5740]],

            // Specs - Warrior
            ['type' => 'Spec', 'title' => 'Arms Warrior', 'media' => ['media_type' => 'spell', 'media_id' => 12294]],
            ['type' => 'Spec', 'title' => 'Fury Warrior', 'media' => ['media_type' => 'spell', 'media_id' => 20375]],
            ['type' => 'Spec', 'title' => 'Protection Warrior', 'media' => ['media_type' => 'spell', 'media_id' => 71]],
        ];

        foreach ($priorities as $priority) {
            Priority::query()->updateOrCreate(
                ['type' => $priority['type'], 'title' => $priority['title']],
                $priority
            );
        }
    }
}
