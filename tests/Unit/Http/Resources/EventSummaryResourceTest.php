<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\EventSummaryResource;
use App\Models\Event;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventSummaryResourceTest extends TestCase
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
    public function it_returns_all_expected_keys(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventSummaryResource($event))->toArray(new Request);

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

        $array = (new EventSummaryResource($event))->toArray(new Request);

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

        $array = (new EventSummaryResource($event))->toArray(new Request);

        $expected = $event->start_time->diffInSeconds($event->end_time);
        $this->assertSame($expected, $array['duration']);
    }

    #[Test]
    public function it_returns_channel_as_an_array_with_id_name_and_position(): void
    {
        $this->mockChannel(id: '999888777', name: 'raid-chat', position: 3);
        $event = Event::factory()->create();

        $array = (new EventSummaryResource($event))->toArray(new Request);

        $this->assertIsArray($array['channel']);
        $this->assertSame('999888777', $array['channel']['id']);
        $this->assertSame('raid-chat', $array['channel']['name']);
        $this->assertSame(3, $array['channel']['position']);
    }

    #[Test]
    public function it_omits_channel_when_discord_api_fails(): void
    {
        $discord = $this->createStub(Discord::class);
        $discord->method('getChannel')->willThrowException(new \Exception('Discord unavailable'));
        $this->app->instance(Discord::class, $discord);

        $event = Event::factory()->create();

        $array = (new EventSummaryResource($event))->toArray(new Request);

        $this->assertArrayNotHasKey('channel', $array);
    }

    #[Test]
    public function it_does_not_include_composition_raids_or_assignments(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = (new EventSummaryResource($event))->toArray(new Request);

        $this->assertArrayNotHasKey('composition', $array);
        $this->assertArrayNotHasKey('raids', $array);
        $this->assertArrayNotHasKey('assignments', $array);
    }
}
