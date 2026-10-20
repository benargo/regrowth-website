<?php

namespace Tests\Feature\Actions\Reports;

use App\Actions\Reports\ListRecentReports;
use App\Models\GameVersion;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ListRecentReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['raiding', 'reports'])->flush();
        $this->freezeTime();
    }

    #[Test]
    public function it_lists_the_versions_five_most_recent_reports_newest_first(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $reports = collect(range(1, 6))->map(
            fn (int $day): Report => Report::factory()->forGameVersion($gameVersion)->create(['start_time' => now()->subDays($day)]),
        );
        Report::factory()->forGameVersion(GameVersion::factory()->forGuild()->create(['release_date' => Carbon::parse('2023-01-01')]))
            ->create(['start_time' => now()]);

        $listed = ListRecentReports::run($gameVersion, new Request);

        $this->assertSame($reports->take(5)->pluck('id')->all(), array_column($listed, 'id'));
    }

    #[Test]
    public function it_returns_nothing_without_a_game_version(): void
    {
        Report::factory()->create();

        $this->assertSame([], ListRecentReports::run(null, new Request));
    }

    #[Test]
    public function it_caches_each_versions_reports_separately(): void
    {
        $first = GameVersion::factory()->forGuild()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $second = GameVersion::factory()->forGuild()->create(['release_date' => Carbon::parse('2023-01-01')]);
        $firstReport = Report::factory()->forGameVersion($first)->create();
        $secondReport = Report::factory()->forGameVersion($second)->create();

        ListRecentReports::run($first, new Request);

        $this->assertSame([$secondReport->id], array_column(ListRecentReports::run($second, new Request), 'id'));
        $this->assertSame([$firstReport->id], array_column(ListRecentReports::run($first, new Request), 'id'));
    }
}
