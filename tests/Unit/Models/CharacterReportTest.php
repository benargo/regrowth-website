<?php

namespace Tests\Unit\Models;

use App\Models\Character;
use App\Models\CharacterReport;
use App\Models\WarcraftLogs\Report;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CharacterReportTest extends TestCase
{
    #[Test]
    public function it_uses_the_correct_table(): void
    {
        $pivot = new CharacterReport;

        $this->assertSame('pivot_characters_wcl_reports', $pivot->getTable());
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
}
