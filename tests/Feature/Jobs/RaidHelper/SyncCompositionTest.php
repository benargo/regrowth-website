<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Actions\Characters\MatchCharacterName;
use App\Enums\SignupStatus;
use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionSlotData;
use App\Jobs\RaidHelper\SyncComposition;
use App\Models\Character;
use App\Models\Event;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class SyncCompositionTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameVersion = GameVersion::factory()->create();
    }

    #[Test]
    public function it_does_nothing_when_event_is_not_found(): void
    {
        EventFacade::fake();

        Cache::tags(['events'])->put('events:test', 'value', 60);

        $data = $this->minimalCompositionData();

        SyncComposition::dispatchSync('non-existent-event-id', $data);

        $this->assertDatabaseCount('pivot_events_characters', 0);
        EventFacade::assertNotDispatched(CompositionChanged::class);
        $this->assertEquals('value', Cache::tags(['events'])->get('events:test'));
    }

    #[Test]
    public function it_syncs_slotted_characters_from_composition_data(): void
    {
        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $this->signUp($event, $character);

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
                isConfirmed: SignupStatus::Confirmed,
                color: '0,0,0',
            ),
        ]);

        SyncComposition::dispatchSync('111222333444555001', $data);

        $pivot = $event->characters()->where('character_id', $character->id)->first()?->pivot;

        $this->assertNotNull($pivot);
        $this->assertEquals(3, $pivot->slot_number);
        $this->assertEquals(1, $pivot->group_number);
        $this->assertSame(SignupStatus::Confirmed, $pivot->signup_status);
        $this->assertFalse((bool) $pivot->is_benched);
    }

    #[Test]
    public function it_syncs_a_cancelled_slot(): void
    {
        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $this->signUp($event, $character);

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
                isConfirmed: SignupStatus::Cancelled,
                color: '0,0,0',
            ),
        ]);

        SyncComposition::dispatchSync('111222333444555001', $data);

        $pivot = $event->characters()->where('character_id', $character->id)->first()?->pivot;

        $this->assertNotNull($pivot);
        $this->assertSame(SignupStatus::Cancelled, $pivot->signup_status);
    }

    #[Test]
    public function it_preserves_existing_benched_pivots(): void
    {
        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $benched = Character::factory()->withUniqueName()->forGameVersion($this->gameVersion)->create();

        $event->characters()->attach($benched->id, [
            'slot_number' => null,
            'group_number' => null,
            'signup_status' => SignupStatus::Unconfirmed->value,
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
        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $slotted = Character::factory()->withUniqueName()->forGameVersion($this->gameVersion)->create();

        $event->characters()->attach($slotted->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'signup_status' => SignupStatus::Confirmed->value,
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

        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $data = $this->minimalCompositionData();

        SyncComposition::dispatchSync('111222333444555001', $data);

        EventFacade::assertDispatched(CompositionChanged::class);
    }

    #[Test]
    public function it_flushes_the_events_cache(): void
    {
        Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);

        Cache::tags(['events'])->put('events:test', 'value', 60);

        $data = $this->minimalCompositionData();

        SyncComposition::dispatchSync('111222333444555001', $data);

        $this->assertNull(Cache::tags(['events'])->get('events:test'));
    }

    // ==================== game version ====================

    #[Test]
    public function it_only_slots_the_events_characters_in_its_game_version(): void
    {
        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $arthas = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $otherArthas = Character::factory()->forGameVersion(GameVersion::factory()->create())->create(['name' => 'Arthas']);
        $jaina = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Jaina']);
        $this->signUp($event, $arthas);

        SyncComposition::dispatchSync('111222333444555001', $this->minimalCompositionData([
            $this->slot('Arthas', 1),
            $this->slot('Jaina', 2),
        ]));

        $this->assertTrue($event->characters()->whereKey($arthas->id)->wherePivot('is_benched', false)->exists());
        $this->assertFalse($event->characters()->whereKey($otherArthas->id)->exists());
        $this->assertFalse($event->characters()->whereKey($jaina->id)->exists());
    }

    #[Test]
    public function it_resolves_a_folded_slot_name_among_the_events_characters_only(): void
    {
        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $tears = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Tears']);
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Teärs']);
        $this->signUp($event, $tears);

        SyncComposition::dispatchSync('111222333444555001', $this->minimalCompositionData([$this->slot('TEARS')]));

        $this->assertTrue($event->characters()->whereKey($tears->id)->wherePivot('is_benched', false)->exists());
    }

    #[Group('error-handling')]
    #[Test]
    public function it_skips_and_logs_a_slot_name_that_matches_several_of_the_events_characters(): void
    {
        Log::spy();
        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $tears = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Tears']);
        $accentedTears = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Teärs']);
        $this->signUp($event, $tears);
        $this->signUp($event, $accentedTears);

        SyncComposition::dispatchSync('111222333444555001', $this->minimalCompositionData([$this->slot('TEARS')]));

        $this->assertFalse($event->characters()->wherePivot('is_benched', false)->exists());
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Skipped a character name that matches more than one character.'
            && $context['source'] === 'raidhelper.composition'
            && $context['game_version_id'] === $this->gameVersion->id
            && $context['name'] === 'TEARS'
            && collect($context['character_ids'])->sort()->values()->all() === collect([$tears->id, $accentedTears->id])->sort()->values()->all());
    }

    #[Group('error-handling')]
    #[Test]
    public function it_leaves_the_composition_alone_when_the_event_has_no_game_version(): void
    {
        EventFacade::fake([CompositionChanged::class]);
        Log::spy();
        MatchCharacterName::shouldNotRun();
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);
        $slotted = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $event->characters()->attach($slotted->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'signup_status' => SignupStatus::Confirmed->value,
            'is_benched' => false,
        ]);
        Cache::tags(['events'])->put('events:test', 'value', 60);

        SyncComposition::dispatchSync('111222333444555001', $this->minimalCompositionData([]));

        $this->assertTrue($event->characters()->whereKey($slotted->id)->exists());
        $this->assertSame('value', Cache::tags(['events'])->get('events:test'));
        EventFacade::assertNotDispatched(CompositionChanged::class);
        Log::shouldHaveReceived('error')->once();
    }

    #[Test]
    public function it_keeps_links_to_characters_outside_the_events_game_version(): void
    {
        $event = Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $otherVersions = Character::factory()->forGameVersion(GameVersion::factory()->create())->create(['name' => 'Jaina']);
        $versionless = Character::factory()->create(['name' => 'Thrall']);

        foreach ([$otherVersions, $versionless] as $character) {
            $event->characters()->attach($character->id, [
                'slot_number' => 1,
                'group_number' => 1,
                'signup_status' => SignupStatus::Confirmed->value,
                'is_benched' => false,
            ]);
        }

        SyncComposition::dispatchSync('111222333444555001', $this->minimalCompositionData([]));

        $this->assertTrue($event->characters()->whereKey($otherVersions->id)->exists());
        $this->assertTrue($event->characters()->whereKey($versionless->id)->exists());
    }

    // ==================== helpers ====================

    private function slot(string $name, int $slotNumber = 1): CompositionSlotData
    {
        return new CompositionSlotData(
            id: "slot-{$slotNumber}",
            name: $name,
            groupNumber: 1,
            slotNumber: $slotNumber,
            className: 'Warrior',
            classEmoteId: '123',
            specName: 'Arms',
            specEmoteId: '456',
            isConfirmed: SignupStatus::Confirmed,
            color: '0,0,0',
        );
    }

    /**
     * Attach the character as an unslotted sign-up, as SyncEvent would before
     * the composition is synced.
     */
    private function signUp(Event $event, Character $character): void
    {
        $event->characters()->attach($character->id, [
            'slot_number' => null,
            'group_number' => null,
            'signup_status' => SignupStatus::Unconfirmed->value,
            'is_benched' => true,
        ]);
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
