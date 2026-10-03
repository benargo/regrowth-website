<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Data\Reports\ReportData;
use App\Http\Integrations\WarcraftLogs\Requests\GetReportsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Tests\Unit\Http\Integrations\WarcraftLogs\WarcraftLogsTestCase;

#[Group('warcraftlogs-integration')]
class GetReportsRequestTest extends WarcraftLogsTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_queries_reports_for_the_guild_tag_on_the_namespace_host(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetReportsRequest::class => MockResponse::make($this->page([$this->report('aaa')], hasMorePages: false)),
        ]);

        $reports = $this->makeConnector()
            ->send(new GetReportsRequest(1234, WarcraftLogsNamespace::Classic))
            ->dto();

        $this->assertCount(1, $reports);
        $this->assertContainsOnlyInstancesOf(ReportData::class, $reports);
        $this->assertSame('aaa', $reports[0]->code);

        Saloon::assertSent(function (Request $request, Response $response): bool {
            if (! $request instanceof GetReportsRequest) {
                return false;
            }

            return $response->getPendingRequest()->getUrl() === 'https://classic.warcraftlogs.com/api/v2/client'
                && $request->body()->all()['variables'] === ['guildTagID' => 1234, 'page' => 1, 'limit' => 100];
        });
    }

    #[Test]
    public function it_omits_the_time_window_when_none_is_given(): void
    {
        $body = (new GetReportsRequest(1234, WarcraftLogsNamespace::Classic))->body()->all();

        $this->assertArrayNotHasKey('startTime', $body['variables']);
        $this->assertArrayNotHasKey('endTime', $body['variables']);
        $this->assertStringNotContainsString('$startTime', $body['query']);
        $this->assertStringNotContainsString('$endTime', $body['query']);
    }

    #[Test]
    public function it_sends_the_time_window_as_epoch_millisecond_floats(): void
    {
        $request = new GetReportsRequest(
            1234,
            WarcraftLogsNamespace::Classic,
            startTime: Carbon::createFromTimestampMs(1700000000123),
            endTime: Carbon::createFromTimestampMs(1700003600456),
        );

        $body = $request->body()->all();

        $this->assertSame(1700000000123.0, $body['variables']['startTime']);
        $this->assertSame(1700003600456.0, $body['variables']['endTime']);
        $this->assertStringContainsString('$startTime: Float', $body['query']);
        $this->assertStringContainsString('startTime: $startTime', $body['query']);
        $this->assertStringContainsString('$endTime: Float', $body['query']);
        $this->assertStringContainsString('endTime: $endTime', $body['query']);
    }

    #[Test]
    public function it_sends_only_the_start_time_when_no_end_time_is_given(): void
    {
        $body = (new GetReportsRequest(1234, WarcraftLogsNamespace::Classic, startTime: Carbon::createFromTimestampMs(1700000000123)))
            ->body()
            ->all();

        $this->assertSame(1700000000123.0, $body['variables']['startTime']);
        $this->assertArrayNotHasKey('endTime', $body['variables']);
        $this->assertStringNotContainsString('$endTime', $body['query']);
    }

    #[Test]
    #[Group('contract')]
    public function it_is_paginatable_and_cached_for_five_minutes(): void
    {
        $request = new GetReportsRequest(1234, WarcraftLogsNamespace::Classic);

        $this->assertInstanceOf(Paginatable::class, $request);
        $this->assertSame(300, $request->cacheExpiryInSeconds());
    }

    #[Test]
    public function it_iterates_every_page_and_caches_each_page_separately(): void
    {
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetReportsRequest::class => fn (PendingRequest $pendingRequest): MockResponse => $pendingRequest->body()->all()['variables']['page'] === 1
                ? MockResponse::make($this->page([$this->report('aaa'), $this->report('bbb')], hasMorePages: true))
                : MockResponse::make($this->page([$this->report('ccc')], hasMorePages: false)),
        ]);
        $connector = $this->makeConnector();

        $first = iterator_to_array($connector->paginate(new GetReportsRequest(1234, WarcraftLogsNamespace::Classic))->items(), false);
        $second = iterator_to_array($connector->paginate(new GetReportsRequest(1234, WarcraftLogsNamespace::Classic))->items(), false);

        $this->assertSame(['aaa', 'bbb', 'ccc'], array_map(fn (ReportData $report): string => $report->code, $first));
        $this->assertSame(['aaa', 'bbb', 'ccc'], array_map(fn (ReportData $report): string => $report->code, $second));
        $mockClient->assertSentCount(2, GetReportsRequest::class);
    }

    /**
     * @param  array<int, array<string, mixed>>  $reports
     * @return array<string, mixed>
     */
    private function page(array $reports, bool $hasMorePages): array
    {
        return [
            'data' => [
                'reportData' => [
                    'reports' => [
                        'data' => $reports,
                        'current_page' => 1,
                        'has_more_pages' => $hasMorePages,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function report(string $code): array
    {
        return [
            'code' => $code,
            'title' => "Report {$code}",
            'startTime' => 1700000000123.0,
            'endTime' => 1700003600456.0,
            'guildTag' => ['id' => 1234, 'name' => 'Main Raid'],
            'zone' => null,
        ];
    }
}
