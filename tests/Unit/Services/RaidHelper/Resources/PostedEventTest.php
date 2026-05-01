<?php

namespace Tests\Unit\Services\RaidHelper\Resources;

use App\Models\User;
use App\Services\RaidHelper\Resources\PostedEvent;
use DateTime;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

class PostedEventTest extends TestCase
{
    use RefreshDatabase;

    private function minimalPayload(): array
    {
        return ['id' => '334385199974967042'];
    }

    private function fullPayload(): array
    {
        return [
            'id' => '334385199974967042',
            'channelId' => '290926798999357250',
            'leaderId' => '80351110224678912',
            'leaderName' => 'Thrall',
            'title' => 'MC Raid Night',
            'description' => 'Come prepared with consumables.',
            'startTime' => new DateTime('2026-05-01 20:00:00'),
            'endTime' => new DateTime('2026-05-01 23:00:00'),
            'closingTime' => new DateTime('2026-05-01 19:00:00'),
            'templateId' => 'tmpl-abc',
            'color' => '255,0,0',
            'imageUrl' => 'https://example.com/raid.png',
            'softresId' => 'softres-xyz',
            'lastUpdated' => new DateTime('2026-04-30 10:00:00'),
            'signups' => [
                ['id' => 'signup-1', 'name' => 'Jaina'],
                ['id' => 'signup-2', 'name' => 'Uther'],
            ],
        ];
    }

    #[Test]
    public function it_constructs_with_only_the_required_id_field(): void
    {
        $event = PostedEvent::from($this->minimalPayload());

        $this->assertSame('334385199974967042', $event->id);
    }

    #[Test]
    public function optional_fields_default_to_optional_instances_when_omitted(): void
    {
        $event = PostedEvent::from($this->minimalPayload());

        $this->assertInstanceOf(Optional::class, $event->channelId);
        $this->assertInstanceOf(Optional::class, $event->leaderId);
        $this->assertInstanceOf(Optional::class, $event->leaderName);
        $this->assertInstanceOf(Optional::class, $event->title);
        $this->assertInstanceOf(Optional::class, $event->description);
        $this->assertInstanceOf(Optional::class, $event->startTime);
        $this->assertInstanceOf(Optional::class, $event->endTime);
        $this->assertInstanceOf(Optional::class, $event->closingTime);
        $this->assertInstanceOf(Optional::class, $event->templateId);
        $this->assertInstanceOf(Optional::class, $event->color);
        $this->assertInstanceOf(Optional::class, $event->imageUrl);
        $this->assertInstanceOf(Optional::class, $event->softresId);
        $this->assertInstanceOf(Optional::class, $event->lastUpdated);
        $this->assertInstanceOf(Optional::class, $event->signups);
    }

    #[Test]
    public function it_stores_all_scalar_string_fields(): void
    {
        $event = PostedEvent::from($this->fullPayload());

        $this->assertSame('334385199974967042', $event->id);
        $this->assertSame('290926798999357250', $event->channelId);
        $this->assertSame('80351110224678912', $event->leaderId);
        $this->assertSame('Thrall', $event->leaderName);
        $this->assertSame('MC Raid Night', $event->title);
        $this->assertSame('Come prepared with consumables.', $event->description);
        $this->assertSame('tmpl-abc', $event->templateId);
        $this->assertSame('255,0,0', $event->color);
        $this->assertSame('https://example.com/raid.png', $event->imageUrl);
        $this->assertSame('softres-xyz', $event->softresId);
    }

    #[Test]
    public function it_stores_datetime_fields_as_datetime_interface(): void
    {
        $event = PostedEvent::from($this->fullPayload());

        $this->assertInstanceOf(DateTimeInterface::class, $event->startTime);
        $this->assertInstanceOf(DateTimeInterface::class, $event->endTime);
        $this->assertInstanceOf(DateTimeInterface::class, $event->closingTime);
        $this->assertInstanceOf(DateTimeInterface::class, $event->lastUpdated);
    }

    #[Test]
    public function it_stores_datetime_field_values_correctly(): void
    {
        $event = PostedEvent::from($this->fullPayload());

        $this->assertSame('2026-05-01 20:00:00', $event->startTime->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-01 23:00:00', $event->endTime->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-01 19:00:00', $event->closingTime->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-30 10:00:00', $event->lastUpdated->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_stores_the_signups_array(): void
    {
        $event = PostedEvent::from($this->fullPayload());

        $this->assertIsArray($event->signups);
        $this->assertCount(2, $event->signups);
        $this->assertSame('Jaina', $event->signups[0]['name']);
        $this->assertSame('Uther', $event->signups[1]['name']);
    }

    #[Test]
    public function it_stores_an_empty_signups_array(): void
    {
        $event = PostedEvent::from([...$this->minimalPayload(), 'signups' => []]);

        $this->assertIsArray($event->signups);
        $this->assertCount(0, $event->signups);
    }

    #[Test]
    public function to_array_omits_optional_fields_that_were_not_provided(): void
    {
        $event = PostedEvent::from($this->minimalPayload());
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayNotHasKey('channel_id', $array);
        $this->assertArrayNotHasKey('leader_id', $array);
        $this->assertArrayNotHasKey('title', $array);
        $this->assertArrayNotHasKey('signups', $array);
    }

    #[Test]
    public function to_array_includes_all_provided_fields_in_snake_case(): void
    {
        $event = PostedEvent::from($this->fullPayload());
        $array = $event->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('channel_id', $array);
        $this->assertArrayHasKey('leader_id', $array);
        $this->assertArrayHasKey('leader_name', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('start_time', $array);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertArrayHasKey('closing_time', $array);
        $this->assertArrayHasKey('template_id', $array);
        $this->assertArrayHasKey('color', $array);
        $this->assertArrayHasKey('image_url', $array);
        $this->assertArrayHasKey('softres_id', $array);
        $this->assertArrayHasKey('last_updated', $array);
        $this->assertArrayHasKey('signups', $array);
    }

    #[Test]
    public function user_returns_null_when_leader_id_is_not_set(): void
    {
        $event = PostedEvent::from($this->minimalPayload());

        $this->assertNull($event->user());
    }

    #[Test]
    public function user_returns_null_when_leader_id_does_not_match_a_user(): void
    {
        $event = PostedEvent::from([...$this->minimalPayload(), 'leaderId' => 'nonexistent-discord-id']);

        $this->assertNull($event->user());
    }

    #[Test]
    public function user_returns_null_when_leader_id_is_valid_but_user_does_not_exist_in_the_database(): void
    {
        $event = PostedEvent::from([...$this->minimalPayload(), 'leaderId' => '80351110224678912']);

        $this->assertNull($event->user());
    }

    #[Test]
    public function user_returns_a_user_when_leader_id_matches_a_user(): void
    {
        $user = User::factory()->create(['id' => '80351110224678912']);
        $event = PostedEvent::from([...$this->minimalPayload(), 'leaderId' => '80351110224678912']);

        $result = $event->user();

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user->id, $result->id);
    }

    #[Test]
    public function rules_returns_time_ordering_constraints(): void
    {
        $rules = PostedEvent::rules();

        $this->assertArrayHasKey('startTime', $rules);
        $this->assertArrayHasKey('endTime', $rules);
        $this->assertArrayHasKey('closingTime', $rules);

        $this->assertContains('before:endTime', $rules['startTime']);
        $this->assertContains('before_or_equal:closingTime', $rules['startTime']);
        $this->assertContains('after:startTime', $rules['endTime']);
        $this->assertContains('after:closingTime', $rules['endTime']);
        $this->assertContains('before_or_equal:startTime', $rules['closingTime']);
    }
}
