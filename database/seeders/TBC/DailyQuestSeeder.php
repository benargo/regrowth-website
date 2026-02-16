<?php

namespace Database\Seeders\TBC;

use App\Enums\Instance;
use App\Models\TBC\DailyQuest;
use Illuminate\Database\Seeder;

class DailyQuestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Daily quests are categorized into 4 types: Cooking, Dungeon, Fishing, and PvP. Each type has its own set of quests with specific rewards. We will seed all these quests into the database.
        $dailyQuests = array_merge(
            $this->cookingQuests,
            $this->dungeonQuests,
            $this->fishingQuests,
            $this->pvpQuests,
        );

        foreach ($dailyQuests as $quest) {
            DailyQuest::query()->updateOrCreate(
                ['id' => $quest['id']],
                $quest
            );
        }
    }

    protected array $cookingQuests = [
        [
            'id' => 11380,
            'name' => 'Manalicious',
            'type' => 'Cooking',
            'instance' => null,
            'mode' => null,
            'rewards' => [
                ['item_id' => 33844, 'quantity' => 1], // Barrel of Fish
                ['item_id' => 33857, 'quantity' => 1], // Crate of Meat
            ],
        ],
        [
            'id' => 11377,
            'name' => 'Revenge is Tasty',
            'type' => 'Cooking',
            'instance' => null,
            'mode' => null,
            'rewards' => [
                ['item_id' => 33844, 'quantity' => 1], // Barrel of Fish
                ['item_id' => 33857, 'quantity' => 1], // Crate of Meat
            ],
        ],
        [
            'id' => 11381,
            'name' => 'Soup for the Soul',
            'type' => 'Cooking',
            'instance' => null,
            'mode' => null,
            'rewards' => [
                ['item_id' => 33844, 'quantity' => 1], // Barrel of Fish
                ['item_id' => 33857, 'quantity' => 1], // Crate of Meat
            ],
        ],
        [
            'id' => 11379,
            'name' => 'Super Hot Stew',
            'type' => 'Cooking',
            'instance' => null,
            'mode' => null,
            'rewards' => [
                ['item_id' => 33844, 'quantity' => 1], // Barrel of Fish
                ['item_id' => 33857, 'quantity' => 1], // Crate of Meat
            ],
        ],
    ];

    protected array $dungeonQuests = [
        /**
         * Normal daily quests
         */
        [
            'id' => 11389,
            'name' => 'Wanted: Arcatraz Sentinels',
            'type' => 'Dungeon',
            'instance' => Instance::Arcatraz->value,
            'mode' => 'Normal',
            'rewards' => [['item_id' => 29460, 'quantity' => 1]], // Ethereum Prison Key
        ],
        [
            'id' => 11390,
            'name' => 'Wanted: Coilfang Myrmidons',
            'type' => 'Dungeon',
            'instance' => Instance::Steamvault->value,
            'mode' => 'Normal',
            'rewards' => [['item_id' => 29460, 'quantity' => 1]], // Ethereum Prison Key
        ],
        [
            'id' => 11376,
            'name' => 'Wanted: Malicious Instructors',
            'type' => 'Dungeon',
            'instance' => Instance::ShadowLabyrinth->value,
            'mode' => 'Normal',
            'rewards' => [['item_id' => 29460, 'quantity' => 1]], // Ethereum Prison Key
        ],
        [
            'id' => 11383,
            'name' => 'Wanted: Rift Lords',
            'type' => 'Dungeon',
            'instance' => Instance::BlackMorass->value,
            'mode' => 'Normal',
            'rewards' => [['item_id' => 29460, 'quantity' => 1]], // Ethereum Prison Key
        ],
        [
            'id' => 11364,
            'name' => 'Wanted: Shattered Hand Centurions',
            'type' => 'Dungeon',
            'instance' => Instance::ShatteredHalls->value,
            'mode' => 'Normal',
            'rewards' => [['item_id' => 29460, 'quantity' => 1]], // Ethereum Prison Key
        ],
        // Magisters' Terrace is not yet implemented, so keep this quest commented out until it is
        // [
        //     'id' => 11500,
        //     'name' => 'Wanted: Sisters of Torment',
        //     'type' => 'Dungeon',
        //     'instance' => Instance::MagistersTerrace->value,
        //     'mode' => 'Normal',
        //     'rewards' => [['item_id' => 29460, 'quantity' => 1]], // Ethereum Prison Key
        // ],
        [
            'id' => 11385,
            'name' => 'Wanted: Sunseeker Channelers',
            'type' => 'Dungeon',
            'instance' => Instance::Botanica->value,
            'mode' => 'Normal',
            'rewards' => [['item_id' => 29460, 'quantity' => 1]], // Ethereum Prison Key
        ],
        [
            'id' => 11387,
            'name' => 'Wanted: Tempest-Forge Destroyers',
            'type' => 'Dungeon',
            'instance' => Instance::Mechanar->value,
            'mode' => 'Normal',
            'rewards' => [['item_id' => 29460, 'quantity' => 1]], // Ethereum Prison Key
        ],

        /**
         * Heroic daily quests
         */
        [
            'id' => 11369,
            'name' => 'Wanted: A Black Stalker Egg',
            'type' => 'Dungeon',
            'instance' => Instance::Underbog->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11384,
            'name' => 'Wanted: A Warp Splinter Clipping',
            'type' => 'Dungeon',
            'instance' => Instance::Botanica->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11382,
            'name' => 'Wanted: Aeonus’s Hourglass',
            'type' => 'Dungeon',
            'instance' => Instance::BlackMorass->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11363,
            'name' => 'Wanted: Bladefist’s Seal',
            'type' => 'Dungeon',
            'instance' => Instance::ShatteredHalls->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11362,
            'name' => 'Wanted: Keli\'dan’s Feathered Stave',
            'type' => 'Dungeon',
            'instance' => Instance::BloodFurnace->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11375,
            'name' => 'Wanted: Murmur’s Whisper',
            'type' => 'Dungeon',
            'instance' => Instance::ShadowLabyrinth->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11354,
            'name' => 'Wanted: Nazan’s Riding Crop',
            'type' => 'Dungeon',
            'instance' => Instance::HellfireRamparts->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11386,
            'name' => 'Wanted: Pathaleon’s Projector',
            'type' => 'Dungeon',
            'instance' => Instance::Mechanar->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11373,
            'name' => 'Wanted: Shaffar’s Wondrous Pendant',
            'type' => 'Dungeon',
            'instance' => Instance::ManaTombs->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11378,
            'name' => 'Wanted: The Epoch Hunter’s Head',
            'type' => 'Dungeon',
            'instance' => Instance::OldHillsbradFoothills->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11374,
            'name' => 'Wanted: The Exarch’s Soul Gem',
            'type' => 'Dungeon',
            'instance' => Instance::AuchenaiCrypts->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11372,
            'name' => 'Wanted: The Headfeathers of Ikiss',
            'type' => 'Dungeon',
            'instance' => Instance::SethekkHalls->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11368,
            'name' => 'Wanted: The Heart of Quagmirran',
            'type' => 'Dungeon',
            'instance' => Instance::SlavePens->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        [
            'id' => 11388,
            'name' => 'Wanted: The Scroll of Skyriss',
            'type' => 'Dungeon',
            'instance' => Instance::Arcatraz->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
        // Magisters' Terrace is not yet implemented, so keep this quest commented out until it is
        // [
        //     'id' => 11499,
        //     'name' => 'Wanted: The Signet Ring of Prince Kael\'thas',
        //     'type' => 'Dungeon',
        //     'instance' => Instance::MagistersTerrace->value,
        //     'mode' => 'Heroic',
        //     'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        // ],
        [
            'id' => 11370,
            'name' => 'Wanted: The Warlord’s Treatise',
            'type' => 'Dungeon',
            'instance' => Instance::Steamvault->value,
            'mode' => 'Heroic',
            'rewards' => [['item_id' => 29434, 'quantity' => 2]], // Badge of Justice (2)
        ],
    ];

    protected array $fishingQuests = [
        [
            'id' => 11666,
            'name' => 'Bait Bandits',
            'type' => 'Fishing',
            'instance' => null,
            'mode' => null,
            'rewards' => [['item_id' => 34863, 'quantity' => 1]], // Bag of Fishing Treasures
        ],
        [
            'id' => 11665,
            'name' => 'Crocolisks in the City',
            'type' => 'Fishing',
            'instance' => null,
            'mode' => null,
            'rewards' => [['item_id' => 34863, 'quantity' => 1]], // Bag of Fishing Treasures
        ],
        [
            'id' => 11669,
            'name' => 'Felblood Fillet',
            'type' => 'Fishing',
            'instance' => null,
            'mode' => null,
            'rewards' => [['item_id' => 34863, 'quantity' => 1]], // Bag of Fishing Treasures
        ],
        [
            'id' => 11668,
            'name' => 'Shrimpin\' Ain\'t Easy',
            'type' => 'Fishing',
            'instance' => null,
            'mode' => null,
            'rewards' => [['item_id' => 34863, 'quantity' => 1]], // Bag of Fishing Treasures
        ],
        [
            'id' => 11667,
            'name' => 'The One That Got Away',
            'type' => 'Fishing',
            'instance' => null,
            'mode' => null,
            'rewards' => [['item_id' => 34863, 'quantity' => 1]], // Bag of Fishing Treasures
        ],
    ];

    protected array $pvpQuests = [
        [
            'id' => 11336,
            'name' => 'Call to Arms: Alterac Valley',
            'type' => 'pvp',
            'instance' => Instance::AlteracValley->value,
            'mode' => null,
            'rewards' => [['item_id' => 20560, 'quantity' => 3]], // Alterac Valley Mark of Honor (3)
        ],
        [
            'id' => 11335,
            'name' => 'Call to Arms: Arathi Basin',
            'type' => 'pvp',
            'instance' => Instance::ArathiBasin->value,
            'mode' => null,
            'rewards' => [['item_id' => 20559, 'quantity' => 3]], // Arathi Basin Mark of Honor (3)
        ],
        [
            'id' => 11337,
            'name' => 'Call to Arms: Eye of the Storm',
            'type' => 'pvp',
            'instance' => Instance::EyeOfTheStorm->value,
            'mode' => null,
            'rewards' => [['item_id' => 29024, 'quantity' => 3]], // Eye of the Storm Mark of Honor (3)
        ],
        [
            'id' => 11338,
            'name' => 'Call to Arms: Warsong Gulch',
            'type' => 'pvp',
            'instance' => Instance::WarsongGulch->value,
            'mode' => null,
            'rewards' => [['item_id' => 20558, 'quantity' => 3]], // Warsong Gulch Mark of Honor (3)
        ],
    ];
}
