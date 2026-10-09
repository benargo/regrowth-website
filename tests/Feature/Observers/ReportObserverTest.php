<?php

namespace Tests\Feature\Observers;

use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use App\Observers\ReportObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class ReportObserverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function saving_derives_the_game_version_and_phase(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $phase = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2025-01-01 00:00:00']);
        $report = Report::factory()->forGuild($guild)->make(['start_time' => '2025-03-14 19:30:00']);

        $observer = new ReportObserver;
        $observer->saving($report);

        $this->assertSame($gameVersion->id, $report->game_version_id);
        $this->assertSame($phase->id, $report->phase_id);
    }

    #[Test]
    public function created_flushes_reports_and_attendance_cache_tags(): void
    {
        Cache::tags(['reports'])->put('reports-key', 'reports-value', 60);
        Cache::tags(['attendance'])->put('attendance-key', 'attendance-value', 60);

        $observer = new ReportObserver;
        $observer->created(Report::factory()->make());

        $this->assertNull(Cache::tags(['reports'])->get('reports-key'));
        $this->assertNull(Cache::tags(['attendance'])->get('attendance-key'));
    }

    #[Test]
    public function updated_flushes_reports_and_attendance_cache_tags(): void
    {
        Cache::tags(['reports'])->put('reports-key', 'reports-value', 60);
        Cache::tags(['attendance'])->put('attendance-key', 'attendance-value', 60);

        $observer = new ReportObserver;
        $observer->updated(Report::factory()->make());

        $this->assertNull(Cache::tags(['reports'])->get('reports-key'));
        $this->assertNull(Cache::tags(['attendance'])->get('attendance-key'));
    }

    #[Test]
    public function defer_flushing_flushes_once_after_the_callback(): void
    {
        Cache::tags(['reports'])->put('reports-key', 'reports-value', 60);
        Cache::tags(['attendance'])->put('attendance-key', 'attendance-value', 60);

        ReportObserver::deferFlushing(function (): void {
            $observer = new ReportObserver;
            $observer->updated(Report::factory()->make());
            $observer->updated(Report::factory()->make());

            $this->assertSame('reports-value', Cache::tags(['reports'])->get('reports-key'));
            $this->assertSame('attendance-value', Cache::tags(['attendance'])->get('attendance-key'));
        });

        $this->assertNull(Cache::tags(['reports'])->get('reports-key'));
        $this->assertNull(Cache::tags(['attendance'])->get('attendance-key'));
    }

    #[Test]
    public function defer_flushing_does_not_flush_when_no_report_is_saved(): void
    {
        Cache::tags(['reports'])->put('reports-key', 'reports-value', 60);

        ReportObserver::deferFlushing(fn () => null);

        $this->assertSame('reports-value', Cache::tags(['reports'])->get('reports-key'));
    }

    #[Test]
    public function defer_flushing_flushes_and_resets_when_the_callback_throws(): void
    {
        Cache::tags(['reports'])->put('reports-key', 'reports-value', 60);

        try {
            ReportObserver::deferFlushing(function (): void {
                (new ReportObserver)->updated(Report::factory()->make());

                throw new \RuntimeException('Bulk save failed.');
            });
        } catch (\RuntimeException) {
        }

        $this->assertNull(Cache::tags(['reports'])->get('reports-key'));

        Cache::tags(['reports'])->put('reports-key', 'reports-value', 60);
        (new ReportObserver)->updated(Report::factory()->make());

        $this->assertNull(Cache::tags(['reports'])->get('reports-key'));
    }

    #[Test]
    public function created_does_not_flush_other_tags(): void
    {
        Cache::tags(['other-tag'])->put('other-key', 'other-value', 60);

        $observer = new ReportObserver;
        $observer->created(Report::factory()->make());

        $this->assertSame('other-value', Cache::tags(['other-tag'])->get('other-key'));
    }

    #[Test]
    public function observer_is_registered_on_report_model(): void
    {
        $attributes = (new \ReflectionClass(Report::class))
            ->getAttributes(ObservedBy::class);

        $this->assertNotEmpty($attributes);

        $observerClasses = $attributes[0]->getArguments()[0];
        $this->assertContains(ReportObserver::class, $observerClasses);
    }
}
