<?php

namespace Tests\Unit\Enums;

use App\Enums\DailyQuestTypeLabel;
use PHPUnit\Framework\TestCase;

class DailyQuestTypeLabelTest extends TestCase
{
    public function test_cases_have_correct_values(): void
    {
        $this->assertSame('Cooking', DailyQuestTypeLabel::CookingQuest->value);
        $this->assertSame('Fishing', DailyQuestTypeLabel::FishingQuest->value);
        $this->assertSame('Normal dungeon', DailyQuestTypeLabel::DungeonQuest->value);
        $this->assertSame('Heroic dungeon', DailyQuestTypeLabel::HeroicQuest->value);
        $this->assertSame('PvP battleground', DailyQuestTypeLabel::PvpQuest->value);
    }

    public function test_relation_names_returns_relation_as_key_and_label_as_value(): void
    {
        $relations = DailyQuestTypeLabel::relationNames();

        $this->assertArrayHasKey('cookingQuest', $relations);
        $this->assertArrayHasKey('fishingQuest', $relations);
        $this->assertArrayHasKey('dungeonQuest', $relations);
        $this->assertArrayHasKey('heroicQuest', $relations);
        $this->assertArrayHasKey('pvpQuest', $relations);
    }

    public function test_relation_names_values_are_human_readable_labels(): void
    {
        $relations = DailyQuestTypeLabel::relationNames();

        $this->assertSame('Cooking', $relations['cookingQuest']);
        $this->assertSame('Fishing', $relations['fishingQuest']);
        $this->assertSame('Normal dungeon', $relations['dungeonQuest']);
        $this->assertSame('Heroic dungeon', $relations['heroicQuest']);
        $this->assertSame('PvP battleground', $relations['pvpQuest']);
    }

    public function test_relation_names_covers_all_cases(): void
    {
        $relations = DailyQuestTypeLabel::relationNames();

        $this->assertCount(count(DailyQuestTypeLabel::cases()), $relations);
    }
}
