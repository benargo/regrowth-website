<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Jobs\RaidHelper\SyncEvent;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_new_event_from_event_data(): void
    {
        $data = EventData::from($this->minimalEventPayload(['id' => '111222333444555001']));

        SyncEvent::dispatchSync($data);

        $this->assertDatabaseHas('events', [
            'raid_helper_event_id' => '111222333444555001',
            'title' => 'Weekly Raid',
        ]);
    }

    #[Test]
    public function it_updates_an_existing_event_matched_by_raid_helper_event_id(): void
    {
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001', 'title' => 'Old Title']);

        $data = EventData::from($this->minimalEventPayload(['id' => '111222333444555001', 'title' => 'New Title']));

        SyncEvent::dispatchSync($data);

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'New Title']);
    }

    #[Test]
    public function it_syncs_raids_decoded_from_the_event_description(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $description = "-# Do not edit below this line...\n".json_encode([['id' => $raid->id, 'name' => $raid->name]]);

        $data = EventData::from($this->minimalEventPayload(['description' => $description]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertTrue($event->raids->contains($raid));
    }

    #[Test]
    public function it_marks_signed_up_characters_not_in_the_comp_as_benched(): void
    {
        $benched = Character::factory()->create(['name' => 'Arthas']);
        $data = EventData::from($this->minimalEventPayload([
            'signUps' => [
                $this->minimalSignUpPayload(['name' => 'Arthas', 'className' => 'Warrior']),
            ],
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $pivot = $event->characters()->where('character_id', $benched->id)->first()?->pivot;

        $this->assertNotNull($pivot);
        $this->assertTrue((bool) $pivot->is_benched);
        $this->assertNull($pivot->slot_number);
        $this->assertNull($pivot->group_number);
    }

    #[Test]
    public function it_excludes_absence_late_and_tentative_signups_from_bench(): void
    {
        $character = Character::factory()->create(['name' => 'Arthas']);
        $data = EventData::from($this->minimalEventPayload([
            'signUps' => [
                $this->minimalSignUpPayload(['name' => 'Arthas', 'className' => 'Absence']),
            ],
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertFalse($event->characters()->where('character_id', $character->id)->exists());
    }

    #[Test]
    public function it_skips_unresolvable_character_names_silently(): void
    {
        $data = EventData::from($this->minimalEventPayload([
            'signUps' => [
                $this->minimalSignUpPayload(['name' => 'NoSuchCharacter', 'className' => 'Warrior']),
            ],
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertCount(0, $event->characters);
    }

    #[Test]
    public function it_broadcasts_composition_changed(): void
    {
        EventFacade::fake();

        $data = EventData::from($this->minimalEventPayload());

        SyncEvent::dispatchSync($data);

        EventFacade::assertDispatched(CompositionChanged::class);
    }

    #[Test]
    public function it_flushes_the_events_cache(): void
    {
        Cache::tags(['events'])->put('events:test', 'value', 60);

        $data = EventData::from($this->minimalEventPayload());

        SyncEvent::dispatchSync($data);

        $this->assertNull(Cache::tags(['events'])->get('events:test'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalEventPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '111222333444555001',
            'channelId' => '100000000000000001',
            'leaderId' => '200000000000000001',
            'leaderName' => 'Raid Leader',
            'title' => 'Weekly Raid',
            'description' => '',
            'startTime' => 1700000000,
            'endTime' => 1700007200,
            'closingTime' => 1699999800,
            'lastUpdated' => 1699999000,
            'color' => '0,0,0',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalSignUpPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '300000000000000001',
            'userId' => '300000000000000001',
            'name' => 'SomeCharacter',
            'className' => 'Warrior',
            'specName' => 'Arms',
            'roleName' => 'Melee DPS',
            'entryTime' => 1699990000,
            'position' => 1,
        ], $overrides);
    }
}
