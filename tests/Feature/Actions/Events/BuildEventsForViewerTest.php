<?php

namespace Tests\Feature\Actions\Events;

use App\Actions\Events\BuildEventsForViewer;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Models\Event;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('raiding')]
#[Group('discord-integration')]
class BuildEventsForViewerTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_builds_the_visitors_shape_for_guests(): void
    {
        $this->fakeDiscord([]);
        $event = Event::factory()->live()->create();
        $event->raids()->attach(Raid::factory()->create(['name' => 'Black Temple']), ['sort_order' => 1]);

        $array = BuildEventsForViewer::run(Event::live(), new Request)->resolve(new Request);

        $this->assertSame(['id', 'title', 'start_time', 'end_time', 'raids'], array_keys($array[0]));
        $this->assertSame('Black Temple', $array[0]['raids'][0]['name']);
        $this->assertArrayNotHasKey('bosses', $array[0]['raids'][0]);
    }

    #[Test]
    public function it_does_not_call_discord_for_guests(): void
    {
        $this->fakeDiscord([]);
        Event::factory()->live()->create(['channel_id' => '999888777']);

        BuildEventsForViewer::run(Event::live(), new Request)->resolve(new Request);

        Saloon::mockClient()->assertNothingSent();
    }

    #[Test]
    #[Group('happy-path')]
    public function it_builds_the_members_shape_with_channels_for_signed_in_viewers(): void
    {
        $this->fakeDiscordChannel(name: 'raid-chat', position: 3);
        Event::factory()->live()->create();
        $request = $this->requestFor(User::factory()->create());

        $array = BuildEventsForViewer::run(Event::live(), $request)->resolve($request);

        $this->assertSame(['id', 'title', 'start_time', 'end_time', 'duration', 'raids', 'channel'], array_keys($array[0]));
        $this->assertSame('raid-chat', $array[0]['channel']['name']);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_keeps_building_when_one_channel_lookup_fails(): void
    {
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);
        Event::factory()->live()->count(2)->create();
        $request = $this->requestFor(User::factory()->create());

        $array = BuildEventsForViewer::run(Event::live(), $request)->resolve($request);

        $this->assertCount(2, $array);

        foreach ($array as $event) {
            $this->assertArrayNotHasKey('channel', $event);
        }
    }

    private function requestFor(User $user): Request
    {
        $request = new Request;
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
