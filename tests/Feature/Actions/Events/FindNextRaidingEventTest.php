<?php

namespace Tests\Feature\Actions\Events;

use App\Actions\Events\FindNextRaidingEvent;
use App\Models\Event;
use App\Models\GameVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon as SupportCarbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class FindNextRaidingEventTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
    }

    #[Test]
    public function it_returns_the_soonest_upcoming_event_of_the_version(): void
    {
        $this->eventStartingAt(now()->addDays(3), $this->gameVersion);
        $tomorrow = $this->eventStartingAt(now()->addDay(), $this->gameVersion);
        $this->eventStartingAt(now()->addHour(), GameVersion::factory()->create(['release_date' => Carbon::parse('2023-01-01')]));
        $this->eventStartingAt(now()->addHours(2));

        $this->assertTrue(FindNextRaidingEvent::run($this->gameVersion)?->is($tomorrow));
    }

    #[Test]
    public function it_includes_an_event_in_progress(): void
    {
        $inProgress = Event::factory()->live()->forGameVersion($this->gameVersion)->create([
            'start_time' => now()->subMinutes(30),
            'end_time' => now()->addHours(2),
        ]);
        $this->eventStartingAt(now()->addDay(), $this->gameVersion);

        $this->assertTrue(FindNextRaidingEvent::run($this->gameVersion)?->is($inProgress));
    }

    #[Test]
    public function it_ignores_past_and_template_events(): void
    {
        Event::factory()->live()->forGameVersion($this->gameVersion)->create([
            'start_time' => now()->subHours(4),
            'end_time' => now()->subHour(),
        ]);
        Event::factory()->template()->forGameVersion($this->gameVersion)->create([
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(3),
        ]);

        $this->assertNull(FindNextRaidingEvent::run($this->gameVersion));
    }

    #[Test]
    public function it_returns_nothing_without_any_game_version(): void
    {
        $this->eventStartingAt(now()->addDay());

        $this->assertNull(FindNextRaidingEvent::run(null));
    }

    private function eventStartingAt(SupportCarbon $startTime, ?GameVersion $gameVersion = null): Event
    {
        $factory = Event::factory()->live();

        if ($gameVersion !== null) {
            $factory = $factory->forGameVersion($gameVersion);
        }

        return $factory->create([
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHours(3),
        ]);
    }
}
