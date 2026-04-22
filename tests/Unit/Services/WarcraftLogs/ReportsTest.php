<?php

namespace Tests\Unit\Services\WarcraftLogs;

use App\Models\WarcraftLogs\GuildTag;
use App\Services\WarcraftLogs\AuthenticationHandler;
use App\Services\WarcraftLogs\Reports;
use App\Services\WarcraftLogs\ValueObjects\ReportData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::shouldReceive('tags')
            ->zeroOrMoreTimes()
            ->andReturnSelf();

        Cache::shouldReceive('flush')
            ->zeroOrMoreTimes();
    }

    protected function getService(array $configOverrides = []): Reports
    {
        $config = array_merge([
            'client_id' => 'test_client_id',
            'client_secret' => 'test_client_secret',
            'guild_id' => 774848,
        ], $configOverrides);

        $auth = new AuthenticationHandler($config['client_id'], $config['client_secret']);

        return new Reports($config, $auth);
    }

    protected function fakeAuthToken(): void
    {
        Cache::shouldReceive('get')
            ->with('warcraftlogs:client_token', \Mockery::type('callable'))
            ->andReturn('test_access_token');
    }

    protected function fakeNotRateLimited(): void
    {
        Cache::shouldReceive('has')
            ->with('warcraftlogs:rate_limited')
            ->andReturn(false);
    }

    protected function fakeCachePassthrough(): void
    {
        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });
    }

    protected function fakeRateLimitHeaders(): void
    {
        Cache::shouldReceive('put')
            ->with('warcraftlogs:rate_limit', \Mockery::type('array'), 3600);
    }

    protected function makeReportData(string $code, string $title, float $startTime, float $endTime, ?array $zone = null): array
    {
        $data = [
            'code' => $code,
            'title' => $title,
            'startTime' => $startTime,
            'endTime' => $endTime,
        ];

        if ($zone !== null) {
            $data['zone'] = $zone;
        }

        return $data;
    }

    protected function fakeReportsResponse(array $reports, bool $hasMorePages = false, int $currentPage = 1): array
    {
        return [
            'data' => [
                'reportData' => [
                    'reports' => [
                        'data' => $reports,
                        'current_page' => $currentPage,
                        'has_more_pages' => $hasMorePages,
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function get_fetches_reports_for_single_guild_tag(): void
    {
        Http::preventStrayRequests();

        $report1 = $this->makeReportData('ABC123', 'Karazhan', 1771611168498, 1771625431211, ['id' => 1047, 'name' => 'Karazhan']);
        $report2 = $this->makeReportData('DEF456', 'Gruul', 1771612483423, 1771626471711, ['id' => 1048, 'name' => "Gruul's Lair"]);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                $this->fakeReportsResponse([$report1, $report2]),
                200,
                ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '799'],
            ),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $guildTag = GuildTag::factory()->create();

        $service = $this->getService();
        $results = $service->byGuildTags(collect([$guildTag]))->get();

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(ReportData::class, $results);

        // Results should be sorted by startTime descending
        $this->assertEquals('DEF456', $results->first()->code);
        $this->assertEquals('ABC123', $results->last()->code);

        Http::assertSent(function ($request) use ($guildTag) {
            $body = $request->data();

            return str_contains($request->url(), 'api/v2/client')
                && str_contains($body['query'], 'guildTagID')
                && $body['variables']['guildTagID'] === $guildTag->id;
        });
    }

    #[Test]
    public function get_fetches_reports_for_multiple_guild_tags(): void
    {
        Http::preventStrayRequests();

        $report1 = $this->makeReportData('ABC123', 'Karazhan', 1771611168498, 1771625431211);
        $report2 = $this->makeReportData('DEF456', 'Gruul', 1771612483423, 1771626471711);
        $report3 = $this->makeReportData('GHI789', 'Magtheridon', 1771700000000, 1771710000000);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push($this->fakeReportsResponse([$report1, $report2]), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '798'])
                ->push($this->fakeReportsResponse([$report2, $report3]), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '797']),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $tag1 = GuildTag::factory()->create();
        $tag2 = GuildTag::factory()->create();

        $service = $this->getService();
        $results = $service->byGuildTags(collect([$tag1, $tag2]))->get();

        // DEF456 appears in both tags but should be deduplicated
        $this->assertCount(3, $results);
        $codes = $results->pluck('code')->all();
        $this->assertContains('ABC123', $codes);
        $this->assertContains('DEF456', $codes);
        $this->assertContains('GHI789', $codes);
    }

    #[Test]
    public function get_deduplicates_reports_across_tags(): void
    {
        Http::preventStrayRequests();

        $sharedReport = $this->makeReportData('SHARED1', 'Karazhan', 1771611168498, 1771625431211);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push($this->fakeReportsResponse([$sharedReport]), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '798'])
                ->push($this->fakeReportsResponse([$sharedReport]), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '797']),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $tag1 = GuildTag::factory()->create();
        $tag2 = GuildTag::factory()->create();

        $service = $this->getService();
        $results = $service->byGuildTags(collect([$tag1, $tag2]))->get();

        $this->assertCount(1, $results);
        $this->assertEquals('SHARED1', $results->first()->code);
    }

    #[Test]
    public function get_with_start_and_end_time_filters(): void
    {
        Http::preventStrayRequests();

        $report = $this->makeReportData('ABC123', 'Karazhan', 1771611168498, 1771625431211);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                $this->fakeReportsResponse([$report]),
                200,
                ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '799'],
            ),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $startTime = Carbon::createFromTimestampMs(1771600000000);
        $endTime = Carbon::createFromTimestampMs(1771700000000);

        $guildTag = GuildTag::factory()->create();

        $service = $this->getService();
        $service->byGuildTags(collect([$guildTag]))
            ->startTime($startTime)
            ->endTime($endTime)
            ->get();

        Http::assertSent(function ($request) {
            $variables = $request->data()['variables'];

            return isset($variables['startTime'])
                && $variables['startTime'] === 1771600000000.0
                && isset($variables['endTime'])
                && $variables['endTime'] === 1771700000000.0;
        });
    }

    #[Test]
    public function get_paginates_through_all_pages(): void
    {
        Http::preventStrayRequests();

        $page1Reports = [$this->makeReportData('PAGE1A', 'Kara 1', 1771611168498, 1771625431211)];
        $page2Reports = [$this->makeReportData('PAGE2A', 'Kara 2', 1771700000000, 1771710000000)];

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push($this->fakeReportsResponse($page1Reports, hasMorePages: true, currentPage: 1), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '798'])
                ->push($this->fakeReportsResponse($page2Reports, hasMorePages: false, currentPage: 2), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '797']),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $guildTag = GuildTag::factory()->create();

        $service = $this->getService();
        $results = $service->byGuildTags(collect([$guildTag]))->get();

        $this->assertCount(2, $results);
        $codes = $results->pluck('code')->all();
        $this->assertContains('PAGE1A', $codes);
        $this->assertContains('PAGE2A', $codes);
    }

    #[Test]
    public function get_falls_back_to_guild_id_when_no_tags(): void
    {
        Http::preventStrayRequests();

        $report = $this->makeReportData('ABC123', 'Karazhan', 1771611168498, 1771625431211);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                $this->fakeReportsResponse([$report]),
                200,
                ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '799'],
            ),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $service = $this->getService(['guild_id' => 774848]);
        $results = $service->get();

        $this->assertCount(1, $results);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($body['query'], 'guildID')
                && ! str_contains($body['query'], 'guildTagID')
                && $body['variables']['guildID'] === 774848;
        });
    }

    #[Test]
    public function results_cached_for_five_minutes(): void
    {
        $this->fakeAuthToken();
        $this->fakeNotRateLimited();

        Cache::shouldReceive('remember')
            ->once()
            ->with(\Mockery::type('string'), 300, \Mockery::type('callable'))
            ->andReturn([
                'reportData' => [
                    'reports' => [
                        'data' => [],
                        'current_page' => 1,
                        'has_more_pages' => false,
                    ],
                ],
            ]);

        $service = $this->getService();
        $service->get();
    }

    #[Test]
    public function by_guild_tags_accepts_collection_of_guild_tag_models(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push($this->fakeReportsResponse([]), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '798'])
                ->push($this->fakeReportsResponse([]), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '797']),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $tag1 = GuildTag::factory()->create();
        $tag2 = GuildTag::factory()->create();

        $service = $this->getService();
        $result = $service->byGuildTags(collect([$tag1, $tag2]));

        $this->assertInstanceOf(Reports::class, $result);

        // Trigger the actual query to verify both tags are queried
        $result->get();

        Http::assertSentCount(2);
    }

    // ==================== lazy() Tests ====================

    #[Test]
    public function lazy_returns_lazy_collection(): void
    {
        Http::preventStrayRequests();

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                $this->fakeReportsResponse([]),
                200,
                ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '799'],
            ),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $service = $this->getService();
        $result = $service->lazy();

        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    #[Test]
    public function lazy_yields_report_records(): void
    {
        Http::preventStrayRequests();

        $report1 = $this->makeReportData('ABC123', 'Karazhan', 1771611168498, 1771625431211);
        $report2 = $this->makeReportData('DEF456', 'Gruul', 1771612483423, 1771626471711);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                $this->fakeReportsResponse([$report1, $report2]),
                200,
                ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '799'],
            ),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $service = $this->getService();
        $records = $service->lazy()->all();

        $this->assertCount(2, $records);
        $this->assertContainsOnlyInstancesOf(ReportData::class, $records);
    }

    #[Test]
    public function lazy_with_guild_tags_fetches_from_api(): void
    {
        Http::preventStrayRequests();

        $report1 = $this->makeReportData('ABC123', 'Karazhan', 1771611168498, 1771625431211);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                $this->fakeReportsResponse([$report1]),
                200,
                ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '799'],
            ),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $guildTag = GuildTag::factory()->create();

        $service = $this->getService();
        $records = $service->byGuildTags(collect([$guildTag]))->lazy()->all();

        $this->assertCount(1, $records);
        $this->assertEquals('ABC123', $records[0]->code);
    }

    #[Test]
    public function lazy_with_multiple_tags_deduplicates(): void
    {
        Http::preventStrayRequests();

        $sharedReport = $this->makeReportData('SHARED1', 'Karazhan', 1771611168498, 1771625431211);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push($this->fakeReportsResponse([$sharedReport]), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '798'])
                ->push($this->fakeReportsResponse([$sharedReport]), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '797']),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $tag1 = GuildTag::factory()->create();
        $tag2 = GuildTag::factory()->create();

        $service = $this->getService();
        $records = $service->byGuildTags(collect([$tag1, $tag2]))->lazy()->all();

        $this->assertCount(1, $records);
        $this->assertEquals('SHARED1', $records[0]->code);
    }

    #[Test]
    public function lazy_paginates_through_all_pages(): void
    {
        Http::preventStrayRequests();

        $page1Reports = [$this->makeReportData('PAGE1A', 'Kara 1', 1771611168498, 1771625431211)];
        $page2Reports = [$this->makeReportData('PAGE2A', 'Kara 2', 1771700000000, 1771710000000)];

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::sequence()
                ->push($this->fakeReportsResponse($page1Reports, hasMorePages: true, currentPage: 1), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '798'])
                ->push($this->fakeReportsResponse($page2Reports, hasMorePages: false, currentPage: 2), 200, ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '797']),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $guildTag = GuildTag::factory()->create();

        $service = $this->getService();
        $records = $service->byGuildTags(collect([$guildTag]))->lazy()->all();

        $this->assertCount(2, $records);
        $codes = array_map(fn (ReportData $r) => $r->code, $records);
        $this->assertContains('PAGE1A', $codes);
        $this->assertContains('PAGE2A', $codes);
    }

    #[Test]
    public function lazy_falls_back_to_guild_id_when_no_tags(): void
    {
        Http::preventStrayRequests();

        $report = $this->makeReportData('ABC123', 'Karazhan', 1771611168498, 1771625431211);

        Http::fake([
            'www.warcraftlogs.com/api/v2/client*' => Http::response(
                $this->fakeReportsResponse([$report]),
                200,
                ['x-ratelimit-limit' => '800', 'x-ratelimit-remaining' => '799'],
            ),
        ]);

        $this->fakeAuthToken();
        $this->fakeNotRateLimited();
        $this->fakeCachePassthrough();
        $this->fakeRateLimitHeaders();

        $service = $this->getService(['guild_id' => 774848]);
        $records = $service->lazy()->all();

        $this->assertCount(1, $records);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($body['query'], 'guildID')
                && ! str_contains($body['query'], 'guildTagID')
                && $body['variables']['guildID'] === 774848;
        });
    }
}
