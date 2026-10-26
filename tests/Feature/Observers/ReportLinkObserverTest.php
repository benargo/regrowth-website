<?php

namespace Tests\Feature\Observers;

use App\Jobs\Addon\BuildExportFile;
use App\Models\GameVersion;
use App\Models\Report;
use App\Models\ReportLink;
use App\Observers\ReportLinkObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class ReportLinkObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([BuildExportFile::class]);
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
        [$reportLink, $gameVersion] = $this->linkedReportsIn(GameVersion::factory()->fetchableRoster()->create());

        (new ReportLinkObserver)->saved($reportLink);

        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id && $job->delay !== null);
    }

    #[Test]
    public function deleted_schedules_a_delayed_addon_export_build(): void
    {
        [$reportLink, $gameVersion] = $this->linkedReportsIn(GameVersion::factory()->fetchableRoster()->create());

        (new ReportLinkObserver)->deleted($reportLink);

        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id && $job->delay !== null);
    }

    #[Test]
    public function linking_reports_rebuilds_the_game_versions_of_both_reports(): void
    {
        $first = GameVersion::factory()->fetchableRoster()->create();
        $second = GameVersion::factory()->fetchableRoster()->create();
        $report1 = Report::factory()->create();
        $report2 = Report::factory()->create();
        $report1->forceFill(['game_version_id' => $first->id])->saveQuietly();
        $report2->forceFill(['game_version_id' => $second->id])->saveQuietly();

        (new ReportLinkObserver)->saved(new ReportLink(['report_1' => $report1->id, 'report_2' => $report2->id]));

        Bus::assertDispatchedTimes(BuildExportFile::class, 2);
        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $first->id);
        Bus::assertDispatched(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $second->id);
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

    /**
     * Build an unsaved link between two reports that belong to the given game version.
     *
     * @return array{0: ReportLink, 1: GameVersion}
     */
    private function linkedReportsIn(GameVersion $gameVersion): array
    {
        $reports = Report::factory()->count(2)->create();
        $reports->each(fn (Report $report) => $report->forceFill(['game_version_id' => $gameVersion->id])->saveQuietly());

        return [new ReportLink(['report_1' => $reports[0]->id, 'report_2' => $reports[1]->id]), $gameVersion];
    }
}
