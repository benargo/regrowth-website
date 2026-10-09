<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Data\Attendance\GuildAttendanceData;
use App\Http\Integrations\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildAttendanceRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
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
class GetGuildAttendanceRequestTest extends WarcraftLogsTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_queries_guild_attendance_on_the_namespace_host(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildAttendanceRequest::class => MockResponse::make($this->page([$this->attendance('aaa')], hasMorePages: false)),
        ]);

        $attendance = $this->makeConnector()
            ->send(new GetGuildAttendanceRequest(774848, WarcraftLogsNamespace::SeasonOfDiscovery))
            ->dto();

        $this->assertCount(1, $attendance);
        $this->assertContainsOnlyInstancesOf(GuildAttendanceData::class, $attendance);
        $this->assertSame('aaa', $attendance[0]->code);

        Saloon::assertSent(function (Request $request, Response $response): bool {
            if (! $request instanceof GetGuildAttendanceRequest) {
                return false;
            }

            $body = $request->body()->all();

            return $response->getPendingRequest()->getUrl() === 'https://sod.warcraftlogs.com/api/v2/client'
                && $body['variables'] === ['id' => 774848, 'page' => 1, 'limit' => 25]
                && ! str_contains($body['query'], 'zoneID');
        });
    }

    #[Test]
    #[Group('contract')]
    public function it_is_paginatable_and_cached_for_twelve_hours(): void
    {
        $request = new GetGuildAttendanceRequest(774848, WarcraftLogsNamespace::SeasonOfDiscovery);

        $this->assertInstanceOf(Paginatable::class, $request);
        $this->assertSame(43200, $request->cacheExpiryInSeconds());
    }

    #[Test]
    public function it_iterates_every_page_through_the_connector_paginator(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildAttendanceRequest::class => fn (PendingRequest $pendingRequest): MockResponse => $pendingRequest->body()->all()['variables']['page'] === 1
                ? MockResponse::make($this->page([$this->attendance('aaa')], hasMorePages: true))
                : MockResponse::make($this->page([$this->attendance('bbb')], hasMorePages: false)),
        ]);

        $codes = $this->makeConnector()->paginate(new GetGuildAttendanceRequest(774848, WarcraftLogsNamespace::SeasonOfDiscovery))
            ->collect()
            ->map(fn (GuildAttendanceData $attendance): string => $attendance->code)
            ->values()
            ->all();

        $this->assertSame(['aaa', 'bbb'], $codes);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_guild_not_found_when_attendance_is_null(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildAttendanceRequest::class => MockResponse::make(['data' => ['guildData' => ['guild' => null]]]),
        ]);

        $this->expectException(GuildNotFoundException::class);
        $this->expectExceptionMessage('Guild with ID 774848 not found');

        $this->makeConnector()
            ->send(new GetGuildAttendanceRequest(774848, WarcraftLogsNamespace::SeasonOfDiscovery))
            ->dto();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_guild_not_found_for_a_not_found_error(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildAttendanceRequest::class => MockResponse::make(['errors' => [['message' => 'Guild not found.']]], 200, ['Content-Type' => 'application/json']),
        ]);

        $this->expectException(GuildNotFoundException::class);

        $this->makeConnector()->send(new GetGuildAttendanceRequest(774848, WarcraftLogsNamespace::SeasonOfDiscovery));
    }

    /**
     * @param  array<int, array<string, mixed>>  $attendance
     * @return array<string, mixed>
     */
    private function page(array $attendance, bool $hasMorePages): array
    {
        return [
            'data' => [
                'guildData' => [
                    'guild' => [
                        'attendance' => [
                            'data' => $attendance,
                            'current_page' => 1,
                            'has_more_pages' => $hasMorePages,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function attendance(string $code): array
    {
        return [
            'code' => $code,
            'startTime' => 1700000000123.0,
            'players' => [['name' => 'Thrall', 'presence' => 1]],
            'zone' => null,
        ];
    }
}
