<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionSlotData;
use App\Jobs\RaidHelper\SyncComposition;
use App\Models\Character;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncCompositionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_does_nothing_when_event_is_not_found(): void
    {
        $data = $this->minimalCompositionData();

        SyncComposition::dispatchSync('non-existent-event-id', $data);

        $this->assertDatabaseCount('pivot_events_characters', 0);
    }

    #[Test]
    public function it_syncs_slotted_characters_from_composition_data(): void
    {
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);
        $character = Character::factory()->create(['name' => 'Arthas']);

        $data = $this->minimalCompositionData([
            new CompositionSlotData(
                id: 'slot-1',
                name: 'Arthas',
                groupNumber: 1,
                slotNumber: 3,
                className: 'Warrior',
                classEmoteId: '123',
                specName: 'Arms',
                specEmoteId: '456',
                isConfirmed: true,
                color: '0,0,0',
            ),
        ]);

        SyncComposition::dispatchSync('111222333444555001', $data);

        $pivot = $event->characters()->where('character_id', $character->id)->first()?->pivot;

        $this->assertNotNull($pivot);
        $this->assertEquals(3, $pivot->slot_number);
        $this->assertEquals(1, $pivot->group_number);
        $this->assertTrue((bool) $pivot->is_confirmed);
        $this->assertFalse((bool) $pivot->is_benched);
    }

    #[Test]
    public function it_preserves_existing_benched_pivots(): void
    {
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);
        $benched = Character::factory()->withUniqueName()->create();

        $event->characters()->attach($benched->id, [
            'slot_number' => null,
            'group_number' => null,
            'is_confirmed' => false,
            'is_benched' => true,
        ]);

        $data = $this->minimalCompositionData([]);

        SyncComposition::dispatchSync('111222333444555001', $data);

        $pivot = $event->characters()->where('character_id', $benched->id)->first()?->pivot;

        $this->assertNotNull($pivot, 'Benched character should still be attached');
        $this->assertTrue((bool) $pivot->is_benched);
    }

    #[Test]
    public function it_removes_characters_no_longer_slotted_and_not_benched(): void
    {
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);
        $slotted = Character::factory()->withUniqueName()->create();

        $event->characters()->attach($slotted->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
            'is_benched' => false,
        ]);

        $data = $this->minimalCompositionData([]);

        SyncComposition::dispatchSync('111222333444555001', $data);

        $this->assertFalse(
            $event->characters()->where('character_id', $slotted->id)->exists(),
            'Previously slotted (non-benched) character not in new comp should be detached'
        );
    }

    #[Test]
    public function it_broadcasts_composition_changed(): void
    {
        EventFacade::fake();

        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);
        $data = $this->minimalCompositionData();

        SyncComposition::dispatchSync('111222333444555001', $data);

        EventFacade::assertDispatched(CompositionChanged::class);
    }

    #[Test]
    public function it_flushes_the_events_cache(): void
    {
        Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);

        Cache::tags(['events'])->put('events:test', 'value', 60);

        $data = $this->minimalCompositionData();

        SyncComposition::dispatchSync('111222333444555001', $data);

        $this->assertNull(Cache::tags(['events'])->get('events:test'));
    }

    /**
     * @param  array<int, CompositionSlotData>  $slots
     */
    private function minimalCompositionData(array $slots = []): CompositionData
    {
        return new CompositionData(
            id: 'comp-id',
            title: 'Comp Title',
            editPermissions: 'managers',
            showRoles: true,
            showClasses: true,
            groupCount: 5,
            slotCount: 25,
            groups: [],
            dividers: [],
            classes: [],
            slots: $slots,
        );
    }
}
