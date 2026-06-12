<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Enums\RaidBackground;
use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Http\Integrations\RaidHelper\Requests\GetCompositionRequest;
use App\Http\Integrations\RaidHelper\Requests\GetEventsRequest;
use App\Jobs\RaidHelper\FetchEvents;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\RateLimitedException;
use App\Services\Discord\Resources\Channel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class FetchEventsTest extends TestCase
{
    use RefreshDatabase;

    private Discord&MockInterface $discord;

    private RaidHelperConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discord = Mockery::mock(Discord::class);
        $this->app->instance(Discord::class, $this->discord);

        // Bind a real connector with deterministic config; HTTP is faked per-test.
        $this->connector = new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
        $this->app->instance(RaidHelperConnector::class, $this->connector);
    }

    // -------------------------------------------------------------------------
    // Channel validation
    // -------------------------------------------------------------------------

    #[Test]
    public function it_only_processes_channel_ids_that_belong_to_the_server(): void
    {
        $validChannelId = '100000000000000001';
        $invalidChannelId = '999999999999999999';

        $this->discord->shouldReceive('getGuildChannels')
            ->with('111222333444555666')
            ->andReturn(Collection::make([Channel::from(['id' => $validChannelId])]));

        $this->fakeEmptyEventsPage();

        $job = new FetchEvents([$validChannelId, $invalidChannelId]);
        $job->handle($this->discord, $this->connector);

        Saloon::assertSentCount(1);
    }

    #[Test]
    public function it_skips_all_channels_when_none_belong_to_the_server(): void
    {
        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([]));

        Saloon::fake([]);

        $job = new FetchEvents(['999999999999999999']);
        $job->handle($this->discord, $this->connector);

        Saloon::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Event fetching & pagination
    // -------------------------------------------------------------------------

    #[Test]
    public function it_fetches_events_for_each_valid_channel(): void
    {
        $channelOneId = '100000000000000001';
        $channelTwoId = '100000000000000002';

        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([
                Channel::from(['id' => $channelOneId]),
                Channel::from(['id' => $channelTwoId]),
            ]));

        $this->fakeEmptyEventsPage();

        $job = new FetchEvents([$channelOneId, $channelTwoId]);
        $job->handle($this->discord, $this->connector);

        Saloon::assertSentCount(2);
    }

    #[Test]
    public function it_passes_the_time_filters_to_get_events(): void
    {
        $channelId = '100000000000000001';
        $start = Carbon::parse('2024-01-01 06:00:00', 'UTC');
        $end = Carbon::parse('2024-01-08 05:59:59', 'UTC');

        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => $channelId])]));

        $this->fakeEmptyEventsPage();

        $job = new FetchEvents([$channelId], $start, $end);
        $job->handle($this->discord, $this->connector);

        Saloon::assertSent(function (Request $request) use ($channelId, $start, $end) {
            $headers = $request->headers();

            return $headers->get('StartTimeFilter') === $start->unix()
                && $headers->get('EndTimeFilter') === $end->unix()
                && $headers->get('ChannelFilter') === $channelId;
        });
    }

    #[Test]
    public function it_requests_sign_ups_when_fetching_events(): void
    {
        $channelId = '100000000000000001';

        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => $channelId])]));

        $this->fakeEmptyEventsPage();

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        Saloon::assertSent(function (Request $request) {
            return $request->headers()->get('IncludeSignUps') === 'true';
        });
    }

    #[Test]
    public function it_collects_events_across_multiple_pages(): void
    {
        $channelId = '100000000000000001';
        $payloadOne = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $payloadTwo = $this->minimalListingEventPayload(['id' => '999000000000000002']);

        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => $channelId])]));

        Saloon::fake([
            GetCompositionRequest::class => MockResponse::make(['reason' => 'unknown composition', 'status' => 'failed'], 404),
            MockResponse::make(['eventsTransmitted' => 1000, 'postedEvents' => [$payloadOne]], 200),
            MockResponse::make(['eventsTransmitted' => 1, 'postedEvents' => [$payloadTwo]], 200),
        ]);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $this->assertDatabaseHas('events', ['raid_helper_event_id' => '999000000000000001']);
        $this->assertDatabaseHas('events', ['raid_helper_event_id' => '999000000000000002']);

        // Two event pages + two composition lookups (one per event).
        Saloon::assertSentCount(4);
    }

    // -------------------------------------------------------------------------
    // Event upsert
    // -------------------------------------------------------------------------

    #[Test]
    public function it_creates_a_new_event_when_it_does_not_exist(): void
    {
        $channelId = '100000000000000001';
        $payload = $this->minimalListingEventPayload([
            'id' => '999000000000000001',
            'title' => 'Molten Core',
            'channelId' => $channelId,
        ]);

        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $this->assertDatabaseHas('events', [
            'raid_helper_event_id' => '999000000000000001',
            'title' => 'Molten Core',
        ]);
    }

    #[Test]
    public function it_updates_an_existing_event_when_it_already_exists(): void
    {
        $channelId = '100000000000000001';
        $existingEvent = Event::factory()->create([
            'raid_helper_event_id' => '999000000000000001',
            'title' => 'Old Title',
        ]);

        $payload = $this->minimalListingEventPayload([
            'id' => '999000000000000001',
            'title' => 'New Title',
        ]);

        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $this->assertDatabaseHas('events', [
            'id' => $existingEvent->id,
            'raid_helper_event_id' => '999000000000000001',
            'title' => 'New Title',
        ]);
        $this->assertDatabaseCount('events', 1);
    }

    // -------------------------------------------------------------------------
    // Timezone conversion
    // -------------------------------------------------------------------------

    #[Test]
    public function it_converts_utc_timestamps_to_the_app_timezone_when_storing_events(): void
    {
        $channelId = '100000000000000001';
        $payload = $this->minimalListingEventPayload([
            'id' => '999000000000000001',
            'startTime' => 1700000000, // 2023-11-14 22:13:20 UTC → 2023-11-14 23:13:20 Europe/Paris
            'endTime' => 1700007200,   // 2023-11-14 24:13:20 UTC → 2023-11-15 01:13:20 Europe/Paris
        ]);

        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $appTimezone = config('app.timezone');

        $this->assertSame($appTimezone, $event->start_time->timezoneName);
        $this->assertSame($appTimezone, $event->end_time->timezoneName);
        $this->assertSame(1700000000, $event->start_time->unix());
        $this->assertSame(1700007200, $event->end_time->unix());
    }

    // -------------------------------------------------------------------------
    // Composition sync
    // -------------------------------------------------------------------------

    #[Test]
    public function it_syncs_characters_from_the_comp_slots_to_the_event(): void
    {
        $channelId = '100000000000000001';
        $character = Character::factory()->create(['name' => 'Arthas']);

        $composition = $this->minimalCompositionPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'Arthas', 'slotNumber' => 1, 'groupNumber' => 1, 'isConfirmed' => 'confirmed']),
            ],
        ]);

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $composition);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertTrue($event->characters->contains($character));

        $pivot = $event->characters->find($character->id)->pivot;
        $this->assertSame(1, $pivot->slot_number);
        $this->assertSame(1, $pivot->group_number);
        $this->assertTrue((bool) $pivot->is_confirmed);
    }

    #[Test]
    public function it_skips_comp_slots_where_the_character_does_not_exist(): void
    {
        $channelId = '100000000000000001';

        $composition = $this->minimalCompositionPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'UnknownCharacter']),
            ],
        ]);

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $composition);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(0, $event->characters);
    }

    #[Test]
    public function it_skips_comp_sync_when_no_comp_exists_for_the_event(): void
    {
        $channelId = '100000000000000001';
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);

        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertNotNull($event);
        $this->assertCount(0, $event->characters);
    }

    #[Test]
    public function it_detaches_characters_when_no_comp_exists_for_the_event(): void
    {
        $channelId = '100000000000000001';
        $character = Character::factory()->create(['name' => 'Arthas']);
        $event = Event::factory()->create(['raid_helper_event_id' => '999000000000000001']);
        $event->characters()->attach($character->id, ['slot_number' => 1, 'group_number' => 1, 'is_confirmed' => false]);

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event->refresh();
        $this->assertCount(0, $event->characters);
    }

    #[Test]
    public function it_syncs_multiple_characters_from_comp_slots(): void
    {
        $channelId = '100000000000000001';
        $arthas = Character::factory()->create(['name' => 'Arthas']);
        $sylvanas = Character::factory()->create(['name' => 'Sylvanas']);

        $composition = $this->minimalCompositionPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'Arthas', 'slotNumber' => 1, 'groupNumber' => 1]),
                $this->minimalSlotPayload(['id' => 'slot-2', 'name' => 'Sylvanas', 'slotNumber' => 2, 'groupNumber' => 1]),
            ],
        ]);

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $composition);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(2, $event->characters);
        $this->assertTrue($event->characters->contains($arthas));
        $this->assertTrue($event->characters->contains($sylvanas));
    }

    // -------------------------------------------------------------------------
    // Bench sync
    // -------------------------------------------------------------------------

    #[Test]
    public function it_syncs_benched_characters_with_is_benched_true(): void
    {
        $channelId = '100000000000000001';
        $arthas = Character::factory()->create(['name' => 'Arthas']);
        $thrall = Character::factory()->create(['name' => 'Thrall']);

        $composition = $this->minimalCompositionPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'Arthas', 'slotNumber' => 1, 'groupNumber' => 1]),
            ],
        ]);

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $composition, signUps: [
            ['id' => 1, 'name' => 'Arthas', 'userId' => '111', 'entryTime' => 1700000000, 'className' => 'Warrior'],
            ['id' => 2, 'name' => 'Thrall', 'userId' => '222', 'entryTime' => 1700000001, 'className' => 'Shaman'],
        ]);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $thrallPivot = $event->characters->find($thrall->id)?->pivot;

        $this->assertNotNull($thrallPivot);
        $this->assertTrue((bool) $thrallPivot->is_benched);
        $this->assertNull($thrallPivot->slot_number);
        $this->assertNull($thrallPivot->group_number);
    }

    #[Test]
    public function it_syncs_comp_characters_with_is_benched_false(): void
    {
        $channelId = '100000000000000001';
        $arthas = Character::factory()->create(['name' => 'Arthas']);

        $composition = $this->minimalCompositionPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'Arthas', 'slotNumber' => 1, 'groupNumber' => 1]),
            ],
        ]);

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $composition, signUps: [
            ['id' => 1, 'name' => 'Arthas', 'userId' => '111', 'entryTime' => 1700000000, 'className' => 'Warrior'],
        ]);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $arthasPivot = $event->characters->find($arthas->id)?->pivot;

        $this->assertNotNull($arthasPivot);
        $this->assertFalse((bool) $arthasPivot->is_benched);
    }

    #[Test]
    public function it_excludes_absent_late_and_tentative_signups_from_bench(): void
    {
        $channelId = '100000000000000001';
        $arthas = Character::factory()->create(['name' => 'Arthas']);
        Character::factory()->create(['name' => 'Jaina']);
        Character::factory()->create(['name' => 'Sylvanas']);
        Character::factory()->create(['name' => 'Varian']);

        $composition = $this->minimalCompositionPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'Arthas', 'slotNumber' => 1, 'groupNumber' => 1]),
            ],
        ]);

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $composition, signUps: [
            ['id' => 1, 'name' => 'Arthas', 'userId' => '111', 'entryTime' => 1700000000, 'className' => 'Warrior'],
            ['id' => 2, 'name' => 'Jaina', 'userId' => '222', 'entryTime' => 1700000001, 'className' => 'Absence'],
            ['id' => 3, 'name' => 'Sylvanas', 'userId' => '333', 'entryTime' => 1700000002, 'className' => 'Late'],
            ['id' => 4, 'name' => 'Varian', 'userId' => '444', 'entryTime' => 1700000003, 'className' => 'Tentative'],
        ]);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(1, $event->characters);
        $this->assertTrue($event->characters->contains($arthas));
    }

    // -------------------------------------------------------------------------
    // Raid sync
    // -------------------------------------------------------------------------

    #[Test]
    public function it_syncs_raids_from_valid_json_in_the_event_description(): void
    {
        $channelId = '100000000000000001';
        $raid = Raid::factory()->create(['name' => 'Molten Core']);

        $description = "-# Do not edit below this line...\n".json_encode([['id' => $raid->id, 'name' => $raid->name]]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(1, $event->raids);
        $this->assertTrue($event->raids->contains($raid));
    }

    #[Test]
    public function it_syncs_multiple_raids_from_the_event_description(): void
    {
        $channelId = '100000000000000001';
        $raidOne = Raid::factory()->create(['name' => 'Molten Core']);
        $raidTwo = Raid::factory()->create(['name' => 'Blackwing Lair']);

        $description = "-# Do not edit below this line...\n".json_encode([
            ['id' => $raidOne->id, 'name' => $raidOne->name],
            ['id' => $raidTwo->id, 'name' => $raidTwo->name],
        ]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(2, $event->raids);
        $this->assertTrue($event->raids->contains($raidOne));
        $this->assertTrue($event->raids->contains($raidTwo));
    }

    #[Test]
    public function it_replaces_existing_raid_associations_when_syncing(): void
    {
        $channelId = '100000000000000001';
        $oldRaid = Raid::factory()->create(['name' => 'Old Raid']);
        $newRaid = Raid::factory()->create(['name' => 'New Raid']);

        $existingEvent = Event::factory()->create(['raid_helper_event_id' => '999000000000000001']);
        $existingEvent->raids()->sync([$oldRaid->id]);

        $description = "-# Do not edit below this line...\n".json_encode([['id' => $newRaid->id, 'name' => $newRaid->name]]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(1, $event->raids);
        $this->assertTrue($event->raids->contains($newRaid));
        $this->assertFalse($event->raids->contains($oldRaid));
    }

    #[Test]
    public function it_skips_a_raid_row_where_the_id_does_not_match(): void
    {
        $channelId = '100000000000000001';
        $raid = Raid::factory()->create(['name' => 'Molten Core']);

        $description = "-# Do not edit below this line...\n".json_encode([
            ['id' => 99999, 'name' => $raid->name],
        ]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(0, $event->raids);
    }

    #[Test]
    public function it_skips_a_raid_row_where_the_name_does_not_match(): void
    {
        $channelId = '100000000000000001';
        $raid = Raid::factory()->create(['name' => 'Molten Core']);

        $description = "-# Do not edit below this line...\n".json_encode([
            ['id' => $raid->id, 'name' => 'Wrong Name'],
        ]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(0, $event->raids);
    }

    #[Test]
    public function it_silently_skips_raid_sync_when_the_description_json_is_invalid(): void
    {
        $channelId = '100000000000000001';
        $description = "-# Do not edit below this line...\nnot valid json";
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertNotNull($event);
        $this->assertCount(0, $event->raids);
    }

    // -------------------------------------------------------------------------
    // background_css_class sync
    // -------------------------------------------------------------------------

    #[Test]
    public function it_sets_background_css_class_from_the_first_raid_that_has_one(): void
    {
        $channelId = '100000000000000001';
        $raid = Raid::factory()->withBackground(RaidBackground::KARAZHAN)->create(['name' => 'Molten Core']);

        $description = "-# Do not edit below this line...\n".json_encode([['id' => $raid->id, 'name' => $raid->name]]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertSame(RaidBackground::KARAZHAN, $event->background_css_class);
    }

    #[Test]
    public function it_leaves_background_css_class_null_when_no_raid_has_one(): void
    {
        $channelId = '100000000000000001';
        $raid = Raid::factory()->create(['name' => 'Molten Core', 'background_css_class' => null]);

        $description = "-# Do not edit below this line...\n".json_encode([['id' => $raid->id, 'name' => $raid->name]]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertNull($event->background_css_class);
    }

    #[Test]
    public function it_uses_the_first_raid_with_a_background_css_class_when_multiple_raids_are_present(): void
    {
        $channelId = '100000000000000001';
        $raidOne = Raid::factory()->create(['name' => 'Molten Core', 'background_css_class' => null]);
        $raidTwo = Raid::factory()->withBackground(RaidBackground::KARAZHAN)->create(['name' => 'Blackwing Lair']);
        $raidThree = Raid::factory()->withBackground(RaidBackground::TEMPEST_KEEP)->create(['name' => "Ahn'Qiraj"]);

        $description = "-# Do not edit below this line...\n".json_encode([
            ['id' => $raidOne->id, 'name' => $raidOne->name],
            ['id' => $raidTwo->id, 'name' => $raidTwo->name],
            ['id' => $raidThree->id, 'name' => $raidThree->name],
        ]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertSame(RaidBackground::KARAZHAN, $event->background_css_class);
    }

    #[Test]
    public function it_clears_background_css_class_when_updated_event_has_no_raid_with_one(): void
    {
        $channelId = '100000000000000001';
        $raid = Raid::factory()->create(['name' => 'Molten Core', 'background_css_class' => null]);

        $existingEvent = Event::factory()->withBackground(RaidBackground::KARAZHAN)->create([
            'raid_helper_event_id' => '999000000000000001',
        ]);

        $description = "-# Do not edit below this line...\n".json_encode([['id' => $raid->id, 'name' => $raid->name]]);
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001', 'description' => $description]);
        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $existingEvent->refresh();
        $this->assertNull($existingEvent->background_css_class);
    }

    // -------------------------------------------------------------------------
    // Color sync
    // -------------------------------------------------------------------------

    #[Test]
    public function it_stores_the_color_from_the_raid_helper_api_as_a_binary_color(): void
    {
        $channelId = '100000000000000001';
        $payload = $this->minimalListingEventPayload([
            'id' => '999000000000000001',
            'color' => '34,110,115',
        ]);

        $this->setupSingleEventRun($channelId, $payload, null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertSame('226e73', $event->color);
    }

    // -------------------------------------------------------------------------
    // Cache flush
    // -------------------------------------------------------------------------

    #[Test]
    public function it_flushes_the_events_cache_after_syncing(): void
    {
        $channelId = '100000000000000001';
        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, null);

        Cache::spy();

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->connector);

        Cache::shouldHaveReceived('tags')->with(['events'])->once();
    }

    #[Test]
    public function it_releases_itself_when_discord_is_rate_limited_fetching_channels(): void
    {
        $this->discord->shouldReceive('getGuildChannels')
            ->once()
            ->andThrow(new RateLimitedException('guilds/111222333444555666/channels', 15.0, 'global'));

        Saloon::fake([]);

        $job = new FetchEvents(['100000000000000001']);
        $job->withFakeQueueInteractions();
        $job->handle($this->discord, $this->connector);

        $job->assertReleased(15.0);
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_continues_with_empty_channels_on_other_discord_errors(): void
    {
        $this->discord->shouldReceive('getGuildChannels')
            ->once()
            ->andThrow(new \RuntimeException('Connection timeout'));

        Saloon::fake([]);

        $job = new FetchEvents(['100000000000000001']);
        $job->withFakeQueueInteractions();
        $job->handle($this->discord, $this->connector);

        $job->assertNotReleased();
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_releases_itself_when_raid_helper_is_rate_limited(): void
    {
        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => '100000000000000001'])]));

        Saloon::fake([
            GetEventsRequest::class => MockResponse::make([], 429, ['Retry-After' => '30']),
        ]);

        $job = new FetchEvents(['100000000000000001']);
        $job->withFakeQueueInteractions();
        $job->handle($this->discord, $this->connector);

        $job->assertReleased();
        $this->assertSame(0, Event::query()->count());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Fake a single-channel, single-event run.
     *
     * @param  array<string, mixed>  $eventPayload
     * @param  array<string, mixed>|null  $compositionPayload  Composition body, or null to fake a 404 (no composition).
     * @param  array<int, array<string, mixed>>|null  $signUps
     */
    private function setupSingleEventRun(string $channelId, array $eventPayload, ?array $compositionPayload, ?array $signUps = null): void
    {
        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => $channelId])]));

        if ($signUps !== null) {
            $eventPayload = array_merge($eventPayload, ['signUps' => $signUps]);
        }

        Saloon::fake([
            GetEventsRequest::class => MockResponse::make([
                'eventsTransmitted' => 1,
                'postedEvents' => [$eventPayload],
            ], 200),
            GetCompositionRequest::class => $compositionPayload === null
                ? MockResponse::make(['reason' => 'unknown composition', 'status' => 'failed'], 404)
                : MockResponse::make($compositionPayload, 200),
        ]);
    }

    /**
     * Fake a single empty events page (no events for the channel).
     */
    private function fakeEmptyEventsPage(): void
    {
        Saloon::fake([
            GetEventsRequest::class => MockResponse::make(['eventsTransmitted' => 0, 'postedEvents' => []], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalListingEventPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '999000000000000001',
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
    private function minimalCompositionPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '999000000000000001',
            'title' => 'Weekly Composition',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => true,
            'groupCount' => 0,
            'slotCount' => 0,
            'groups' => [],
            'dividers' => [],
            'classes' => [],
            'slots' => [],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalSlotPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 'slot-1',
            'name' => 'SomeCharacter',
            'groupNumber' => 1,
            'slotNumber' => 1,
            'className' => 'Warrior',
            'classEmoteId' => '0',
            'specName' => 'Arms',
            'specEmoteId' => '0',
            'isConfirmed' => 'unconfirmed',
            'color' => '0,0,0',
        ], $overrides);
    }
}
