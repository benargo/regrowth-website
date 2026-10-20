<?php

namespace Tests\Feature\Raiding;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('raiding')]
class EventsIndexTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->freezeTime();
        $this->gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
    }

    // ==================== upcoming events ====================

    #[Test]
    #[Group('happy-path')]
    public function it_lists_the_current_versions_upcoming_events_soonest_first(): void
    {
        $later = $this->eventEndingIn(days: 5);
        $sooner = $this->eventEndingIn(days: 1);

        $response = $this->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Events/Index')
            ->has('upcomingEvents.data', 2)
            ->where('upcomingEvents.data.0.id', $sooner->id)
            ->where('upcomingEvents.data.1.id', $later->id)
        );
    }

    #[Test]
    public function it_lists_only_the_current_versions_events(): void
    {
        $event = $this->eventEndingIn(days: 1);
        Event::factory()->live()->forGameVersion(GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]))->create();
        Event::factory()->live()->create();
        Event::factory()->template()->forGameVersion($this->gameVersion)->create();

        $response = $this->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('upcomingEvents.data', 1)
            ->where('upcomingEvents.data.0.id', $event->id)
        );
    }

    #[Test]
    public function it_follows_the_session_version(): void
    {
        $sessionVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $event = Event::factory()->live()->forGameVersion($sessionVersion)->create();
        $this->eventEndingIn(days: 1);

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $sessionVersion->slug])->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('upcomingEvents.data', 1)
            ->where('upcomingEvents.data.0.id', $event->id)
        );
    }

    // ==================== past events ====================

    #[Test]
    public function it_lists_past_events_newest_first(): void
    {
        $older = $this->eventEndingIn(days: -3);
        $newer = $this->eventEndingIn(days: -1);

        $response = $this->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('pastEvents.data', 2)
            ->where('pastEvents.data.0.id', $newer->id)
            ->where('pastEvents.data.1.id', $older->id)
        );
    }

    #[Test]
    public function it_orders_past_events_with_the_same_start_by_newest_id(): void
    {
        $first = $this->eventEndingIn(days: -2);
        $second = $this->eventEndingIn(days: -2);
        $second->update(['start_time' => $first->start_time]);

        $response = $this->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('pastEvents.data.0.id', $second->id)
            ->where('pastEvents.data.1.id', $first->id)
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_hides_events_that_ended_over_two_weeks_ago_without_permission(): void
    {
        $recent = $this->eventEndingIn(days: -13);
        $this->eventEndingIn(days: -15);

        $response = $this->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('pastEvents.data', 1)
            ->where('pastEvents.data.0.id', $recent->id)
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_shows_older_events_with_view_old_raid_plans(): void
    {
        $this->fakeDiscordChannel();
        $user = User::factory()->withPermissions('view-old-raid-plans')->create();
        $old = $this->eventEndingIn(days: -15);

        $response = $this->actingAs($user)->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('pastEvents.data', 1)
            ->where('pastEvents.data.0.id', $old->id)
        );
    }

    #[Test]
    public function it_paginates_past_events_by_twenty(): void
    {
        foreach (range(1, 21) as $day) {
            $this->eventEndingIn(days: -1, hours: -$day);
        }

        $firstPage = $this->get(route('raiding.events.index'));
        $secondPage = $this->get(route('raiding.events.index', ['page' => 2]));

        $firstPage->assertInertia(fn (Assert $page) => $page
            ->has('pastEvents.data', 20)
            ->where('pastEvents.meta.last_page', 2)
        );
        $secondPage->assertInertia(fn (Assert $page) => $page->has('pastEvents.data', 1));
    }

    // ==================== empty states ====================

    #[Test]
    public function it_shows_nothing_without_any_game_version(): void
    {
        $this->gameVersion->delete();
        Event::factory()->live()->create();

        $response = $this->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('hasAnyEvents', false)
            ->has('upcomingEvents.data', 0)
            ->has('pastEvents.data', 0)
        );
    }

    #[Test]
    public function it_reports_that_the_version_has_events_when_the_viewer_can_see_none_of_them(): void
    {
        $this->eventEndingIn(days: -15);

        $response = $this->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('hasAnyEvents', true)
            ->has('upcomingEvents.data', 0)
            ->has('pastEvents.data', 0)
        );
    }

    #[Test]
    public function it_reports_that_a_version_without_events_has_none(): void
    {
        Event::factory()->template()->forGameVersion($this->gameVersion)->create();

        $response = $this->get(route('raiding.events.index'));

        $response->assertInertia(fn (Assert $page) => $page->where('hasAnyEvents', false));
    }

    // ==================== helpers ====================

    private function eventEndingIn(int $days, int $hours = 0): Event
    {
        $endTime = now()->addDays($days)->addHours($hours);

        return Event::factory()->live()->forGameVersion($this->gameVersion)->create([
            'start_time' => $endTime->copy()->subHours(3),
            'end_time' => $endTime,
        ]);
    }
}
