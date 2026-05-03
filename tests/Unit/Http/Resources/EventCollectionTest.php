<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventCollection;
use App\Models\Raids\Event;
use App\Models\TBC\Raid;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventCollectionTest extends TestCase
{
    use RefreshDatabase;

    private function mockChannel(string $id = '111222333', ?string $name = 'general', ?int $position = 0): Channel
    {
        $channel = new Channel(id: $id, name: $name, position: $position);

        $discord = $this->createStub(Discord::class);
        $discord->method('getChannel')->willReturn($channel);
        $this->app->instance(Discord::class, $discord);

        return $channel;
    }

    #[Test]
    public function it_returns_all_expected_keys_for_each_event(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventCollection(collect([$event])))->toArray(new Request);

        $this->assertArrayHasKey('id', $array[0]);
        $this->assertArrayHasKey('title', $array[0]);
        $this->assertArrayHasKey('start_time', $array[0]);
        $this->assertArrayHasKey('end_time', $array[0]);
        $this->assertArrayHasKey('duration', $array[0]);
        $this->assertArrayHasKey('channel', $array[0]);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventCollection(collect([$event])))->toArray(new Request);

        $this->assertSame($event->id, $array[0]['id']);
        $this->assertSame($event->title, $array[0]['title']);
        $this->assertEquals($event->start_time, $array[0]['start_time']);
        $this->assertEquals($event->end_time, $array[0]['end_time']);
    }

    #[Test]
    public function it_returns_duration_as_seconds_between_start_and_end(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventCollection(collect([$event])))->toArray(new Request);

        $expected = $event->start_time->diffInSeconds($event->end_time);
        $this->assertSame($expected, $array[0]['duration']);
    }

    #[Test]
    public function it_returns_channel_as_a_channel_instance(): void
    {
        $this->mockChannel(id: '999888777', name: 'raid-chat', position: 3);
        $event = Event::factory()->create();

        $array = (new EventCollection(collect([$event])))->toArray(new Request);

        $this->assertInstanceOf(Channel::class, $array[0]['channel']);
        $this->assertSame('999888777', $array[0]['channel']->id);
        $this->assertSame('raid-chat', $array[0]['channel']->name);
        $this->assertSame(3, $array[0]['channel']->position);
    }

    #[Test]
    public function it_excludes_raid_when_relation_is_not_loaded(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventCollection(collect([$event])))->toArray(new Request);

        $this->assertArrayNotHasKey('raid', $array[0]);
    }

    #[Test]
    public function it_includes_raid_id_and_name_when_relation_is_loaded(): void
    {
        $this->mockChannel();
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $event = Event::factory()->for($raid)->create();
        $event->load('raid');

        $array = (new EventCollection(collect([$event])))->toArray(new Request);

        $this->assertArrayHasKey('raid', $array[0]);
        $this->assertSame($raid->id, $array[0]['raid']->id);
        $this->assertSame('Karazhan', $array[0]['raid']->name);
    }

    #[Test]
    public function it_returns_multiple_events(): void
    {
        $this->mockChannel();
        $events = Event::factory()->count(3)->create();

        $array = (new EventCollection($events))->toArray(new Request);

        $this->assertCount(3, $array);
    }
}
