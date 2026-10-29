<?php

namespace Tests\Unit\Http\Integrations\WarcraftLogs\Requests;

use App\Http\Integrations\WarcraftLogs\Data\GuildTags\GuildTagData;
use App\Http\Integrations\WarcraftLogs\Exceptions\GraphQLException;
use App\Http\Integrations\WarcraftLogs\Exceptions\GuildNotFoundException;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildTagsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\WarcraftLogs\WarcraftLogsTestCase;

#[Group('warcraftlogs-integration')]
class GetGuildTagsRequestTest extends WarcraftLogsTestCase
{
    #[Test]
    #[Group('happy-path')]
    public function it_queries_the_guild_on_the_namespace_host_and_returns_tag_data(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make($this->sampleApiResponse()),
        ]);

        $tags = $this->makeConnector()
            ->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary))
            ->dto();

        $this->assertCount(2, $tags);
        $this->assertContainsOnlyInstancesOf(GuildTagData::class, $tags);
        $this->assertSame(1234, $tags[0]->id);
        $this->assertSame('Main Raid', $tags[0]->name);

        Saloon::assertSent(function (Request $request, Response $response): bool {
            if (! $request instanceof GetGuildTagsRequest) {
                return false;
            }

            return $response->getPendingRequest()->getUrl() === 'https://fresh.warcraftlogs.com/api/v2/client'
                && $request->body()->all()['variables'] === ['id' => 774848];
        });
    }

    #[Test]
    public function it_returns_an_empty_list_when_the_guild_has_no_tags(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make(['data' => ['guildData' => ['guild' => ['id' => 774848, 'tags' => []]]]]),
        ]);

        $tags = $this->makeConnector()
            ->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary))
            ->dto();

        $this->assertSame([], $tags);
    }

    #[Test]
    #[Group('contract')]
    public function it_caches_for_twelve_hours(): void
    {
        $this->assertSame(43200, (new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary))->cacheExpiryInSeconds());
    }

    #[Test]
    public function it_caches_each_namespace_separately(): void
    {
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make($this->sampleApiResponse()),
        ]);
        $connector = $this->makeConnector();

        $anniversary = $connector->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary));
        $retail = $connector->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Retail));
        $anniversaryAgain = $connector->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary));

        $this->assertFalse($anniversary->isCached());
        $this->assertFalse($retail->isCached());
        $this->assertTrue($anniversaryAgain->isCached());
        $mockClient->assertSentCount(2, GetGuildTagsRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_does_not_cache_a_graphql_error(): void
    {
        $mockClient = Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make(['errors' => [['message' => 'Internal server error']]], 200, ['Content-Type' => 'application/json']),
        ]);
        $connector = $this->makeConnector();

        $this->assertThrows(fn () => $connector->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary)), GraphQLException::class);
        $this->assertThrows(fn () => $connector->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary)), GraphQLException::class);

        $mockClient->assertSentCount(2, GetGuildTagsRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_guild_not_found_when_the_guild_is_null(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make(['data' => ['guildData' => ['guild' => null]]]),
        ]);

        $this->expectException(GuildNotFoundException::class);
        $this->expectExceptionMessage('Guild with ID 774848 not found');

        $this->makeConnector()
            ->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary))
            ->dto();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_guild_not_found_for_a_does_not_exist_error(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make([
                'errors' => [['message' => 'This guild does not exist.']],
                'data' => ['guildData' => ['guild' => null]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $this->expectException(GuildNotFoundException::class);

        $this->makeConnector()->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_keeps_other_graphql_errors_as_graphql_exceptions(): void
    {
        Saloon::fake([
            GetClientCredentialsTokenBasicAuthRequest::class => $this->tokenMock(),
            GetGuildTagsRequest::class => MockResponse::make(['errors' => [['message' => 'Internal server error']]], 200, ['Content-Type' => 'application/json']),
        ]);

        $this->assertThrows(
            fn () => $this->makeConnector()->send(new GetGuildTagsRequest(774848, WarcraftLogsNamespace::Anniversary)),
            fn (GraphQLException $exception): bool => ! $exception instanceof GuildNotFoundException,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'data' => [
                'guildData' => [
                    'guild' => [
                        'id' => 774848,
                        'tags' => [
                            ['id' => 1234, 'name' => 'Main Raid'],
                            ['id' => 5678, 'name' => 'Alt Raid'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
