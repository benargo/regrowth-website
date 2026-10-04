<?php

namespace Tests\Feature\Console\Commands;

use App\Http\Integrations\WarcraftLogs\Data\RateLimit\RateLimitData;
use App\Http\Integrations\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Http\Integrations\WarcraftLogs\RateLimitResetCache;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildTagsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Jobs\WarcraftLogs\FetchAttendanceData;
use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Jobs\WarcraftLogs\FetchReportsByGuildTag;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Report;
use App\Models\WarcraftLogs\GuildTag;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Concerns\FakesWarcraftLogs;
use Tests\TestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class FetchWarcraftLogsTest extends TestCase
{
    use FakesWarcraftLogs;
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_fetches_guild_tags_and_queues_a_named_report_batch_per_game_version(): void
    {
        Bus::fake();

        $first = $this->fetchableGameVersion(111);
        $second = $this->fetchableGameVersion(222);
        $firstTag = $this->guildTagFor($first);
        $secondTags = [$this->guildTagFor($second), $this->guildTagFor($second)];

        $this->artisan('fetch:warcraft-logs')
            ->expectsOutputToContain("Fetching Warcraft Logs data for {$first->title}")
            ->expectsOutputToContain("Fetching Warcraft Logs data for {$second->title}")
            ->assertSuccessful();

        Bus::assertDispatchedSync(FetchGuildTags::class, fn (FetchGuildTags $job): bool => $job->gameVersion->is($first));
        Bus::assertDispatchedSync(FetchGuildTags::class, fn (FetchGuildTags $job): bool => $job->gameVersion->is($second));
        Bus::assertBatchCount(2);
        $this->assertSame([$firstTag->id], $this->batchedTagIds($this->batchFor($first)));
        $this->assertSame(collect($secondTags)->pluck('id')->sort()->values()->all(), $this->batchedTagIds($this->batchFor($second)));
    }

    #[Test]
    public function it_dispatches_attendance_for_the_version_when_its_batch_completes(): void
    {
        Bus::fake();

        $gameVersion = $this->fetchableGameVersion(111);
        $this->guildTagFor($gameVersion);

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        foreach ($this->batchFor($gameVersion)->thenCallbacks() as $callback) {
            $callback($this->createStub(Batch::class));
        }

        Bus::assertDispatched(FetchAttendanceData::class, fn (FetchAttendanceData $job): bool => $job->gameVersion->is($gameVersion));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_the_failure_when_a_report_batch_fails(): void
    {
        Bus::fake();
        Log::spy();

        $gameVersion = $this->fetchableGameVersion(111);
        $this->guildTagFor($gameVersion);

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        foreach ($this->batchFor($gameVersion)->catchCallbacks() as $callback) {
            $callback($this->createStub(Batch::class), new RuntimeException('boom'));
        }

        Log::shouldHaveReceived('error')->once()->with("Warcraft Logs batch for game version {$gameVersion->id} failed: boom");
    }

    #[Test]
    public function it_skips_and_reports_game_versions_without_warcraft_logs_fields(): void
    {
        Bus::fake();

        $fetchable = $this->fetchableGameVersion(111);
        $noGuild = GameVersion::factory()->create(['warcraftlogs_guild' => null]);
        $noNamespace = GameVersion::factory()->create(['warcraftlogs_namespace' => null]);

        $this->artisan('fetch:warcraft-logs')
            ->expectsOutputToContain("Skipping {$noGuild->title}: no Warcraft Logs guild or namespace.")
            ->expectsOutputToContain("Skipping {$noNamespace->title}: no Warcraft Logs guild or namespace.")
            ->expectsOutputToContain('Warcraft Logs fetch complete: 1 queued, 2 skipped, 0 failed.')
            ->assertSuccessful();

        Bus::assertDispatchedSyncTimes(FetchGuildTags::class, 1);
        Bus::assertDispatchedSync(FetchGuildTags::class, fn (FetchGuildTags $job): bool => $job->gameVersion->is($fetchable));
    }

    #[Test]
    #[Group('edge-case')]
    public function it_dispatches_attendance_directly_when_a_version_has_no_tags_to_fetch(): void
    {
        Bus::fake();

        $gameVersion = $this->fetchableGameVersion(111);

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        Bus::assertNothingBatched();
        Bus::assertDispatched(FetchAttendanceData::class, fn (FetchAttendanceData $job): bool => $job->gameVersion->is($gameVersion));
    }

    // ==================== tag selection ====================

    #[Test]
    public function it_queues_every_phase_linked_tag_of_the_version(): void
    {
        Bus::fake();

        $gameVersion = $this->fetchableGameVersion(111);
        $otherVersion = GameVersion::factory()->create(['warcraftlogs_guild' => null]);
        $countingTag = $this->guildTagFor($gameVersion);
        $nonCountingTag = $this->guildTagFor($gameVersion, countsAttendance: false);
        $this->guildTagFor($otherVersion);
        GuildTag::factory()->withoutPhase()->countsAttendance()->create();

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        Bus::assertBatchCount(1);
        $this->assertSame(
            collect([$countingTag, $nonCountingTag])->pluck('id')->sort()->values()->all(),
            $this->batchedTagIds($this->batchFor($gameVersion)),
        );
    }

    // ==================== latest option ====================

    #[Test]
    public function it_passes_null_since_when_latest_flag_is_absent(): void
    {
        Bus::fake();

        $gameVersion = $this->fetchableGameVersion(111);
        $this->guildTagFor($gameVersion);
        Report::factory()->create(['end_time' => now()->subHour()]);

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        $this->assertNull($this->batchFor($gameVersion)->jobs->first()->since);
    }

    #[Test]
    public function it_uses_the_latest_report_end_time_plus_one_second_as_since(): void
    {
        Bus::fake();

        $gameVersion = $this->fetchableGameVersion(111);
        $this->guildTagFor($gameVersion);
        $endTime = Carbon::parse('2025-06-01 20:00:00');
        Report::factory()->create(['end_time' => $endTime]);

        $this->artisan('fetch:warcraft-logs', ['--latest' => true])->assertSuccessful();

        $this->assertTrue($this->batchFor($gameVersion)->jobs->first()->since->eq($endTime->copy()->addSecond()));
    }

    #[Test]
    public function it_passes_null_since_when_latest_flag_is_set_but_no_reports_exist(): void
    {
        Bus::fake();

        $gameVersion = $this->fetchableGameVersion(111);
        $this->guildTagFor($gameVersion);

        $this->artisan('fetch:warcraft-logs', ['--latest' => true])->assertSuccessful();

        $this->assertNull($this->batchFor($gameVersion)->jobs->first()->since);
    }

    #[Test]
    public function it_uses_the_most_recently_created_report_when_multiple_reports_exist(): void
    {
        Bus::fake();

        $gameVersion = $this->fetchableGameVersion(111);
        $this->guildTagFor($gameVersion);
        $newerEndTime = Carbon::parse('2025-06-15 22:00:00');
        Report::factory()->create(['end_time' => Carbon::parse('2025-05-01 18:00:00'), 'created_at' => now()->subMinute()]);
        Report::factory()->create(['end_time' => $newerEndTime, 'created_at' => now()]);

        $this->artisan('fetch:warcraft-logs', ['--latest' => true])->assertSuccessful();

        $this->assertTrue($this->batchFor($gameVersion)->jobs->first()->since->eq($newerEndTime->copy()->addSecond()));
    }

    #[Test]
    public function it_uses_the_same_since_for_every_game_version(): void
    {
        // Pins today's semantics (spec Review Focus 2): "since" is the newest report
        // across all versions, not per version. A follow-up changes this on purpose.
        Bus::fake();

        $first = $this->fetchableGameVersion(111);
        $second = $this->fetchableGameVersion(222);
        $firstTag = $this->guildTagFor($first);
        $this->guildTagFor($second);
        $endTime = Carbon::parse('2025-06-01 20:00:00');
        Report::factory()->withGuildTag($firstTag)->create(['end_time' => $endTime]);

        $this->artisan('fetch:warcraft-logs', ['--latest' => true])->assertSuccessful();

        $expectedSince = $endTime->copy()->addSecond();
        $this->assertTrue($this->batchFor($first)->jobs->first()->since->eq($expectedSince));
        $this->assertTrue($this->batchFor($second)->jobs->first()->since->eq($expectedSince));
    }

    // ==================== guild tag failures ====================

    #[Test]
    #[Group('error-handling')]
    public function it_reports_a_missing_guild_and_still_processes_the_next_version(): void
    {
        Bus::fake([FetchReportsByGuildTag::class, FetchAttendanceData::class]);
        Exceptions::fake();

        $missing = $this->fetchableGameVersion(111);
        $healthy = $this->fetchableGameVersion(222);
        $this->guildTagFor($healthy);

        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => function (PendingRequest $pendingRequest): MockResponse {
                if (data_get($pendingRequest->body()->all(), 'variables.id') === 111) {
                    return MockResponse::make(['data' => ['guildData' => ['guild' => null]]]);
                }

                return MockResponse::make(['data' => ['guildData' => ['guild' => ['id' => 222, 'tags' => []]]]]);
            },
        ]);

        $this->artisan('fetch:warcraft-logs')
            ->expectsOutputToContain("Failed to fetch guild tags for {$missing->title}")
            ->expectsOutputToContain('Warcraft Logs fetch complete: 1 queued, 0 skipped, 1 failed.')
            ->assertSuccessful();

        Exceptions::assertReported(GuildNotFoundException::class);
        Bus::assertBatchCount(1);
        $this->assertNotNull($this->batchFor($healthy));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_reports_a_connection_failure_and_still_processes_the_next_version(): void
    {
        Bus::fake([FetchReportsByGuildTag::class, FetchAttendanceData::class]);
        Exceptions::fake();

        $unreachable = $this->fetchableGameVersion(111);
        $healthy = $this->fetchableGameVersion(222);
        $this->guildTagFor($healthy);

        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => function (PendingRequest $pendingRequest): MockResponse {
                if (data_get($pendingRequest->body()->all(), 'variables.id') === 111) {
                    return MockResponse::make()->throw(fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Connection refused'), $pendingRequest));
                }

                return MockResponse::make(['data' => ['guildData' => ['guild' => ['id' => 222, 'tags' => []]]]]);
            },
        ]);

        $this->artisan('fetch:warcraft-logs')
            ->expectsOutputToContain("Failed to fetch guild tags for {$unreachable->title}: Connection refused")
            ->expectsOutputToContain('Warcraft Logs fetch complete: 1 queued, 0 skipped, 1 failed.')
            ->assertSuccessful();

        Exceptions::assertReported(FatalRequestException::class);
        $this->assertNotNull($this->batchFor($healthy));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_stops_at_the_rate_limit_and_prints_when_the_points_reset(): void
    {
        $this->freezeTime();
        Bus::fake([FetchReportsByGuildTag::class, FetchAttendanceData::class]);

        $this->fetchableGameVersion(111);
        $this->guildTagFor($this->fetchableGameVersion(222));
        $this->app->make(RateLimitResetCache::class)->put(
            new RateLimitData(limitPerHour: 3600, pointsSpentThisHour: 3600.0, pointsResetIn: 1200),
        );

        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make(['error' => 'Too Many Requests'], 429),
        ]);

        $resetsAt = now()->addSeconds(1200)->toDateTimeString();

        $this->artisan('fetch:warcraft-logs')
            ->expectsOutputToContain("Warcraft Logs rate limit reached; points reset at {$resetsAt}.")
            ->expectsOutputToContain('Warcraft Logs fetch complete: 0 queued, 1 skipped, 1 failed.')
            ->assertSuccessful();

        Saloon::mockClient()->assertSentCount(1, GetGuildTagsRequest::class);
        Bus::assertNothingBatched();
        Bus::assertNotDispatched(FetchAttendanceData::class);
    }

    // ==================== helpers ====================

    private function fetchableGameVersion(int $warcraftLogsGuild): GameVersion
    {
        return GameVersion::factory()->create([
            'warcraftlogs_guild' => $warcraftLogsGuild,
            'warcraftlogs_namespace' => WarcraftLogsNamespace::Anniversary,
        ]);
    }

    private function guildTagFor(GameVersion $gameVersion, bool $countsAttendance = true): GuildTag
    {
        $factory = GuildTag::factory()->withPhase(Phase::factory()->forGameVersion($gameVersion)->create());

        if ($countsAttendance) {
            return $factory->countsAttendance()->create();
        }

        return $factory->doesNotCountAttendance()->create();
    }

    private function batchFor(GameVersion $gameVersion): ?PendingBatch
    {
        return collect(Bus::dispatchedBatches())
            ->first(fn (PendingBatch $batch): bool => $batch->name === "warcraftlogs:{$gameVersion->id}");
    }

    /**
     * @return array<int, int>
     */
    private function batchedTagIds(PendingBatch $batch): array
    {
        return $batch->jobs
            ->map(fn (FetchReportsByGuildTag $job): int => $job->guildTag->id)
            ->sort()
            ->values()
            ->all();
    }
}
