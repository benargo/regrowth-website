<?php

namespace Tests\Feature\Actions\Events;

use App\Actions\Events\ListUpcomingRaidingEvents;
use App\Models\Event;
use App\Models\GameVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ListUpcomingRaidingEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::tags(['raiding', 'events'])->flush();
        $this->freezeTime();
    }

    #[Test]
    public function it_lists_the_versions_events_in_the_coming_week_soonest_first(): void
    {
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $later = $this->eventEndingIn($gameVersion, days: 3);
        $sooner = $this->eventEndingIn($gameVersion, days: 1);
        $this->eventEndingIn($gameVersion, days: 9);
        $this->eventEndingIn(GameVersion::factory()->create(['release_date' => Carbon::parse('2023-01-01')]), days: 1);

        $events = ListUpcomingRaidingEvents::run($gameVersion, new Request);

        $this->assertSame([$sooner->id, $later->id], array_column($events, 'id'));
    }

    #[Test]
    public function it_returns_nothing_without_a_game_version(): void
    {
        $this->eventEndingIn(GameVersion::factory()->create(), days: 1);

        $this->assertSame([], ListUpcomingRaidingEvents::run(null, new Request));
    }

    #[Test]
    public function it_caches_each_versions_events_separately(): void
    {
        $first = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $second = GameVersion::factory()->create(['release_date' => Carbon::parse('2023-01-01')]);
        $firstEvent = $this->eventEndingIn($first, days: 1);
        $secondEvent = $this->eventEndingIn($second, days: 1);

        ListUpcomingRaidingEvents::run($first, new Request);

        $this->assertSame([$secondEvent->id], array_column(ListUpcomingRaidingEvents::run($second, new Request), 'id'));
        $this->assertSame([$firstEvent->id], array_column(ListUpcomingRaidingEvents::run($first, new Request), 'id'));
    }

    private function eventEndingIn(GameVersion $gameVersion, int $days): Event
    {
        $endTime = now()->addDays($days);

        return Event::factory()->live()->forGameVersion($gameVersion)->create([
            'start_time' => $endTime->copy()->subHours(3),
            'end_time' => $endTime,
        ]);
    }
}
