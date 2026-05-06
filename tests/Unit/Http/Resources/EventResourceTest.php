<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Raid;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventResourceTest extends TestCase
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

        $array = (new EventResource($event))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('start_time', $array);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertArrayHasKey('duration', $array);
        $this->assertArrayHasKey('channel', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventResource($event))->toArray(new Request);

        $this->assertSame($event->id, $array['id']);
        $this->assertSame($event->title, $array['title']);
        $this->assertSame($event->start_time->toIso8601String(), $array['start_time']);
        $this->assertSame($event->end_time->toIso8601String(), $array['end_time']);
    }

    #[Test]
    public function it_returns_duration_as_seconds_between_start_and_end(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventResource($event))->toArray(new Request);

        $expected = $event->start_time->diffInSeconds($event->end_time);
        $this->assertSame($expected, $array['duration']);
    }

    #[Test]
    public function it_returns_channel_as_an_array(): void
    {
        $this->mockChannel(id: '999888777', name: 'raid-chat', position: 3);
        $event = Event::factory()->create();

        $array = (new EventResource($event))->toArray(new Request);

        $this->assertIsArray($array['channel']);
        $this->assertSame('999888777', $array['channel']['id']);
        $this->assertSame('raid-chat', $array['channel']['name']);
        $this->assertSame(3, $array['channel']['position']);
    }

    #[Test]
    public function it_excludes_raids_when_relation_is_not_loaded(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventResource($event))->toArray(new Request);

        $this->assertArrayNotHasKey('raids', $array);
    }

    #[Test]
    public function it_includes_raid_data_when_relation_is_loaded(): void
    {
        $this->mockChannel();
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $event = Event::factory()->create();
        $event->raids()->attach($raid);
        $event->load('raids');

        $array = (new EventResource($event))->toArray(new Request);

        $this->assertArrayHasKey('raids', $array);
        $this->assertSame($raid->id, $array['raids'][0]['id']);
        $this->assertSame('Karazhan', $array['raids'][0]['name']);
        $this->assertSame($raid->slug, $array['raids'][0]['slug']);
        $this->assertSame($raid->difficulty, $array['raids'][0]['difficulty']);
        $this->assertSame($raid->max_players, $array['raids'][0]['max_players']);
    }

    #[Test]
    public function it_excludes_characters_when_relation_is_not_loaded(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventResource($event))->toArray(new Request);

        $this->assertArrayNotHasKey('characters', $array);
    }

    #[Test]
    public function it_includes_character_data_when_relation_is_loaded(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();
        $event->characters()->createMany([
            ['name' => 'Warrior'],
            ['name' => 'Mage'],
        ]);
        $event->load('characters');

        $array = (new EventResource($event))->toArray(new Request);

        $this->assertArrayHasKey('characters', $array);
        $this->assertCount(2, $array['characters']);
        $this->assertSame('Warrior', $array['characters'][0]['name']);
        $this->assertSame('Mage', $array['characters'][1]['name']);
        $this->assertArrayHasKey('id', $array['characters'][0]);
        $this->assertArrayHasKey('id', $array['characters'][1]);
    }
}
