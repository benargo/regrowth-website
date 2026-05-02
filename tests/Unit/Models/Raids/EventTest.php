<?php

namespace Tests\Unit\Models\Raids;

use App\Models\Character;
use App\Models\Raids\Event;
use App\Models\Raids\EventCharacter;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class EventTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Event::class;
    }

    #[Test]
    public function it_uses_raid_events_table(): void
    {
        $model = new Event;

        $this->assertSame('raid_events', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Event;

        $this->assertFillable($model, [
            'id',
            'raid_helper_event_id',
            'title',
            'start_time',
            'end_time',
            'channel_id',
        ]);
    }

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $model = new Event;

        $this->assertHidden($model, ['channel_id']);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Event;

        $this->assertCasts($model, [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ]);
    }

    #[Test]
    public function channel_attribute_returns_discord_channel(): void
    {
        $channelId = '123456789';
        $channel = Channel::from(['id' => $channelId]);

        $discord = $this->createMock(Discord::class);
        $discord->expects($this->once())
            ->method('getChannel')
            ->with($channelId)
            ->willReturn($channel);

        $this->app->instance(Discord::class, $discord);

        $event = Event::factory()->make(['channel_id' => $channelId]);

        $this->assertSame($channel, $event->channel);
    }

    #[Test]
    public function channel_attribute_is_cached(): void
    {
        $channelId = '123456789';
        $channel = Channel::from(['id' => $channelId]);

        $discord = $this->createMock(Discord::class);
        $discord->expects($this->once())
            ->method('getChannel')
            ->willReturn($channel);

        $this->app->instance(Discord::class, $discord);

        $event = Event::factory()->make(['channel_id' => $channelId]);

        $event->channel;
        $event->channel;
    }

    #[Test]
    public function it_can_be_created(): void
    {
        $event = $this->create(['title' => 'Naxxramas Night']);

        $this->assertTableHas(['title' => 'Naxxramas Night']);
        $this->assertModelExists($event);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $event = $this->create();

        $this->assertNotNull($event->created_at);
        $this->assertNotNull($event->updated_at);
    }

    #[Test]
    public function raid_helper_event_id_is_unique(): void
    {
        $this->create(['raid_helper_event_id' => 'unique-123']);

        $this->assertUniqueConstraint(fn () => $this->create(['raid_helper_event_id' => 'unique-123']));
    }

    // characters

    #[Test]
    public function characters_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->characters());
    }

    #[Test]
    public function it_can_attach_characters(): void
    {
        $event = $this->create();
        $character = Character::factory()->create();

        $event->characters()->attach($character->id);

        $this->assertCount(1, $event->characters);
        $this->assertSame($character->id, $event->characters->first()->id);
    }

    #[Test]
    public function characters_returns_empty_collection_when_none_attached(): void
    {
        $event = $this->create();

        $this->assertCount(0, $event->characters);
    }

    #[Test]
    public function characters_use_event_character_pivot(): void
    {
        $event = $this->create();
        $character = Character::factory()->create();

        $event->characters()->attach($character->id, [
            'slot_number' => 3,
            'group_number' => 1,
            'is_confirmed' => true,
        ]);

        $pivot = $event->characters->first()->pivot;

        $this->assertInstanceOf(EventCharacter::class, $pivot);
        $this->assertSame(3, $pivot->slot_number);
        $this->assertSame(1, $pivot->group_number);
        $this->assertTrue($pivot->is_confirmed);
    }

    #[Test]
    public function deleting_event_cascades_to_pivot(): void
    {
        $event = $this->create();
        $character = Character::factory()->create();
        $event->characters()->attach($character->id);

        $event->delete();

        $this->assertDatabaseMissing('pivot_raid_events_characters', [
            'event_id' => $event->id,
        ]);
    }

    // leaders

    #[Test]
    public function leaders_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->leaders());
    }

    #[Test]
    public function leaders_returns_only_characters_with_is_leader_true(): void
    {
        $event = $this->factory()->withLeader()->create();
        $nonLeader = Character::factory()->create();
        $event->characters()->attach($nonLeader->id, ['is_leader' => false]);

        $this->assertCount(1, $event->leaders);
        $this->assertTrue($event->leaders->first()->pivot->is_leader);
    }

    #[Test]
    public function leaders_returns_empty_when_no_leaders_attached(): void
    {
        $event = $this->create();
        $character = Character::factory()->create();
        $event->characters()->attach($character->id, ['is_leader' => false]);

        $this->assertCount(0, $event->leaders);
    }

    // lootCouncillors

    #[Test]
    public function loot_councillors_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->lootCouncillors());
    }

    #[Test]
    public function loot_councillors_returns_only_characters_with_is_loot_councillor_true(): void
    {
        $event = $this->factory()->withLootCouncillor()->create();
        $regular = Character::factory()->create();
        $event->characters()->attach($regular->id, ['is_loot_councillor' => false]);

        $this->assertCount(1, $event->lootCouncillors);
        $this->assertTrue($event->lootCouncillors->first()->pivot->is_loot_councillor);
    }

    #[Test]
    public function loot_councillors_returns_empty_when_none_attached(): void
    {
        $event = $this->create();

        $this->assertCount(0, $event->lootCouncillors);
    }
}
