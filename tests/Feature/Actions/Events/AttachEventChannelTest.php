<?php

namespace Tests\Feature\Actions\Events;

use App\Actions\Events\AttachEventChannel;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('raiding')]
#[Group('discord-integration')]
class AttachEventChannelTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_returns_channel_as_an_array(): void
    {
        $this->fakeDiscordChannel(id: '999888777', name: 'raid-chat', position: 3);
        $event = Event::factory()->create();

        $array = AttachEventChannel::run(new EventResource($event))->resolve(new Request);

        $this->assertEquals(['id' => '999888777', 'name' => 'raid-chat', 'position' => 3], $array['channel']);
    }

    #[Test]
    public function it_returns_null_channel_when_channel_id_is_null(): void
    {
        $this->fakeDiscord([]);
        $event = Event::factory()->template()->create(['channel_id' => null, 'start_time' => null, 'end_time' => null]);

        $array = AttachEventChannel::run(new EventResource($event))->resolve(new Request);

        $this->assertArrayHasKey('channel', $array);
        $this->assertNull($array['channel']);
    }

    #[Test]
    public function it_does_not_call_discord_when_channel_id_is_null(): void
    {
        $this->fakeDiscord([]);
        $event = Event::factory()->template()->create(['channel_id' => null, 'start_time' => null, 'end_time' => null]);

        AttachEventChannel::run(new EventResource($event));

        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_returns_null_channel_without_calling_discord_when_channel_id_is_blank(): void
    {
        $this->fakeDiscord([]);
        $event = Event::factory()->create(['channel_id' => '']);

        $array = AttachEventChannel::run(new EventResource($event))->resolve(new Request);

        $this->assertNull($array['channel']);
        Saloon::assertNothingSent();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_omits_channel_when_discord_api_fails(): void
    {
        Exceptions::fake();
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);
        $event = Event::factory()->create();

        $array = AttachEventChannel::run(new EventResource($event))->resolve(new Request);

        $this->assertArrayNotHasKey('channel', $array);
        Exceptions::assertReported(fn (ApiException $exception): bool => $exception->getDiscordCode() === 50001);
        Saloon::mockClient()->assertSentCount(1, GetChannelRequest::class);
    }

    #[Test]
    public function the_members_scope_returns_the_attached_channel(): void
    {
        $this->fakeDiscordChannel(id: '999888777', name: 'raid-chat', position: 3);
        $event = Event::factory()->create();

        $array = AttachEventChannel::run(new EventResource($event))->scope('members')->resolve(new Request);

        $this->assertEquals(['id' => '999888777', 'name' => 'raid-chat', 'position' => 3], $array['channel']);
    }

    #[Test]
    #[Group('error-handling')]
    public function the_members_scope_omits_channel_when_discord_api_fails(): void
    {
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);
        $event = Event::factory()->create();

        $array = AttachEventChannel::run(new EventResource($event))->scope('members')->resolve(new Request);

        $this->assertArrayNotHasKey('channel', $array);
        Saloon::mockClient()->assertSentCount(1, GetChannelRequest::class);
    }
}
