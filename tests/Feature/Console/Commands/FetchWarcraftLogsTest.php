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
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
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
    public function it_fetches_guild_tags_and_queues_a_named_report_batch_per_guild(): void
    {
        Bus::fake();

        $first = $this->guild(111);
        $second = $this->guild(222);
        $firstTag = $this->guildTagFor($first);
        $secondTags = [$this->guildTagFor($second), $this->guildTagFor($second)];

        $this->artisan('fetch:warcraft-logs')
            ->expectsOutputToContain('Fetching Warcraft Logs data for guild 111')
            ->expectsOutputToContain('Fetching Warcraft Logs data for guild 222')
            ->assertSuccessful();

        Bus::assertDispatchedSync(FetchGuildTags::class, fn (FetchGuildTags $job): bool => $job->guild->is($first));
        Bus::assertDispatchedSync(FetchGuildTags::class, fn (FetchGuildTags $job): bool => $job->guild->is($second));
        Bus::assertBatchCount(2);
        $this->assertSame([$firstTag->id], $this->batchedTagIds($this->batchFor($first)));
        $this->assertSame(collect($secondTags)->pluck('id')->sort()->values()->all(), $this->batchedTagIds($this->batchFor($second)));
    }

    #[Test]
    public function it_fetches_a_guild_shared_by_two_game_versions_once(): void
    {
        Bus::fake();

        $guild = $this->guild(111);
        GameVersion::factory()->forGuild($guild)->count(2)->create();
        $this->guildTagFor($guild);

        $this->artisan('fetch:warcraft-logs')
            ->expectsOutputToContain('Warcraft Logs fetch complete: 1 queued, 0 skipped, 0 failed.')
            ->assertSuccessful();

        Bus::assertDispatchedSyncTimes(FetchGuildTags::class, 1);
        Bus::assertBatchCount(1);
    }

    #[Test]
    public function it_dispatches_attendance_for_the_guild_when_its_batch_completes(): void
    {
        Bus::fake();

        $guild = $this->guild(111);
        $this->guildTagFor($guild);

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        foreach ($this->batchFor($guild)->thenCallbacks() as $callback) {
            $callback($this->createStub(Batch::class));
        }

        Bus::assertDispatched(FetchAttendanceData::class, fn (FetchAttendanceData $job): bool => $job->guild->is($guild));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_the_failure_when_a_report_batch_fails(): void
    {
        Bus::fake();
        Log::spy();

        $guild = $this->guild(111);
        $this->guildTagFor($guild);

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        foreach ($this->batchFor($guild)->catchCallbacks() as $callback) {
            $callback($this->createStub(Batch::class), new RuntimeException('boom'));
        }

        Log::shouldHaveReceived('error')->once()->with('Warcraft Logs batch for guild 111 failed: boom');
    }

    #[Test]
    public function it_warns_about_game_versions_without_a_guild(): void
    {
        Bus::fake();

        $this->guild(111);
        $noGuild = GameVersion::factory()->create(['warcraft_logs_guild_id' => null]);

        $this->artisan('fetch:warcraft-logs')
            ->expectsOutputToContain("Skipping {$noGuild->title}: no Warcraft Logs guild.")
            ->expectsOutputToContain('Warcraft Logs fetch complete: 1 queued, 1 skipped, 0 failed.')
            ->assertSuccessful();

        Bus::assertDispatchedSyncTimes(FetchGuildTags::class, 1);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_dispatches_attendance_directly_when_a_guild_has_no_tags_to_fetch(): void
    {
        Bus::fake();

        $guild = $this->guild(111);

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        Bus::assertNothingBatched();
        Bus::assertDispatched(FetchAttendanceData::class, fn (FetchAttendanceData $job): bool => $job->guild->is($guild));
    }

    // ==================== tag selection ====================

    #[Test]
    public function it_queues_every_tag_of_the_guild_and_no_other(): void
    {
        Bus::fake();

        $guild = $this->guild(111);
        $countingTag = $this->guildTagFor($guild);
        $nonCountingTag = $this->guildTagFor($guild, countsAttendance: false);
        $this->guildTagFor($this->guild(222));
        GuildTag::factory()->countsAttendance()->create();

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        $this->assertSame(
            collect([$countingTag, $nonCountingTag])->pluck('id')->sort()->values()->all(),
            $this->batchedTagIds($this->batchFor($guild)),
        );
    }

    // ==================== latest option ====================

    #[Test]
    public function it_passes_null_since_when_latest_flag_is_absent(): void
    {
        Bus::fake();

        $guild = $this->guild(111);
        $this->guildTagFor($guild);
        Report::factory()->create(['end_time' => now()->subHour()]);

        $this->artisan('fetch:warcraft-logs')->assertSuccessful();

        $this->assertNull($this->batchFor($guild)->jobs->first()->since);
    }

    #[Test]
    public function it_uses_the_latest_report_end_time_plus_one_second_as_since(): void
    {
        Bus::fake();

        $guild = $this->guild(111);
        $this->guildTagFor($guild);
        $endTime = Carbon::parse('2025-06-01 20:00:00');
        Report::factory()->forGuild($guild)->create(['end_time' => $endTime]);

        $this->artisan('fetch:warcraft-logs', ['--latest' => true])->assertSuccessful();

        $this->assertTrue($this->batchFor($guild)->jobs->first()->since->eq($endTime->copy()->addSecond()));
    }

    #[Test]
    public function it_passes_null_since_when_latest_flag_is_set_but_no_reports_exist(): void
    {
        Bus::fake();

        $guild = $this->guild(111);
        $this->guildTagFor($guild);

        $this->artisan('fetch:warcraft-logs', ['--latest' => true])->assertSuccessful();

        $this->assertNull($this->batchFor($guild)->jobs->first()->since);
    }

    #[Test]
    public function it_uses_the_report_that_ended_last_rather_than_the_one_created_last(): void
    {
        Bus::fake();

        $guild = $this->guild(111);
        $this->guildTagFor($guild);
        $newerEndTime = Carbon::parse('2025-06-15 22:00:00');
        Report::factory()->forGuild($guild)->create(['end_time' => $newerEndTime, 'created_at' => now()->subMinute()]);
        Report::factory()->forGuild($guild)->create(['end_time' => Carbon::parse('2025-05-01 18:00:00'), 'created_at' => now()]);

        $this->artisan('fetch:warcraft-logs', ['--latest' => true])->assertSuccessful();

        $this->assertTrue($this->batchFor($guild)->jobs->first()->since->eq($newerEndTime->copy()->addSecond()));
    }

    #[Test]
    public function it_works_out_since_separately_for_each_guild(): void
    {
        Bus::fake();

        $first = $this->guild(111);
        $second = $this->guild(222);
        $this->guildTagFor($first);
        $this->guildTagFor($second);
        $endTime = Carbon::parse('2025-06-01 20:00:00');
        Report::factory()->forGuild($first)->create(['end_time' => $endTime]);

        $this->artisan('fetch:warcraft-logs', ['--latest' => true])->assertSuccessful();

        $this->assertTrue($this->batchFor($first)->jobs->first()->since->eq($endTime->copy()->addSecond()));
        $this->assertNull($this->batchFor($second)->jobs->first()->since);
    }

    // ==================== guild tag failures ====================

    #[Test]
    #[Group('error-handling')]
    public function it_reports_a_missing_guild_and_still_processes_the_next_guild(): void
    {
        Bus::fake([FetchReportsByGuildTag::class, FetchAttendanceData::class]);
        Exceptions::fake();

        $this->guild(111);
        $healthy = $this->guild(222);
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
            ->expectsOutputToContain('Failed to fetch guild tags for guild 111')
            ->expectsOutputToContain('Warcraft Logs fetch complete: 1 queued, 0 skipped, 1 failed.')
            ->assertSuccessful();

        Exceptions::assertReported(GuildNotFoundException::class);
        Bus::assertBatchCount(1);
        $this->assertNotNull($this->batchFor($healthy));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_reports_a_connection_failure_and_still_processes_the_next_guild(): void
    {
        Bus::fake([FetchReportsByGuildTag::class, FetchAttendanceData::class]);
        Exceptions::fake();

        $this->guild(111);
        $healthy = $this->guild(222);
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
            ->expectsOutputToContain('Failed to fetch guild tags for guild 111: Connection refused')
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

        $this->guild(111);
        $this->guildTagFor($this->guild(222));
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

    private function guild(int $id): Guild
    {
        return Guild::factory()->create(['id' => $id, 'namespace' => WarcraftLogsNamespace::Anniversary]);
    }

    private function guildTagFor(Guild $guild, bool $countsAttendance = true): GuildTag
    {
        $factory = GuildTag::factory()->forGuild($guild);

        if ($countsAttendance) {
            return $factory->countsAttendance()->create();
        }

        return $factory->doesNotCountAttendance()->create();
    }

    private function batchFor(Guild $guild): ?PendingBatch
    {
        return collect(Bus::dispatchedBatches())
            ->first(fn (PendingBatch $batch): bool => $batch->name === "warcraftlogs:guild:{$guild->id}");
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
