<?php

namespace App\Enums;

use Illuminate\Support\Arr;

enum DailyQuestType: string
{
    case Cooking = 'Cooking';
    case Fishing = 'Fishing';
    case Dungeon = 'Normal dungeon';
    case Heroic = 'Heroic dungeon';
    case PvP = 'PvP battleground';

    /**
     * Get an array mapping the enum case names to their values.
     */
    public static function map(): array
    {
        return Arr::mapWithKeys(self::cases(), fn (self $case) => [$case->name => $case->value]);
    }

    /**
     * Get the reward items for this quest type.
     *
     * @return array<int, array{item_id: int, quantity: int}>
     */
    public function rewards(): array
    {
        return match ($this) {
            self::Cooking => [['item_id' => 33844, 'quantity' => 1], ['item_id' => 33857, 'quantity' => 1]],
            self::Fishing => [['item_id' => 34863, 'quantity' => 1]],
            self::Dungeon => [['item_id' => 29460, 'quantity' => 1]],
            self::Heroic => [['item_id' => 29434, 'quantity' => 2]],
            self::PvP => [],
        };
    }

    /**
     * Get the icon name associated with the daily quest type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Cooking => 'inv_misc_food_15',
            self::Fishing => 'trade_fishing',
            self::Dungeon => 'inv_qiraj_jewelencased',
            self::Heroic => 'spell_holy_championsbond',
            self::PvP => 'inv_bannerpvp_02',
        };
    }
}
