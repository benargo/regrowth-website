<?php

namespace Tests\Feature\Jobs\Discord;

use App\Http\Integrations\Discord\Requests\Guilds\GetGuildEmojisRequest;
use App\Jobs\Discord\SyncEmojis;
use App\Models\DiscordEmoji;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class SyncEmojisTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    public function it_creates_emoji_from_discord(): void
    {
        $this->fakeGuildEmojis();

        SyncEmojis::dispatchSync();

        $this->assertDatabaseCount('discord_emoji', 3);
        $this->assertDatabaseHas('discord_emoji', ['id' => '111111111111111111', 'name' => 'static_emoji', 'animated' => false, 'available' => true]);
        $this->assertDatabaseHas('discord_emoji', ['id' => '222222222222222222', 'name' => 'animated_emoji', 'animated' => true, 'available' => true]);
        $this->assertDatabaseHas('discord_emoji', ['id' => '333333333333333333', 'name' => 'unavailable_emoji', 'animated' => false, 'available' => false]);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildEmojisRequest
            && $request->resolveEndpoint() === '/guilds/829020506907869214/emojis');
    }

    #[Test]
    public function it_updates_existing_emoji(): void
    {
        DiscordEmoji::factory()->create([
            'id' => '111111111111111111',
            'name' => 'old_name',
        ]);

        $this->fakeGuildEmojis();

        SyncEmojis::dispatchSync();

        $this->assertDatabaseHas('discord_emoji', ['id' => '111111111111111111', 'name' => 'static_emoji']);
        $this->assertDatabaseMissing('discord_emoji', ['name' => 'old_name']);
    }

    #[Test]
    public function it_deletes_emoji_no_longer_in_discord(): void
    {
        DiscordEmoji::factory()->create(['id' => '999999999999999999', 'name' => 'deleted_emoji']);

        $this->fakeGuildEmojis();

        SyncEmojis::dispatchSync();

        $this->assertDatabaseMissing('discord_emoji', ['id' => '999999999999999999']);
    }

    #[Test]
    public function it_treats_omitted_flags_as_static_and_available(): void
    {
        $this->fakeDiscord([GetGuildEmojisRequest::class => MockResponse::make([
            ['id' => '444444444444444444', 'name' => 'bare_emoji'],
        ])]);

        SyncEmojis::dispatchSync();

        $this->assertDatabaseHas('discord_emoji', [
            'id' => '444444444444444444',
            'name' => 'bare_emoji',
            'animated' => false,
            'available' => true,
        ]);
    }

    #[Test]
    #[Group('contract')]
    public function it_does_nothing_when_a_duplicate_is_skipped(): void
    {
        $this->fakeGuildEmojis();

        $running = new SyncEmojis;
        $overlap = collect($running->middleware())->first(fn (object $middleware): bool => $middleware instanceof WithoutOverlapping);
        Cache::lock($overlap->getLockKey($running), 120)->get();

        SyncEmojis::dispatchSync();

        Saloon::assertNothingSent();
        $this->assertDatabaseCount('discord_emoji', 0);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited(): void
    {
        $this->fakeDiscord([GetGuildEmojisRequest::class => $this->makeDiscordRateLimitResponse(30.0)]);

        $job = new SyncEmojis;
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(30);
        $this->assertDatabaseCount('discord_emoji', 0);
    }

    // ==================== helpers ====================

    private function fakeGuildEmojis(): void
    {
        $this->fakeDiscord([GetGuildEmojisRequest::class => MockResponse::make([
            $this->makeDiscordEmojiResponse('111111111111111111', 'static_emoji'),
            $this->makeDiscordEmojiResponse('222222222222222222', 'animated_emoji', animated: true),
            $this->makeDiscordEmojiResponse('333333333333333333', 'unavailable_emoji', available: false),
        ])]);
    }
}
