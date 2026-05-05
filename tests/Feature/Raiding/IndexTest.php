<?php

namespace Tests\Feature\Raiding;

use App\Models\Event;
use App\Models\Raids\Report;
use App\Models\User;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    protected function mockDiscordChannel(): void
    {
        $channel = Channel::from(['id' => '123456789', 'name' => 'raid-events', 'position' => 1]);

        $this->mock(Discord::class, function (MockInterface $mock) use ($channel) {
            $mock->shouldReceive('getChannel')->andReturn($channel);
        });
    }

    #[Test]
    public function guests_can_view_the_raiding_index(): void
    {
        $response = $this->get(route('raiding.index'));

        $response->assertOk();
    }

    #[Test]
    public function authenticated_users_can_view_the_raiding_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Raiding/Index'));
    }

    #[Test]
    public function deferred_props_are_absent_from_the_initial_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('raiding.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Index')
            ->missing('upcomingEvents')
            ->missing('reports')
        );
    }

    #[Test]
    public function upcoming_events_within_the_next_week_are_returned(): void
    {
        $this->mockDiscordChannel();

        $user = User::factory()->create();

        $upcomingEvent = Event::factory()->create(['start_time' => now()->addDays(3)]);
        $pastEvent = Event::factory()->create(['start_time' => now()->subDay()]);
        $tooFarFutureEvent = Event::factory()->create(['start_time' => now()->addWeek()->addDays(2)]);

        $response = $this->actingAs($user)->get(route('raiding.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('upcomingEvents', fn ($events) => collect($events['data'])->pluck('id')->contains($upcomingEvent->id))
                ->where('upcomingEvents', fn ($events) => ! collect($events['data'])->pluck('id')->contains($pastEvent->id))
                ->where('upcomingEvents', fn ($events) => ! collect($events['data'])->pluck('id')->contains($tooFarFutureEvent->id))
            )
        );
    }

    #[Test]
    public function upcoming_events_are_ordered_by_start_time_ascending(): void
    {
        $this->mockDiscordChannel();

        $user = User::factory()->create();

        $laterEvent = Event::factory()->create(['start_time' => now()->addDays(5)]);
        $earlierEvent = Event::factory()->create(['start_time' => now()->addDays(2)]);

        $response = $this->actingAs($user)->get(route('raiding.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('upcomingEvents', function ($events) use ($earlierEvent, $laterEvent) {
                    $ids = collect($events['data'])->pluck('id')->values();

                    return $ids->search($earlierEvent->id) < $ids->search($laterEvent->id);
                })
            )
        );
    }

    #[Test]
    public function reports_are_limited_to_ten_most_recent(): void
    {
        $user = User::factory()->create();

        Report::factory()->withZone()->count(12)->create([
            'start_time' => fn () => fake()->dateTimeBetween('-30 days', 'now'),
        ]);

        $response = $this->actingAs($user)->get(route('raiding.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('reports', fn ($reports) => count($reports['data']) === 10)
            )
        );
    }

    #[Test]
    public function reports_are_ordered_by_start_time_descending(): void
    {
        $user = User::factory()->create();

        $olderReport = Report::factory()->withZone()->create(['start_time' => now()->subDays(10)]);
        $newerReport = Report::factory()->withZone()->create(['start_time' => now()->subDays(2)]);

        $response = $this->actingAs($user)->get(route('raiding.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('reports', function ($reports) use ($newerReport, $olderReport) {
                    $ids = collect($reports['data'])->pluck('id')->values();

                    return $ids->search($newerReport->id) < $ids->search($olderReport->id);
                })
            )
        );
    }
}
