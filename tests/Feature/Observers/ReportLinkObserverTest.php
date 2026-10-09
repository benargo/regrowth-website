<?php

namespace Tests\Feature\Observers;

use App\Jobs\BuildAddonExportFile;
use App\Models\ReportLink;
use App\Observers\ReportLinkObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class ReportLinkObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([BuildAddonExportFile::class]);
    }

    #[Test]
    public function saved_flushes_reports_and_attendance_cache_tags(): void
    {
        Cache::tags(['reports'])->put('reports-key', 'reports-value', 60);
        Cache::tags(['attendance'])->put('attendance-key', 'attendance-value', 60);

        $observer = new ReportLinkObserver;
        $observer->saved(new ReportLink);

        $this->assertNull(Cache::tags(['reports'])->get('reports-key'));
        $this->assertNull(Cache::tags(['attendance'])->get('attendance-key'));
    }

    #[Test]
    public function deleted_flushes_reports_and_attendance_cache_tags(): void
    {
        Cache::tags(['reports'])->put('reports-key', 'reports-value', 60);
        Cache::tags(['attendance'])->put('attendance-key', 'attendance-value', 60);

        $observer = new ReportLinkObserver;
        $observer->deleted(new ReportLink);

        $this->assertNull(Cache::tags(['reports'])->get('reports-key'));
        $this->assertNull(Cache::tags(['attendance'])->get('attendance-key'));
    }

    #[Test]
    public function saved_schedules_a_delayed_addon_export_build(): void
    {
        $observer = new ReportLinkObserver;
        $observer->saved(new ReportLink);

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function deleted_schedules_a_delayed_addon_export_build(): void
    {
        $observer = new ReportLinkObserver;
        $observer->deleted(new ReportLink);

        Bus::assertDispatched(BuildAddonExportFile::class, fn (BuildAddonExportFile $job) => $job->delay !== null);
    }

    #[Test]
    public function saved_does_not_flush_other_tags(): void
    {
        Cache::tags(['other-tag'])->put('other-key', 'other-value', 60);

        $observer = new ReportLinkObserver;
        $observer->saved(new ReportLink);

        $this->assertSame('other-value', Cache::tags(['other-tag'])->get('other-key'));
    }

    #[Test]
    public function observer_is_registered_on_report_link_model(): void
    {
        $attributes = (new \ReflectionClass(ReportLink::class))
            ->getAttributes(ObservedBy::class);

        $this->assertNotEmpty($attributes);

        $observerClasses = $attributes[0]->getArguments()[0];
        $this->assertContains(ReportLinkObserver::class, $observerClasses);
    }
}
