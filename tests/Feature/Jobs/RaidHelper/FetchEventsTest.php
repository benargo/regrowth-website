<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Jobs\RaidHelper\FetchEvents;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use App\Services\RaidHelper\RaidHelper;
use App\Services\RaidHelper\Resources\Comp;
use App\Services\RaidHelper\Resources\Event as RaidHelperEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchEventsTest extends TestCase
{
    use RefreshDatabase;

    private Discord&MockInterface $discord;

    private RaidHelper&MockInterface $raidHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discord = Mockery::mock(Discord::class);
        $this->raidHelper = Mockery::mock(RaidHelper::class);

        $this->app->instance(Discord::class, $this->discord);
        $this->app->instance(RaidHelper::class, $this->raidHelper);
    }

    // -------------------------------------------------------------------------
    // Channel validation
    // -------------------------------------------------------------------------

    #[Test]
    public function it_only_processes_channel_ids_that_belong_to_the_server(): void
    {
        $serverId = '111222333444555666';
        $validChannelId = '100000000000000001';
        $invalidChannelId = '999999999999999999';

        $this->raidHelper->shouldReceive('getServerId')->andReturn($serverId);
        $this->discord->shouldReceive('getGuildChannels')
            ->with($serverId)
            ->andReturn(Collection::make([Channel::from(['id' => $validChannelId])]));

        $this->raidHelper->shouldReceive('getEvents')
            ->once()
            ->andReturn($this->singlePagePaginator([]));

        $job = new FetchEvents([$validChannelId, $invalidChannelId]);
        $job->handle($this->discord, $this->raidHelper);
    }

    #[Test]
    public function it_skips_all_channels_when_none_belong_to_the_server(): void
    {
        $this->raidHelper->shouldReceive('getServerId')->andReturn('111222333444555666');
        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([]));

        $this->raidHelper->shouldNotReceive('getEvents');

        $job = new FetchEvents(['999999999999999999']);
        $job->handle($this->discord, $this->raidHelper);
    }

    // -------------------------------------------------------------------------
    // Event fetching & pagination
    // -------------------------------------------------------------------------

    #[Test]
    public function it_fetches_events_for_each_valid_channel(): void
    {
        $channelOneId = '100000000000000001';
        $channelTwoId = '100000000000000002';

        $this->raidHelper->shouldReceive('getServerId')->andReturn('111222333444555666');
        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([
                Channel::from(['id' => $channelOneId]),
                Channel::from(['id' => $channelTwoId]),
            ]));

        $this->raidHelper->shouldReceive('getEvents')
            ->twice()
            ->andReturn($this->singlePagePaginator([]));

        $job = new FetchEvents([$channelOneId, $channelTwoId]);
        $job->handle($this->discord, $this->raidHelper);
    }

    #[Test]
    public function it_passes_the_time_filters_to_get_events(): void
    {
        $channelId = '100000000000000001';
        $start = Carbon::parse('2024-01-01 06:00:00', 'UTC');
        $end = Carbon::parse('2024-01-08 05:59:59', 'UTC');

        $capturedChannelId = null;
        $capturedStart = null;
        $capturedEnd = null;

        $this->raidHelper->shouldReceive('getServerId')->andReturn('111222333444555666');
        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => $channelId])]));

        $this->raidHelper->shouldReceive('getEvents')
            ->withArgs(function ($page, $includeSignUps, $channelIdArg, $startFilter, $endFilter) use (&$capturedChannelId, &$capturedStart, &$capturedEnd) {
                $capturedChannelId = $channelIdArg;
                $capturedStart = $startFilter;
                $capturedEnd = $endFilter;

                return true;
            })
            ->andReturn($this->singlePagePaginator([]));

        $job = new FetchEvents([$channelId], $start, $end);
        $job->handle($this->discord, $this->raidHelper);

        $this->assertSame($channelId, $capturedChannelId);
        $this->assertTrue($capturedStart->eq($start));
        $this->assertTrue($capturedEnd->eq($end));
    }

    #[Test]
    public function it_collects_events_across_multiple_pages(): void
    {
        $channelId = '100000000000000001';
        $payloadOne = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $payloadTwo = $this->minimalListingEventPayload(['id' => '999000000000000002']);

        $pageOne = (new Paginator(
            items: RaidHelperEvent::collect([$payloadOne]),
            perPage: 1,
            currentPage: 1,
            options: [],
        ))->hasMorePagesWhen(true);

        $pageTwo = (new Paginator(
            items: RaidHelperEvent::collect([$payloadTwo]),
            perPage: 1,
            currentPage: 2,
            options: [],
        ))->hasMorePagesWhen(false);

        $this->raidHelper->shouldReceive('getServerId')->andReturn('111222333444555666');
        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => $channelId])]));

        $this->raidHelper->shouldReceive('getEvents')
            ->once()
            ->withArgs(fn ($page, $includeSignUps, $channelIdArg) => $channelIdArg === $channelId && $page !== 2)
            ->andReturn($pageOne);

        $this->raidHelper->shouldReceive('getEvents')
            ->once()
            ->withArgs(fn ($page, $includeSignUps, $channelIdArg) => $channelIdArg === $channelId && $page === 2)
            ->andReturn($pageTwo);

        $this->raidHelper->shouldReceive('getComp')->with('999000000000000001')->andReturn(null);
        $this->raidHelper->shouldReceive('getComp')->with('999000000000000002')->andReturn(null);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->raidHelper);

        $this->assertDatabaseHas('events', ['raid_helper_event_id' => '999000000000000001']);
        $this->assertDatabaseHas('events', ['raid_helper_event_id' => '999000000000000002']);
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
        $job->handle($this->discord, $this->raidHelper);

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
        $job->handle($this->discord, $this->raidHelper);

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
        $job->handle($this->discord, $this->raidHelper);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $appTimezone = config('app.timezone');

        $this->assertSame($appTimezone, $event->start_time->timezoneName);
        $this->assertSame($appTimezone, $event->end_time->timezoneName);
        $this->assertSame(1700000000, $event->start_time->unix());
        $this->assertSame(1700007200, $event->end_time->unix());
    }

    // -------------------------------------------------------------------------
    // Comp sync
    // -------------------------------------------------------------------------

    #[Test]
    public function it_syncs_characters_from_the_comp_slots_to_the_event(): void
    {
        $channelId = '100000000000000001';
        $character = Character::factory()->create(['name' => 'Arthas']);

        $comp = Comp::from($this->minimalCompPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'Arthas', 'slotNumber' => 1, 'groupNumber' => 1, 'isConfirmed' => 'confirmed']),
            ],
        ]));

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $comp);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->raidHelper);

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

        $comp = Comp::from($this->minimalCompPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'UnknownCharacter']),
            ],
        ]));

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $comp);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->raidHelper);

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
        $job->handle($this->discord, $this->raidHelper);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertNotNull($event);
        $this->assertCount(0, $event->characters);
    }

    #[Test]
    public function it_syncs_multiple_characters_from_comp_slots(): void
    {
        $channelId = '100000000000000001';
        $arthas = Character::factory()->create(['name' => 'Arthas']);
        $sylvanas = Character::factory()->create(['name' => 'Sylvanas']);

        $comp = Comp::from($this->minimalCompPayload([
            'slots' => [
                $this->minimalSlotPayload(['name' => 'Arthas', 'slotNumber' => 1, 'groupNumber' => 1]),
                $this->minimalSlotPayload(['id' => 'slot-2', 'name' => 'Sylvanas', 'slotNumber' => 2, 'groupNumber' => 1]),
            ],
        ]));

        $payload = $this->minimalListingEventPayload(['id' => '999000000000000001']);
        $this->setupSingleEventRun($channelId, $payload, $comp);

        $job = new FetchEvents([$channelId]);
        $job->handle($this->discord, $this->raidHelper);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertCount(2, $event->characters);
        $this->assertTrue($event->characters->contains($arthas));
        $this->assertTrue($event->characters->contains($sylvanas));
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
        $job->handle($this->discord, $this->raidHelper);

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
        $job->handle($this->discord, $this->raidHelper);

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
        $job->handle($this->discord, $this->raidHelper);

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
        $job->handle($this->discord, $this->raidHelper);

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
        $job->handle($this->discord, $this->raidHelper);

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
        $job->handle($this->discord, $this->raidHelper);

        $event = Event::where('raid_helper_event_id', '999000000000000001')->first();
        $this->assertNotNull($event);
        $this->assertCount(0, $event->raids);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Wire up mocks for a single-event, single-channel run.
     *
     * @param  array<string, mixed>  $eventPayload
     */
    private function setupSingleEventRun(string $channelId, array $eventPayload, ?Comp $comp): void
    {
        $this->raidHelper->shouldReceive('getServerId')->andReturn('111222333444555666');
        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => $channelId])]));

        $this->raidHelper->shouldReceive('getEvents')
            ->once()
            ->andReturn($this->singlePagePaginator([$eventPayload]));

        $this->raidHelper->shouldReceive('getComp')
            ->with($eventPayload['id'])
            ->andReturn($comp);
    }

    /**
     * Build a single-page LengthAwarePaginator from an array of raw event payloads.
     *
     * @param  array<int, array<string, mixed>>  $payloads
     */
    private function singlePagePaginator(array $payloads): Paginator
    {
        return (new Paginator(
            items: RaidHelperEvent::collect($payloads),
            perPage: max(count($payloads), 1),
            currentPage: 1,
            options: [],
        ))->hasMorePagesWhen(false);
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
    private function minimalCompPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '999000000000000001',
            'title' => 'Weekly Comp',
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
