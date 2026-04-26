<?php

namespace Tests\Unit\Enums;

use App\Enums\DailyQuestIcons;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DailyQuestIconsTest extends TestCase
{
    // ==================== cases ====================

    #[Test]
    public function it_has_exactly_six_cases(): void
    {
        $this->assertCount(6, DailyQuestIcons::cases());
    }

    #[Test]
    public function each_case_has_the_correct_value(): void
    {
        $this->assertSame('inv_misc_food_15', DailyQuestIcons::Cooking->value);
        $this->assertSame('trade_fishing', DailyQuestIcons::Fishing->value);
        $this->assertSame('inv_qiraj_jewelencased', DailyQuestIcons::Dungeon->value);
        $this->assertSame('spell_holy_championsbond', DailyQuestIcons::HeroicDungeon->value);
        $this->assertSame('inv_bannerpvp_02', DailyQuestIcons::PvP->value);
        $this->assertSame('inv_misc_questionmark', DailyQuestIcons::Default->value);
    }
}
