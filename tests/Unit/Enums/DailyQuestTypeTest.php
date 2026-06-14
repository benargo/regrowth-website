<?php

namespace Tests\Unit\Enums;

use App\Enums\DailyQuestType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('daily-quests')]
class DailyQuestTypeTest extends TestCase
{
    #[Test]
    public function cases_have_correct_values(): void
    {
        $this->assertSame('Cooking', DailyQuestType::Cooking->value);
        $this->assertSame('Fishing', DailyQuestType::Fishing->value);
        $this->assertSame('Normal dungeon', DailyQuestType::Dungeon->value);
        $this->assertSame('Heroic dungeon', DailyQuestType::Heroic->value);
        $this->assertSame('PvP battleground', DailyQuestType::PvP->value);
    }

    #[Test]
    public function icon_returns_correct_icon_name(): void
    {
        $this->assertSame('inv_misc_food_15', DailyQuestType::Cooking->icon());
        $this->assertSame('trade_fishing', DailyQuestType::Fishing->icon());
        $this->assertSame('inv_qiraj_jewelencased', DailyQuestType::Dungeon->icon());
        $this->assertSame('spell_holy_championsbond', DailyQuestType::Heroic->icon());
        $this->assertSame('inv_bannerpvp_02', DailyQuestType::PvP->icon());
    }

    #[Test]
    public function rewards_returns_correct_items_per_type(): void
    {
        $this->assertSame([['item_id' => 33844, 'quantity' => 1], ['item_id' => 33857, 'quantity' => 1]], DailyQuestType::Cooking->rewards());
        $this->assertSame([['item_id' => 34863, 'quantity' => 1]], DailyQuestType::Fishing->rewards());
        $this->assertSame([['item_id' => 29460, 'quantity' => 1]], DailyQuestType::Dungeon->rewards());
        $this->assertSame([['item_id' => 29434, 'quantity' => 2]], DailyQuestType::Heroic->rewards());
        $this->assertSame([], DailyQuestType::PvP->rewards());
    }

    #[Test]
    public function map_returns_name_to_value_array(): void
    {
        $this->assertSame([
            'Cooking' => 'Cooking',
            'Fishing' => 'Fishing',
            'Dungeon' => 'Normal dungeon',
            'Heroic' => 'Heroic dungeon',
            'PvP' => 'PvP battleground',
        ], DailyQuestType::map());
    }
}
