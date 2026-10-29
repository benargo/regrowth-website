<?php

namespace Tests\Feature\Actions\Discord;

use App\Actions\Discord\ResolveDiscordChannel;
use App\Http\Integrations\Discord\Exceptions\ChannelNotFoundException;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Models\DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class ResolveDiscordChannelTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_stored_channel_without_calling_discord(): void
    {
        Saloon::fake([]);
        $stored = DiscordChannel::factory()->create();

        $channel = ResolveDiscordChannel::run($stored->id);

        $this->assertTrue($channel->is($stored));
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_returns_a_trashed_channel_without_calling_discord(): void
    {
        Saloon::fake([]);
        $stored = DiscordChannel::factory()->trashed()->create();

        $channel = ResolveDiscordChannel::run($stored->id);

        $this->assertTrue($channel->is($stored));
        $this->assertTrue($channel->trashed());
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_fetches_and_stores_an_unknown_channel(): void
    {
        $this->fakeDiscordChannel('555', 'raids', 2);

        $channel = ResolveDiscordChannel::run('555');

        $this->assertSame('raids', $channel->name);
        $this->assertDatabaseHas('discord_channels', ['id' => '555', 'name' => 'raids', 'position' => 2]);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_propagates_a_discord_error(): void
    {
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(404, 10003, 'Unknown Channel')]);

        try {
            ResolveDiscordChannel::run('555');
            $this->fail('Expected ChannelNotFoundException.');
        } catch (ChannelNotFoundException) {
            $this->assertDatabaseCount('discord_channels', 0);
        }
    }
}
