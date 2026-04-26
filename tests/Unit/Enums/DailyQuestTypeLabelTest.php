<?php

namespace Tests\Unit\Enums;

use App\Enums\DailyQuestTypeLabel;
use PHPUnit\Framework\TestCase;

class DailyQuestTypeLabelTest extends TestCase
{
    public function test_cases_have_correct_values(): void
    {
        $this->assertSame('Cooking', DailyQuestTypeLabel::Cooking->value);
        $this->assertSame('Fishing', DailyQuestTypeLabel::Fishing->value);
        $this->assertSame('Normal dungeon', DailyQuestTypeLabel::Dungeon->value);
        $this->assertSame('Heroic dungeon', DailyQuestTypeLabel::Heroic->value);
        $this->assertSame('PvP battleground', DailyQuestTypeLabel::PvP->value);
    }
}
