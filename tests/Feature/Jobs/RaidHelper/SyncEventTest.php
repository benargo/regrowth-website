<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Actions\Characters\MatchCharacterName;
use App\Actions\EventBossResolver;
use App\Enums\SignupStatus;
use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Jobs\RaidHelper\FetchComposition;
use App\Jobs\RaidHelper\SyncEvent;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class SyncEventTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.raidhelper.channel_ids', ['100000000000000001']);

        Queue::fake([FetchComposition::class]);

        $this->gameVersion = GameVersion::factory()->create();
    }

    // ==================== event upsert ====================

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
    public function it_stores_the_game_version_of_the_events_raids(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create(['name' => 'Molten Core']);

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([['id' => $raid->id, 'name' => $raid->name]]),
        ])));

        $this->assertDatabaseHas('events', ['raid_helper_event_id' => '111222333444555001', 'game_version_id' => $gameVersion->id]);
    }

    #[Test]
    public function it_clears_the_game_version_when_the_event_no_longer_has_raids(): void
    {
        $event = Event::factory()->forGameVersion(GameVersion::factory()->create())->create(['raid_helper_event_id' => '111222333444555001']);

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload()));

        $this->assertNull($event->fresh()->game_version_id);
    }

    // ==================== boss sync ====================

    #[Test]
    public function a_zone_without_a_bosses_key_attaches_every_boss_in_the_raid(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $second = Boss::factory()->for($raid)->order(2)->create();
        $first = Boss::factory()->for($raid)->order(1)->create();

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                ['id' => $raid->id, 'name' => $raid->name],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$first->id, $second->id], $event->bosses->pluck('id')->all());
    }

    #[Test]
    public function a_zone_with_an_explicit_subset_attaches_only_those_bosses(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $wanted = Boss::factory()->for($raid)->order(1)->create();
        $skipped = Boss::factory()->for($raid)->order(2)->create();

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                [
                    'id' => $raid->id,
                    'name' => $raid->name,
                    'bosses' => [['id' => $wanted->id, 'name' => $wanted->name]],
                ],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$wanted->id], $event->bosses->pluck('id')->all());
        $this->assertDatabaseMissing('pivot_events_bosses', ['boss_id' => $skipped->id]);
    }

    #[Test]
    public function a_zone_with_an_explicit_empty_bosses_array_attaches_none(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        Boss::factory()->for($raid)->order(1)->create();

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                ['id' => $raid->id, 'name' => $raid->name, 'bosses' => []],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertCount(0, $event->bosses);
        $this->assertCount(1, $event->raids);
    }

    #[Test]
    public function it_numbers_bosses_contiguously_across_two_zones_in_raid_order(): void
    {
        $first = Raid::factory()->create(['name' => 'Molten Core']);
        $second = Raid::factory()->create(['name' => 'Blackwing Lair']);
        $a = Boss::factory()->for($first)->order(1)->create();
        $b = Boss::factory()->for($first)->order(2)->create();
        $c = Boss::factory()->for($second)->order(1)->create();

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                ['id' => $first->id, 'name' => $first->name],
                ['id' => $second->id, 'name' => $second->name],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$a->id, $b->id, $c->id], $event->bosses->pluck('id')->all());
        $this->assertSame([1, 2, 3], $event->bosses->pluck('pivot.sort_order')->all());
    }

    #[Test]
    public function boss_order_decides_the_sequence_when_present(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $a = Boss::factory()->for($raid)->order(1)->create();
        $b = Boss::factory()->for($raid)->order(2)->create();

        // The payload deliberately reverses the bosses' own sort_order.
        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                [
                    'id' => $raid->id,
                    'name' => $raid->name,
                    'bosses' => [
                        ['id' => $a->id, 'name' => $a->name, 'order' => 2],
                        ['id' => $b->id, 'name' => $b->name, 'order' => 1],
                    ],
                ],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$b->id, $a->id], $event->bosses->pluck('id')->all());
    }

    #[Test]
    public function sparse_and_duplicated_payload_order_values_collapse_to_contiguous_positions(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $a = Boss::factory()->for($raid)->order(1)->create();
        $b = Boss::factory()->for($raid)->order(2)->create();
        $c = Boss::factory()->for($raid)->order(3)->create();

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                [
                    'id' => $raid->id,
                    'name' => $raid->name,
                    'bosses' => [
                        ['id' => $a->id, 'name' => $a->name, 'order' => 5],
                        ['id' => $b->id, 'name' => $b->name, 'order' => 5],
                        ['id' => $c->id, 'name' => $c->name, 'order' => 90],
                    ],
                ],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$a->id, $b->id, $c->id], $event->bosses->pluck('id')->all());
        $this->assertSame([1, 2, 3], $event->bosses->pluck('pivot.sort_order')->all());
    }

    #[Test]
    public function zone_order_drives_the_raid_sequence(): void
    {
        $first = Raid::factory()->create(['name' => 'Molten Core']);
        $second = Raid::factory()->create(['name' => 'Blackwing Lair']);

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                ['id' => $first->id, 'name' => $first->name, 'order' => 2],
                ['id' => $second->id, 'name' => $second->name, 'order' => 1],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$second->id, $first->id], $event->raids->pluck('id')->all());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_skips_an_unknown_boss_id_and_completes(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->once();

        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($this->gameVersion))->create(['name' => 'Molten Core']);
        $known = Boss::factory()->for($raid)->order(1)->create();

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                [
                    'id' => $raid->id,
                    'name' => $raid->name,
                    'bosses' => [
                        ['id' => $known->id, 'name' => $known->name],
                        ['id' => 999999, 'name' => 'No Such Boss'],
                    ],
                ],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$known->id], $event->bosses->pluck('id')->all());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_skips_a_boss_belonging_to_a_different_raid(): void
    {
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->once();

        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($this->gameVersion))->create(['name' => 'Molten Core']);
        $mine = Boss::factory()->for($raid)->order(1)->create();
        $foreign = Boss::factory()->for(Raid::factory()->create())->order(1)->create();

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                [
                    'id' => $raid->id,
                    'name' => $raid->name,
                    'bosses' => [
                        ['id' => $mine->id, 'name' => $mine->name],
                        ['id' => $foreign->id, 'name' => $foreign->name],
                    ],
                ],
            ]),
        ]));

        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$mine->id], $event->bosses->pluck('id')->all());
        $this->assertDatabaseMissing('pivot_events_bosses', ['boss_id' => $foreign->id]);
    }

    #[Test]
    public function re_running_the_same_payload_is_idempotent(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $a = Boss::factory()->for($raid)->order(1)->create();
        $b = Boss::factory()->for($raid)->order(2)->create();

        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                ['id' => $raid->id, 'name' => $raid->name],
            ]),
        ]));

        SyncEvent::dispatchSync($data);
        SyncEvent::dispatchSync($data);

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$a->id, $b->id], $event->bosses->pluck('id')->all());
        $this->assertDatabaseCount('pivot_events_bosses', 2);
    }

    #[Test]
    public function a_mid_insert_re_sync_resolves_in_the_new_order(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $a = Boss::factory()->for($raid)->order(1)->create();
        $b = Boss::factory()->for($raid)->order(2)->create();
        $c = Boss::factory()->for($raid)->order(3)->create();

        $zone = fn (array $bosses): string => $this->zonePayload([
            ['id' => $raid->id, 'name' => $raid->name, 'bosses' => $bosses],
        ]);

        // The event first holds a and c.
        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $zone([
                ['id' => $a->id, 'name' => $a->name],
                ['id' => $c->id, 'name' => $c->name],
            ]),
        ])));

        // b is then inserted between them.
        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $zone([
                ['id' => $a->id, 'name' => $a->name],
                ['id' => $b->id, 'name' => $b->name],
                ['id' => $c->id, 'name' => $c->name],
            ]),
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$a->id, $b->id, $c->id], $event->bosses->pluck('id')->all());
    }

    #[Test]
    public function a_full_reversal_resolves_in_the_new_order(): void
    {
        $raid = Raid::factory()->create(['name' => 'Molten Core']);
        $a = Boss::factory()->for($raid)->order(1)->create();
        $b = Boss::factory()->for($raid)->order(2)->create();
        $c = Boss::factory()->for($raid)->order(3)->create();

        $zone = fn (array $bosses): string => $this->zonePayload([
            ['id' => $raid->id, 'name' => $raid->name, 'bosses' => $bosses],
        ]);

        $entry = fn (Boss $boss, int $order): array => [
            'id' => $boss->id,
            'name' => $boss->name,
            'order' => $order,
        ];

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $zone([$entry($a, 1), $entry($b, 2), $entry($c, 3)]),
        ])));

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $zone([$entry($c, 1), $entry($b, 2), $entry($a, 3)]),
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$c->id, $b->id, $a->id], $event->bosses->pluck('id')->all());
    }

    #[Test]
    public function a_zone_removed_from_the_payload_detaches_its_bosses(): void
    {
        $kept = Raid::factory()->create(['name' => 'Molten Core']);
        $dropped = Raid::factory()->create(['name' => 'Blackwing Lair']);
        $keptBoss = Boss::factory()->for($kept)->order(1)->create();
        $droppedBoss = Boss::factory()->for($dropped)->order(1)->create();

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                ['id' => $kept->id, 'name' => $kept->name],
                ['id' => $dropped->id, 'name' => $dropped->name],
            ]),
        ])));

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([
                ['id' => $kept->id, 'name' => $kept->name],
            ]),
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();

        $this->assertSame([$keptBoss->id], $event->bosses->pluck('id')->all());
        $this->assertDatabaseMissing('pivot_events_bosses', ['boss_id' => $droppedBoss->id]);
        $this->assertDatabaseMissing('pivot_events_raids', ['raid_id' => $dropped->id]);
    }

    // ==================== bench sync ====================

    #[Test]
    public function it_marks_signed_up_characters_not_in_the_comp_as_benched(): void
    {
        $benched = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($this->gameVersion),
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
    public function it_excludes_absence_signups_from_bench(): void
    {
        $character = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $data = EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($this->gameVersion),
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
        $slotted = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
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
            'description' => $this->descriptionInVersion($this->gameVersion),
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
        $removed = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);

        // Arthas was benched from a previous sync.
        $event->characters()->attach($removed->id, [
            'slot_number' => null,
            'group_number' => null,
            'signup_status' => SignupStatus::Unconfirmed->value,
            'is_benched' => true,
        ]);

        // New event data arrives with no sign-ups (Arthas has left the event).
        $data = EventData::from($this->minimalEventPayload([
            'id' => '111222333444555001',
            'description' => $this->descriptionInVersion($this->gameVersion),
        ]));

        SyncEvent::dispatchSync($data);

        $this->assertFalse($event->characters()->where('character_id', $removed->id)->exists());
    }

    #[Test]
    public function it_does_not_detach_slotted_characters_absent_from_sign_ups(): void
    {
        $slotted = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);

        // Arthas is slotted (not benched) by SyncComposition.
        $event->characters()->attach($slotted->id, [
            'slot_number' => 2,
            'group_number' => 1,
            'signup_status' => SignupStatus::Confirmed->value,
            'is_benched' => false,
        ]);

        // New event data arrives with no sign-ups for Arthas.
        $data = EventData::from($this->minimalEventPayload([
            'id' => '111222333444555001',
            'description' => $this->descriptionInVersion($this->gameVersion),
        ]));

        SyncEvent::dispatchSync($data);

        $this->assertTrue($event->characters()->where('character_id', $slotted->id)->exists());
    }

    #[TestWith(['Bench'])]
    #[TestWith(['Late'])]
    #[TestWith(['Tentative'])]
    #[Test]
    public function it_benches_sign_ups_who_are_not_absent(string $className): void
    {
        $arthas = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($this->gameVersion),
            'signUps' => [$this->minimalSignUpPayload(['name' => 'Arthas', 'className' => $className])],
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertTrue($event->characters()->whereKey($arthas->id)->wherePivot('is_benched', true)->exists());
    }

    #[Test]
    public function it_benches_sign_ups_among_the_events_game_version(): void
    {
        $arthas = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        Character::factory()->forGameVersion(GameVersion::factory()->create())->create(['name' => 'Arthas']);

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($this->gameVersion),
            'signUps' => [$this->minimalSignUpPayload(['name' => 'Arthas'])],
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertSame([$arthas->id], $event->characters()->pluck('characters.id')->all());
    }

    #[Test]
    public function it_benches_a_character_signed_up_twice_once(): void
    {
        $arthas = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($this->gameVersion),
            'signUps' => [
                $this->minimalSignUpPayload(['id' => '300000000000000001', 'name' => 'Arthas']),
                $this->minimalSignUpPayload(['id' => '300000000000000002', 'name' => 'Arthas']),
            ],
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertSame([$arthas->id], $event->characters()->pluck('characters.id')->all());
    }

    #[Test]
    public function it_benches_a_character_once_when_two_sign_up_names_resolve_to_it(): void
    {
        $deo = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Déo']);

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($this->gameVersion),
            'signUps' => [
                $this->minimalSignUpPayload(['id' => '300000000000000001', 'name' => 'Déo']),
                $this->minimalSignUpPayload(['id' => '300000000000000002', 'name' => 'Deo']),
            ],
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertSame([$deo->id], $event->characters()->pluck('characters.id')->all());
    }

    #[Group('error-handling')]
    #[Test]
    public function it_skips_and_logs_a_sign_up_name_that_matches_several_characters(): void
    {
        Log::spy();
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Tears']);
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Teärs']);

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($this->gameVersion),
            'signUps' => [$this->minimalSignUpPayload(['name' => 'TEARS'])],
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertCount(0, $event->characters);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Skipped a character name that matches more than one character.'
            && $context['source'] === 'raidhelper.signups'
            && $context['name'] === 'TEARS');
    }

    #[Group('error-handling')]
    #[Test]
    public function it_leaves_the_bench_alone_when_the_event_has_no_game_version(): void
    {
        Log::spy();
        MatchCharacterName::shouldNotRun();
        $benched = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);
        $event->characters()->attach($benched->id, [
            'slot_number' => null,
            'group_number' => null,
            'signup_status' => SignupStatus::Unconfirmed->value,
            'is_benched' => true,
        ]);
        $versionlessRaid = Raid::factory()->for(Phase::factory())->create();

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->zonePayload([['id' => $versionlessRaid->id, 'name' => $versionlessRaid->name]]),
            'signUps' => [$this->minimalSignUpPayload(['name' => 'Jaina'])],
        ])));

        $this->assertNull($event->fresh()->game_version_id);
        $this->assertTrue($event->characters()->whereKey($benched->id)->exists());
        Queue::assertPushed(FetchComposition::class);
        Log::shouldHaveReceived('error')->once();
    }

    #[Test]
    public function it_keeps_benched_characters_outside_the_events_game_version(): void
    {
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);
        $otherVersions = Character::factory()->forGameVersion(GameVersion::factory()->create())->create(['name' => 'Jaina']);
        $versionless = Character::factory()->create(['name' => 'Thrall']);

        foreach ([$otherVersions, $versionless] as $character) {
            $event->characters()->attach($character->id, [
                'slot_number' => null,
                'group_number' => null,
                'signup_status' => SignupStatus::Unconfirmed->value,
                'is_benched' => true,
            ]);
        }

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($this->gameVersion),
        ])));

        $this->assertTrue($event->characters()->whereKey($otherVersions->id)->exists());
        $this->assertTrue($event->characters()->whereKey($versionless->id)->exists());
    }

    #[Test]
    public function it_benches_against_the_new_game_version_when_the_zones_change(): void
    {
        $newGameVersion = GameVersion::factory()->create();
        Event::factory()->forGameVersion($this->gameVersion)->create(['raid_helper_event_id' => '111222333444555001']);
        $oldArthas = Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'Arthas']);
        $newArthas = Character::factory()->forGameVersion($newGameVersion)->create(['name' => 'Arthas']);

        SyncEvent::dispatchSync(EventData::from($this->minimalEventPayload([
            'description' => $this->descriptionInVersion($newGameVersion),
            'signUps' => [$this->minimalSignUpPayload(['name' => 'Arthas'])],
        ])));

        $event = Event::where('raid_helper_event_id', '111222333444555001')->first();
        $this->assertTrue($event->characters()->whereKey($newArthas->id)->exists());
        $this->assertFalse($event->characters()->whereKey($oldArthas->id)->exists());
    }

    // ==================== channel filtering ====================

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

    // ==================== timezone handling ====================

    #[Test]
    public function it_captures_the_timezone_at_construction_time(): void
    {
        config()->set('app.timezone', 'Pacific/Auckland');

        $data = EventData::from($this->minimalEventPayload(['startTime' => 1700000000]));
        $job = new SyncEvent($data);

        // Change timezone after construction — the stored datetime must still reflect Pacific/Auckland.
        config()->set('app.timezone', 'America/New_York');

        $job->handle(app(EventBossResolver::class));

        $expected = Carbon::createFromTimestamp(1700000000)->setTimezone('Pacific/Auckland')->toDateTimeString();

        $this->assertDatabaseHas('events', [
            'raid_helper_event_id' => '111222333444555001',
            'start_time' => $expected,
        ]);
    }

    // ==================== side effects ====================

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

    // ==================== helpers ====================

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
     * An event description naming one raid of the given game version, so the
     * synced event takes that version.
     */
    private function descriptionInVersion(GameVersion $gameVersion): string
    {
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create();

        return $this->zonePayload([['id' => $raid->id, 'name' => $raid->name]]);
    }

    /**
     * Build an event description carrying the given zone payload.
     *
     * @param  array<int, array<string, mixed>>  $zones
     */
    private function zonePayload(array $zones): string
    {
        return "-# Do not edit below this line...\n".json_encode($zones);
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
