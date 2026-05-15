<?php

namespace Tests\Feature\Raiding;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NextEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_to_the_next_upcoming_event(): void
    {
        $user = User::factory()->create();

        $nextEvent = Event::factory()->create(['start_time' => now()->addDay()]);

        $this->actingAs($user)
            ->get(route('raiding.plans.next'))
            ->assertRedirect(route('raiding.plans.show', $nextEvent));
    }

    #[Test]
    public function it_redirects_to_the_earliest_upcoming_event_when_multiple_exist(): void
    {
        $user = User::factory()->create();

        $laterEvent = Event::factory()->create(['start_time' => now()->addDays(5)]);
        $earlierEvent = Event::factory()->create(['start_time' => now()->addDays(2)]);

        $this->actingAs($user)
            ->get(route('raiding.plans.next'))
            ->assertRedirect(route('raiding.plans.show', $earlierEvent));
    }

    #[Test]
    public function it_excludes_past_events(): void
    {
        $user = User::factory()->create();

        Event::factory()->create(['start_time' => now()->subDay()]);

        $this->actingAs($user)
            ->get(route('raiding.plans.next'))
            ->assertRedirect(route('raiding.index'));
    }

    #[Test]
    public function it_redirects_to_the_raiding_index_when_no_upcoming_events_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('raiding.plans.next'))
            ->assertRedirect(route('raiding.index'));
    }
}
