<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Enums\SignupStatus;
use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Jobs\RaidHelper\FetchComposition;
use App\Jobs\RaidHelper\SyncEvent;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class SyncEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.raidhelper.channel_ids', ['100000000000000001']);
    }

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
    public function it_uses_a_single_query_to_resolve_raids_from_the_description(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $description = "-# Do not edit below this line...\n".json_encode([['id' => $raid->id, 'name' => $raid->name]]);

        $data = EventData::from($this->minimalEventPayload(['description' => $description]));

        // Run the job twice to confirm the same raid is associated each time (not duplicated by double-query).
        SyncEvent::dispatchSync($data);
        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertCount(1, $event->raids);
        $this->assertTrue($event->raids->contains($raid));
    }

    #[Test]
    public function it_does_not_overwrite_slotted_characters_pivot_data_set_by_sync_composition(): void
    {
        $slotted = Character::factory()->create(['name' => 'Arthas']);
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);

        // Simulate SyncComposition having placed Arthas in slot 1, group 1, not benched.
        $event->characters()->attach($slotted->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'signup_status' => SignupStatus::Confirmed->value,
            'is_benched' => false,
        ]);

        // SyncEvent fires (e.g. title change webhook) with Arthas in sign-ups.
        $data = EventData::from($this->minimalEventPayload([
            'id' => '111222333444555001',
            'signUps' => [
                $this->minimalSignUpPayload(['name' => 'Arthas', 'className' => 'Warrior']),
            ],
        ]));

        SyncEvent::dispatchSync($data);

        $pivot = $event->characters()->where('character_id', $slotted->id)->first()?->pivot;

        $this->assertNotNull($pivot);
        $this->assertSame(1, (int) $pivot->slot_number);
        $this->assertSame(1, (int) $pivot->group_number);
        $this->assertFalse((bool) $pivot->is_benched);
    }

    #[Test]
    public function it_detaches_benched_characters_who_are_no_longer_in_sign_ups(): void
    {
        $removed = Character::factory()->create(['name' => 'Arthas']);
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);

        // Arthas was benched from a previous sync.
        $event->characters()->attach($removed->id, [
            'slot_number' => null,
            'group_number' => null,
            'signup_status' => SignupStatus::Unconfirmed->value,
            'is_benched' => true,
        ]);

        // New event data arrives with no sign-ups (Arthas has left the event).
        $data = EventData::from($this->minimalEventPayload(['id' => '111222333444555001']));

        SyncEvent::dispatchSync($data);

        $this->assertFalse($event->characters()->where('character_id', $removed->id)->exists());
    }

    #[Test]
    public function it_does_not_detach_slotted_characters_absent_from_sign_ups(): void
    {
        $slotted = Character::factory()->create(['name' => 'Arthas']);
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);

        // Arthas is slotted (not benched) by SyncComposition.
        $event->characters()->attach($slotted->id, [
            'slot_number' => 2,
            'group_number' => 1,
            'signup_status' => SignupStatus::Confirmed->value,
            'is_benched' => false,
        ]);

        // New event data arrives with no sign-ups for Arthas.
        $data = EventData::from($this->minimalEventPayload(['id' => '111222333444555001']));

        SyncEvent::dispatchSync($data);

        $this->assertTrue($event->characters()->where('character_id', $slotted->id)->exists());
    }

    #[Test]
    public function it_does_not_upsert_an_event_from_an_unlisted_channel(): void
    {
        config()->set('services.raidhelper.channel_ids', ['999000000000000001']);

        $data = EventData::from($this->minimalEventPayload(['channelId' => '100000000000000001']));

        SyncEvent::dispatchSync($data);

        $this->assertDatabaseCount('events', 0);
    }

    #[Test]
    public function it_upserts_an_event_when_its_channel_is_in_the_allowed_list(): void
    {
        config()->set('services.raidhelper.channel_ids', ['100000000000000001']);

        $data = EventData::from($this->minimalEventPayload(['channelId' => '100000000000000001']));

        SyncEvent::dispatchSync($data);

        $this->assertDatabaseHas('events', [
            'raid_helper_event_id' => '111222333444555001',
        ]);
    }

    #[Test]
    public function it_captures_the_timezone_at_construction_time(): void
    {
        config()->set('app.timezone', 'Pacific/Auckland');

        $data = EventData::from($this->minimalEventPayload(['startTime' => 1700000000]));
        $job = new SyncEvent($data);

        // Change timezone after construction — the stored datetime must still reflect Pacific/Auckland.
        config()->set('app.timezone', 'America/New_York');

        $job->handle();

        $expected = Carbon::createFromTimestamp(1700000000)->setTimezone('Pacific/Auckland')->toDateTimeString();

        $this->assertDatabaseHas('events', [
            'raid_helper_event_id' => '111222333444555001',
            'start_time' => $expected,
        ]);
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
    public function it_dispatches_fetch_composition_after_syncing(): void
    {
        Queue::fake()->except([SyncEvent::class]);

        $data = EventData::from($this->minimalEventPayload());

        SyncEvent::dispatchSync($data);

        Queue::assertPushed(FetchComposition::class, function (FetchComposition $job) {
            $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

            return $job->eventId === $event->id;
        });
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
