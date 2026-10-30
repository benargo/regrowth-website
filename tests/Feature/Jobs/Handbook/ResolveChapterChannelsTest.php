<?php

namespace Tests\Feature\Jobs\Handbook;

use App\Http\Integrations\Discord\Enums\ChannelType;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Jobs\Handbook\ResolveChapterChannels;
use App\Models\DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('raiding')]
#[Group('discord-integration')]
class ResolveChapterChannelsTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    public function it_stores_an_unknown_thread(): void
    {
        $this->fakeDiscordChannel('555', 'loot-council-faq', type: 11);

        ResolveChapterChannels::dispatchSync(['555']);

        $this->assertSame(ChannelType::PublicThread, DiscordChannel::findOrFail('555')->type);
    }

    #[Test]
    public function it_does_not_fetch_stored_channels(): void
    {
        Saloon::fake([]);
        DiscordChannel::factory()->create(['id' => '111']);
        DiscordChannel::factory()->trashed()->create(['id' => '222']);

        ResolveChapterChannels::dispatchSync(['111', '222']);

        Saloon::assertNothingSent();
        $this->assertSoftDeleted('discord_channels', ['id' => '222']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_and_continues_when_a_channel_is_not_found(): void
    {
        Log::spy();
        $this->fakeDiscord([
            GetChannelRequest::class => fn (PendingRequest $request) => str_ends_with($request->getUrl(), '/channels/111')
                ? $this->makeDiscordErrorResponse(404, 10003, 'Unknown Channel')
                : MockResponse::make($this->makeDiscordChannelResponse('222', 'general')),
        ]);

        $job = new ResolveChapterChannels(['111', '222']);
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertNotFailed();
        $this->assertNotNull(DiscordChannel::find('222'));
        $this->assertNull(DiscordChannel::withTrashed()->find('111'));
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_and_continues_when_a_channel_is_forbidden(): void
    {
        Log::spy();
        $this->fakeDiscord([
            GetChannelRequest::class => fn (PendingRequest $request) => str_ends_with($request->getUrl(), '/channels/111')
                ? $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')
                : MockResponse::make($this->makeDiscordChannelResponse('222', 'general')),
        ]);

        $job = new ResolveChapterChannels(['111', '222']);
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertNotFailed();
        $this->assertNotNull(DiscordChannel::find('222'));
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited(): void
    {
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordRateLimitResponse(30.0)]);

        $job = new ResolveChapterChannels(['111']);
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(30);
    }

    #[Test]
    #[Group('contract')]
    public function it_has_the_resolve_chapter_channels_tags(): void
    {
        $this->assertSame(['discord', 'discord:resolve-chapter-channels'], (new ResolveChapterChannels([]))->tags());
    }
}
