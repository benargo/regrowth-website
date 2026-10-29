<?php

namespace Tests\Feature\Jobs\Discord;

use App\Actions\Discord\FetchChannel;
use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildChannelsRequest;
use App\Jobs\Discord\SyncChannels;
use App\Models\DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class SyncChannelsTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    // ==================== guild list ====================

    #[Test]
    public function it_creates_channels_from_discord(): void
    {
        $this->fakeGuildChannels([
            $this->makeChannel('100', 'Raiding', ChannelType::GuildCategory, ['position' => 1]),
            $this->makeChannel('200', 'raids', ChannelType::GuildText, [
                'position' => 2,
                'parent_id' => '100',
                'topic' => 'Raid sign-ups',
                'nsfw' => true,
            ]),
        ]);

        SyncChannels::dispatchSync();

        $this->assertDatabaseCount('discord_channels', 2);
        $this->assertDatabaseHas('discord_channels', [
            'id' => '100',
            'name' => 'Raiding',
            'type' => ChannelType::GuildCategory->value,
            'position' => 1,
            'parent_id' => null,
        ]);
        $this->assertDatabaseHas('discord_channels', [
            'id' => '200',
            'name' => 'raids',
            'type' => ChannelType::GuildText->value,
            'position' => 2,
            'parent_id' => '100',
            'topic' => 'Raid sign-ups',
            'nsfw' => true,
        ]);
        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildChannelsRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/channels');
    }

    #[Test]
    public function it_updates_existing_channels(): void
    {
        DiscordChannel::factory()->create(['id' => '200', 'name' => 'old-name', 'position' => 9]);
        $this->fakeGuildChannels([$this->makeChannel('200', 'raids', ChannelType::GuildText, ['position' => 2])]);

        SyncChannels::dispatchSync();

        $this->assertDatabaseCount('discord_channels', 1);
        $this->assertDatabaseHas('discord_channels', ['id' => '200', 'name' => 'raids', 'position' => 2]);
    }

    #[Test]
    public function it_soft_deletes_channels_missing_from_discord(): void
    {
        $missing = DiscordChannel::factory()->create();
        $this->fakeGuildChannels([$this->makeChannel('200', 'raids')]);

        SyncChannels::dispatchSync();

        $this->assertSoftDeleted($missing);
    }

    #[Test]
    public function it_restores_a_trashed_channel_that_comes_back(): void
    {
        DiscordChannel::factory()->trashed()->create(['id' => '200']);
        $this->fakeGuildChannels([$this->makeChannel('200', 'raids')]);

        SyncChannels::dispatchSync();

        $this->assertNotSoftDeleted('discord_channels', ['id' => '200']);
    }

    #[Test]
    public function it_fills_in_a_stub_from_the_guild_list_without_fetching_it(): void
    {
        DiscordChannel::factory()->stub()->create(['id' => '200']);
        $this->fakeGuildChannels([$this->makeChannel('200', 'raids', ChannelType::GuildText, ['position' => 2])]);

        SyncChannels::dispatchSync();

        $this->assertDatabaseHas('discord_channels', [
            'id' => '200',
            'name' => 'raids',
            'type' => ChannelType::GuildText->value,
            'position' => 2,
        ]);
        Saloon::assertNotSent(GetChannelRequest::class);
    }

    // ==================== threads and stubs by id ====================

    #[Test]
    public function it_keeps_a_stored_thread_missing_from_the_guild_list(): void
    {
        $thread = DiscordChannel::factory()->thread()->create(['id' => '300']);
        $this->fakeGuildChannels([], [
            '300' => MockResponse::make($this->makeChannel('300', $thread->name, ChannelType::PublicThread)),
        ]);

        SyncChannels::dispatchSync();

        $this->assertNotSoftDeleted($thread);
    }

    #[Test]
    public function it_refreshes_a_stored_thread_by_id_bypassing_the_cache(): void
    {
        DiscordChannel::factory()->thread()->create(['id' => '300', 'name' => 'old-name']);
        $this->fakeDiscord(['discord.com/api/v10/channels/300' => MockResponse::make($this->makeChannel('300', 'old-name', ChannelType::PublicThread))]);
        FetchChannel::run('300');

        $this->fakeGuildChannels([], [
            '300' => MockResponse::make($this->makeChannel('300', 'new-name', ChannelType::PublicThread)),
        ]);

        SyncChannels::dispatchSync();

        $this->assertDatabaseHas('discord_channels', ['id' => '300', 'name' => 'new-name']);
    }

    #[Test]
    public function it_does_not_fetch_a_trashed_thread_or_stub(): void
    {
        $thread = DiscordChannel::factory()->thread()->trashed()->create(['id' => '300']);
        $stub = DiscordChannel::factory()->stub()->trashed()->create(['id' => '400']);
        $this->fakeGuildChannels([]);

        SyncChannels::dispatchSync();

        Saloon::assertNotSent(GetChannelRequest::class);
        $this->assertSoftDeleted($thread);
        $this->assertSoftDeleted($stub);
    }

    #[Test]
    public function it_soft_deletes_a_thread_that_returns_unknown_channel(): void
    {
        $thread = DiscordChannel::factory()->thread()->create(['id' => '300']);
        $this->fakeGuildChannels([], [
            '300' => $this->makeDiscordErrorResponse(404, 10003, 'Unknown Channel'),
        ]);

        SyncChannels::dispatchSync();

        $this->assertSoftDeleted($thread);
    }

    #[Test]
    public function it_leaves_a_thread_that_returns_forbidden(): void
    {
        Log::spy();
        DiscordChannel::factory()->thread()->create(['id' => '300', 'name' => 'secret']);
        $this->fakeGuildChannels([], [
            '300' => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access'),
        ]);

        SyncChannels::dispatchSync();

        $this->assertDatabaseHas('discord_channels', ['id' => '300', 'name' => 'secret', 'deleted_at' => null]);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $context['channel_id'] === '300');
    }

    #[Test]
    #[Group('error-handling')]
    public function it_leaves_a_thread_that_returns_a_server_error_and_syncs_the_rest(): void
    {
        Log::spy();
        DiscordChannel::factory()->thread()->create(['id' => '300', 'name' => 'flaky']);
        DiscordChannel::factory()->thread()->create(['id' => '301', 'name' => 'old-name']);
        $this->fakeGuildChannels([$this->makeChannel('200', 'raids')], [
            '300' => $this->makeDiscordErrorResponse(500, 0, 'Internal Server Error'),
            '301' => MockResponse::make($this->makeChannel('301', 'new-name', ChannelType::PublicThread)),
        ]);

        SyncChannels::dispatchSync();

        $this->assertDatabaseHas('discord_channels', ['id' => '300', 'name' => 'flaky', 'deleted_at' => null]);
        $this->assertDatabaseHas('discord_channels', ['id' => '301', 'name' => 'new-name']);
        $this->assertDatabaseHas('discord_channels', ['id' => '200', 'name' => 'raids']);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => ($context['channel_id'] ?? null) === '300');
    }

    #[Test]
    public function it_fills_in_a_stub_found_by_id(): void
    {
        DiscordChannel::factory()->stub()->create(['id' => '400']);
        $this->fakeGuildChannels([], [
            '400' => MockResponse::make($this->makeChannel('400', 'old-raids', ChannelType::GuildText)),
        ]);

        SyncChannels::dispatchSync();

        $this->assertDatabaseHas('discord_channels', [
            'id' => '400',
            'name' => 'old-raids',
            'type' => ChannelType::GuildText->value,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_soft_deletes_a_stub_that_returns_unknown_channel(): void
    {
        $stub = DiscordChannel::factory()->stub()->create(['id' => '400']);
        $this->fakeGuildChannels([], [
            '400' => $this->makeDiscordErrorResponse(404, 10003, 'Unknown Channel'),
        ]);

        SyncChannels::dispatchSync();

        $this->assertSoftDeleted($stub);
    }

    // ==================== contract and errors ====================

    #[Test]
    #[Group('contract')]
    public function it_does_nothing_when_a_duplicate_is_skipped(): void
    {
        $this->fakeGuildChannels([$this->makeChannel('200', 'raids')]);

        $running = new SyncChannels;
        $overlap = collect($running->middleware())->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);
        Cache::lock($overlap->getLockKey($running), 120)->get();

        SyncChannels::dispatchSync();

        Saloon::assertNothingSent();
        $this->assertDatabaseCount('discord_channels', 0);
    }

    #[Test]
    #[Group('contract')]
    public function it_expires_its_overlap_lock_after_ten_minutes(): void
    {
        $overlap = collect((new SyncChannels)->middleware())->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);

        $this->assertSame(600, $overlap->expiresAfter);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_rate_limited_on_the_guild_list(): void
    {
        $this->fakeDiscord([GetGuildChannelsRequest::class => $this->makeDiscordRateLimitResponse(30.0)]);

        $job = new SyncChannels;
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(30);
        $this->assertDatabaseCount('discord_channels', 0);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_rate_limited_on_a_thread_fetch(): void
    {
        DiscordChannel::factory()->thread()->create(['id' => '300']);
        $this->fakeGuildChannels([$this->makeChannel('200', 'raids')], [
            '300' => $this->makeDiscordRateLimitResponse(30.0),
        ]);

        $job = new SyncChannels;
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(30);
        $this->assertDatabaseCount('discord_channels', 1);
        $this->assertDatabaseMissing('discord_channels', ['id' => '200']);
    }

    #[Test]
    #[Group('contract')]
    public function it_has_the_discord_channel_tags(): void
    {
        $this->assertSame(['discord', 'discord:channels'], (new SyncChannels)->tags());
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, array<string, mixed>>  $channels
     * @param  array<string, MockResponse>  $byId
     */
    private function fakeGuildChannels(array $channels, array $byId = []): void
    {
        $fakes = [GetGuildChannelsRequest::class => MockResponse::make($channels)];

        foreach ($byId as $id => $response) {
            $fakes["discord.com/api/v10/channels/{$id}"] = $response;
        }

        $this->fakeDiscord($fakes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function makeChannel(string $id, string $name, ChannelType $type = ChannelType::GuildText, array $attributes = []): array
    {
        return ['id' => $id, 'name' => $name, 'type' => $type->value, ...$attributes];
    }
}
