<?php

namespace Tests\Unit\Enums;

use App\Enums\Instance;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InstanceTest extends TestCase
{
    #[Test]
    public function daily_quest_rewards_returns_correct_items_for_battlegrounds(): void
    {
        $this->assertSame([['item_id' => 20560, 'quantity' => 3]], Instance::AlteracValley->dailyQuestRewards());
        $this->assertSame([['item_id' => 20559, 'quantity' => 3]], Instance::ArathiBasin->dailyQuestRewards());
        $this->assertSame([['item_id' => 29024, 'quantity' => 3]], Instance::EyeOfTheStorm->dailyQuestRewards());
        $this->assertSame([['item_id' => 20558, 'quantity' => 3]], Instance::WarsongGulch->dailyQuestRewards());
    }

    #[Test]
    public function daily_quest_rewards_returns_empty_array_for_non_battleground_instances(): void
    {
        $this->assertSame([], Instance::HellfireRamparts->dailyQuestRewards());
        $this->assertSame([], Instance::Underbog->dailyQuestRewards());
        $this->assertSame([], Instance::ShadowLabyrinth->dailyQuestRewards());
        $this->assertSame([], Instance::BlackMorass->dailyQuestRewards());
        $this->assertSame([], Instance::Mechanar->dailyQuestRewards());
    }
}
