<?php

namespace Tests\Feature\Actions\Events;

use App\Actions\Events\BuildEventsForViewer;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Models\Event;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
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
    public function it_paginates_when_given_a_page_size(): void
    {
        $this->fakeDiscord([]);
        Event::factory()->live()->count(3)->create();

        $resources = BuildEventsForViewer::run(Event::live()->orderBy('start_time'), new Request, 2);

        $this->assertInstanceOf(LengthAwarePaginator::class, $resources->resource);
        $this->assertSame(3, $resources->resource->total());
        $this->assertCount(2, $resources->resolve(new Request));
    }

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
    public function it_lists_events_without_lazy_loading_raid_media(): void
    {
        Storage::fake('s3');
        $this->fakeDiscord([]);
        foreach (range(1, 2) as $index) {
            Event::factory()->live()->create()->raids()->attach(Raid::factory()->withBanner()->create(), ['sort_order' => 1]);
        }

        config(['media-library.force_lazy_loading' => false]);
        Model::preventLazyLoading();

        try {
            $array = BuildEventsForViewer::run(Event::live(), new Request)->resolve(new Request);

            $this->assertCount(2, $array);
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    #[Test]
    public function it_does_not_call_discord_for_guests(): void
    {
        $this->fakeDiscord([]);
        Event::factory()->live()->create();

        BuildEventsForViewer::run(Event::live(), new Request)->resolve(new Request);

        Saloon::mockClient()->assertNothingSent();
    }

    #[Test]
    #[Group('happy-path')]
    public function it_builds_the_members_shape_without_calling_discord_for_signed_in_viewers(): void
    {
        $this->fakeDiscordChannel(name: 'raid-chat', position: 3);
        Event::factory()->live()->count(2)->create();
        $request = $this->requestFor(User::factory()->create());

        $array = BuildEventsForViewer::run(Event::live(), $request)->resolve($request);

        $this->assertSame(['id', 'title', 'start_time', 'end_time', 'duration', 'raids'], array_keys($array[0]));
        Saloon::assertNotSent(GetChannelRequest::class);
    }

    private function requestFor(User $user): Request
    {
        $request = new Request;
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
