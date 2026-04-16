<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterReport;
use App\Models\Raids\Report;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class CharacterReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_the_correct_table(): void
    {
        $pivot = new CharacterReport;

        $this->assertSame('pivot_characters_raid_reports', $pivot->getTable());
    }

    #[Test]
    public function it_has_timestamps_disabled(): void
    {
        $pivot = new CharacterReport;

        $this->assertFalse($pivot->timestamps);
    }

    #[Test]
    public function it_touches_the_report_relationship(): void
    {
        $pivot = new CharacterReport;

        $this->assertContains('report', $pivot->getTouchedRelations());
    }

    // ==================== is_loot_councillor ====================

    #[Test]
    public function it_defaults_is_loot_councillor_to_false(): void
    {
        $character = Character::factory()->create();
        $report = Report::factory()->create();

        $report->characters()->attach($character->id, ['presence' => 1]);

        $pivot = CharacterReport::where('character_id', $character->id)
            ->where('raid_report_id', $report->id)
            ->first();

        $this->assertFalse($pivot->is_loot_councillor);
    }

    #[Test]
    public function it_can_set_is_loot_councillor_to_true(): void
    {
        $character = Character::factory()->create();
        $report = Report::factory()->create();

        $report->characters()->attach($character->id, ['presence' => 1, 'is_loot_councillor' => true]);

        $pivot = CharacterReport::where('character_id', $character->id)
            ->where('raid_report_id', $report->id)
            ->first();

        $this->assertTrue($pivot->is_loot_councillor);
    }

    #[Test]
    public function it_casts_is_loot_councillor_to_boolean(): void
    {
        $pivot = new CharacterReport;

        $this->assertArrayHasKey('is_loot_councillor', $pivot->getCasts());
        $this->assertSame('boolean', $pivot->getCasts()['is_loot_councillor']);
    }

    // ==================== report ====================

    #[Test]
    public function report_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(CharacterReport::class, 'report'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function report_method_is_typed_to_report_model(): void
    {
        $source = (new ReflectionMethod(CharacterReport::class, 'report'))->getFileName();

        $this->assertStringContainsString('CharacterReport.php', $source);

        // Verify the Report model is imported in the pivot class
        $this->assertTrue(class_exists(Report::class));
    }

    #[Test]
    public function report_relation_returns_the_associated_report(): void
    {
        $character = Character::factory()->create();
        $report = Report::factory()->create();

        $report->characters()->attach($character->id, ['presence' => 1]);

        $pivot = CharacterReport::where('character_id', $character->id)
            ->where('raid_report_id', $report->id)
            ->first();

        $this->assertInstanceOf(Report::class, $pivot->report);
        $this->assertTrue($pivot->report->is($report));
    }

    // ==================== character ====================

    #[Test]
    public function character_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(CharacterReport::class, 'character'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function character_method_is_typed_to_character_model(): void
    {
        $source = (new ReflectionMethod(CharacterReport::class, 'character'))->getFileName();

        $this->assertStringContainsString('CharacterReport.php', $source);

        // Verify the Character model is imported in the pivot class
        $this->assertTrue(class_exists(Character::class));
    }

    #[Test]
    public function character_relation_returns_the_associated_character(): void
    {
        $character = Character::factory()->create();
        $report = Report::factory()->create();

        $report->characters()->attach($character->id, ['presence' => 1]);

        $pivot = CharacterReport::where('character_id', $character->id)
            ->where('raid_report_id', $report->id)
            ->first();

        $this->assertInstanceOf(Character::class, $pivot->character);
        $this->assertTrue($pivot->character->is($character));
    }
}
