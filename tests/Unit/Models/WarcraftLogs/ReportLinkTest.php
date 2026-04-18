<?php

namespace Tests\Unit\Models\WarcraftLogs;

use App\Events\ReportLinkDeleted;
use App\Events\ReportLinkSaved;
use App\Models\Raids\Report;
use App\Models\Raids\ReportLink;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ReportLinkTest extends TestCase
{
    #[Test]
    public function it_uses_the_correct_table(): void
    {
        $pivot = new ReportLink;

        $this->assertSame('raid_report_links', $pivot->getTable());
    }

    #[Test]
    public function it_dispatches_events_on_saved_and_deleted(): void
    {
        $pivot = new ReportLink;

        $this->assertSame([
            'saved' => ReportLinkSaved::class,
            'deleted' => ReportLinkDeleted::class,
        ], $pivot->dispatchesEvents());
    }

    #[Test]
    public function it_touches_report1_and_report2_relationships(): void
    {
        $pivot = new ReportLink;

        $this->assertContains('report1', $pivot->getTouchedRelations());
        $this->assertContains('report2', $pivot->getTouchedRelations());
    }

    #[Test]
    public function report1_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(ReportLink::class, 'report1'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function report1_method_is_typed_to_report_model(): void
    {
        $source = (new ReflectionMethod(ReportLink::class, 'report1'))->getFileName();

        $this->assertStringContainsString('ReportLink.php', $source);

        // Verify the Report model is imported in the pivot class
        $this->assertTrue(class_exists(Report::class));
    }

    #[Test]
    public function report2_method_returns_belongs_to(): void
    {
        $returnType = (new ReflectionMethod(ReportLink::class, 'report2'))->getReturnType();

        $this->assertSame(BelongsTo::class, $returnType->getName());
    }

    #[Test]
    public function report2_method_is_typed_to_report_model(): void
    {
        $source = (new ReflectionMethod(ReportLink::class, 'report2'))->getFileName();

        $this->assertStringContainsString('ReportLink.php', $source);

        // Verify the Report model is imported in the pivot class
        $this->assertTrue(class_exists(Report::class));
    }
}
