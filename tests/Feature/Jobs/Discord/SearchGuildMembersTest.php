<?php

namespace Tests\Feature\Jobs\Discord;

use App\Events\Broadcasts\DiscordMembersSearched;
use App\Http\Integrations\Discord\Requests\Guilds\SearchGuildMembersRequest;
use App\Jobs\Discord\SearchGuildMembers;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class SearchGuildMembersTest extends TestCase
{
    use MocksDiscordService;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([DiscordMembersSearched::class]);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_caches_each_member_as_its_id_nickname_and_username(): void
    {
        $this->fakeDiscord([SearchGuildMembersRequest::class => MockResponse::make([
            $this->makeDiscordGuildMemberWithUser('111111111111111111', 'testuser', 'TestNick'),
            $this->makeDiscordGuildMemberWithUser('333333333333333333', 'nonick'),
        ])]);

        SearchGuildMembers::dispatchSync('test', 1, '100000000000000003');

        $this->assertSame([
            ['id' => '111111111111111111', 'nickname' => 'TestNick', 'username' => 'testuser'],
            ['id' => '333333333333333333', 'nickname' => null, 'username' => 'nonick'],
        ], Cache::tags(['discord'])->get(SearchGuildMembers::cacheKey('829020506907869214', 1, 'test')));

        Saloon::assertSent(fn (Request $request): bool => $request instanceof SearchGuildMembersRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/members/search'
            && $request->query()->all() === ['query' => 'test', 'limit' => 1]);
    }

    #[Test]
    public function it_caches_searches_by_guild_limit_and_lowercased_query(): void
    {
        $this->fakeDiscord([SearchGuildMembersRequest::class => MockResponse::make([
            $this->makeDiscordGuildMemberWithUser('444444444444444444', 'cacheduser', 'Cached'),
        ])]);

        SearchGuildMembers::dispatchSync('Cache', 5, '100000000000000003');
        SearchGuildMembers::dispatchSync('cache', 5, '100000000000000003');

        $this->assertSame(
            [['id' => '444444444444444444', 'nickname' => 'Cached', 'username' => 'cacheduser']],
            Cache::tags(['discord'])->get(SearchGuildMembers::cacheKey('829020506907869214', 5, 'cache')),
        );
        Saloon::mockClient()->assertSentCount(1, SearchGuildMembersRequest::class);
    }

    #[Test]
    public function it_omits_the_nickname_when_the_member_payload_omits_it(): void
    {
        $this->fakeDiscord([SearchGuildMembersRequest::class => MockResponse::make([
            Arr::except($this->makeDiscordGuildMemberWithUser('555555555555555555', 'nonick'), ['nick']),
        ])]);

        SearchGuildMembers::dispatchSync('nonick', 1, '100000000000000003');

        $this->assertSame(
            [['id' => '555555555555555555', 'username' => 'nonick']],
            Cache::tags(['discord'])->get(SearchGuildMembers::cacheKey('829020506907869214', 1, 'nonick')),
        );
    }

    #[Test]
    #[Group('contract')]
    public function it_runs_on_the_high_queue(): void
    {
        Queue::fake();

        SearchGuildMembers::dispatch('test', 5, '100000000000000003');

        Queue::assertPushedOn('high', SearchGuildMembers::class);
    }

    #[Test]
    public function it_broadcasts_to_the_requesting_officer_once_the_results_are_cached(): void
    {
        $this->fakeDiscord([SearchGuildMembersRequest::class => MockResponse::make([])]);

        SearchGuildMembers::dispatchSync('test', 5, '100000000000000003');

        Event::assertDispatched(DiscordMembersSearched::class, fn (DiscordMembersSearched $event): bool => $event->userId === '100000000000000003'
            && $event->query === 'test'
            && $event->limit === 5
            && $event->failed === false);
        $this->assertSame([], Cache::tags(['discord'])->get(SearchGuildMembers::cacheKey('829020506907869214', 5, 'test')));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited(): void
    {
        $this->fakeDiscord([SearchGuildMembersRequest::class => $this->makeDiscordRateLimitResponse(4.0)]);

        $job = new SearchGuildMembers('test', 5, '100000000000000003');
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(4);
        Event::assertNotDispatched(DiscordMembersSearched::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_tells_the_officer_when_the_search_finally_fails(): void
    {
        (new SearchGuildMembers('test', 5, '100000000000000003'))->failed(new RuntimeException('Discord is down'));

        Event::assertDispatched(DiscordMembersSearched::class, fn (DiscordMembersSearched $event): bool => $event->userId === '100000000000000003'
            && $event->query === 'test'
            && $event->limit === 5
            && $event->failed);
    }
}
