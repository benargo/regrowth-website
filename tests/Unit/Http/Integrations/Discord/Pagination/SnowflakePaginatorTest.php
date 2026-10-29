<?php

namespace Tests\Unit\Http\Integrations\Discord\Pagination;

use App\Http\Integrations\Discord\Contracts\PaginatesBySnowflake;
use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\Pagination\SnowflakePaginator;
use App\Http\Integrations\Discord\Requests\Guilds\ListGuildMembersRequest;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\Unit\Http\Integrations\Discord\DiscordTestCase;

#[Group('discord-integration')]
class SnowflakePaginatorTest extends DiscordTestCase
{
    use MocksDiscordService;

    #[Test]
    #[Group('happy-path')]
    public function it_pages_until_a_short_page(): void
    {
        $this->fakeMemberPages([
            '0' => ['100000000000000001', '100000000000000002'],
            '100000000000000002' => ['100000000000000003'],
        ]);

        $ids = $this->collectMemberIds(limit: 2);

        $this->assertSame(['100000000000000001', '100000000000000002', '100000000000000003'], $ids);
        Saloon::mockClient()->assertSentCount(2, ListGuildMembersRequest::class);
    }

    #[Test]
    public function it_stops_on_an_empty_page(): void
    {
        $this->fakeMemberPages([
            '0' => ['100000000000000001', '100000000000000002'],
            '100000000000000002' => [],
        ]);

        $this->assertCount(2, $this->collectMemberIds(limit: 2));
        Saloon::mockClient()->assertSentCount(2, ListGuildMembersRequest::class);
    }

    #[Test]
    public function it_advances_after_to_the_numerically_highest_snowflake(): void
    {
        $this->fakeMemberPages([
            '0' => ['99999999999999999', '100000000000000000'],
            '100000000000000000' => [],
        ]);

        $this->assertCount(2, $this->collectMemberIds(limit: 2));
    }

    #[Test]
    public function it_pages_backwards_by_the_numerically_lowest_snowflake_at_the_requests_cursor_key(): void
    {
        $request = new class extends Request implements PaginatesBySnowflake
        {
            protected Method $method = Method::GET;

            public function resolveEndpoint(): string
            {
                return '/channels/1/messages';
            }

            /**
             * @return array{limit: int}
             */
            protected function defaultQuery(): array
            {
                return ['limit' => 2];
            }

            public function cursorKey(): string
            {
                return 'id';
            }

            public function cursorDirection(): string
            {
                return 'before';
            }

            /**
             * @return Collection<int, array<string, string>>
             */
            public function createDtoFromResponse(Response $response): Collection
            {
                return collect($response->json());
            }
        };

        $pages = [
            '' => ['300000000000000000', '100000000000000000'],
            '100000000000000000' => ['99999999999999999'],
        ];

        Saloon::fake(['*' => function (PendingRequest $pending) use ($pages): MockResponse {
            $this->assertNull($pending->query()->get('after'));
            $before = (string) $pending->query()->get('before');
            $this->assertArrayHasKey($before, $pages, "Unexpected before cursor [{$before}].");

            return MockResponse::make(array_map(fn (string $id): array => ['id' => $id], $pages[$before]));
        }]);

        $ids = collect((new SnowflakePaginator(connector: $this->makeConnector(), request: $request))->items())
            ->pluck('id')
            ->all();

        $this->assertSame(['300000000000000000', '100000000000000000', '99999999999999999'], $ids);
    }

    // ==================== helpers ====================

    /**
     * Fake one member page per `after` cursor; an unexpected cursor fails the test.
     *
     * @param  array<string, array<int, string>>  $pages
     */
    private function fakeMemberPages(array $pages): void
    {
        Saloon::fake([ListGuildMembersRequest::class => function (PendingRequest $pending) use ($pages): MockResponse {
            $after = (string) $pending->query()->get('after');
            $this->assertArrayHasKey($after, $pages, "Unexpected after cursor [{$after}].");

            return MockResponse::make(array_map(
                fn (string $id): array => $this->makeDiscordGuildMemberWithUser($id, "user{$id}"),
                $pages[$after],
            ));
        }]);
    }

    /**
     * @return array<int, string>
     */
    private function collectMemberIds(int $limit): array
    {
        $paginator = new SnowflakePaginator(
            connector: $this->makeConnector(),
            request: new ListGuildMembersRequest('829020506907869214', $limit),
        );

        return collect($paginator->items())
            ->map(fn (GuildMemberData $member): string => $member->user->id)
            ->values()
            ->all();
    }
}
