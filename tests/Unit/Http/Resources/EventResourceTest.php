<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\RaidBackground;
use App\Http\Resources\EventResource;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\Raid;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use App\Services\RaidHelper\RaidHelper;
use App\Services\RaidHelper\Resources\Event as RaidHelperEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventResourceTest extends TestCase
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

    private function mockRaidHelper(array $signUps = []): void
    {
        $this->mock(RaidHelper::class, function (MockInterface $mock) use ($signUps) {
            $mock->shouldReceive('getEvent')->andReturn(
                RaidHelperEvent::from([
                    'id' => '999000000000000001',
                    'serverId' => '111222333444555666',
                    'leaderId' => '200000000000000001',
                    'leaderName' => 'Raid Leader',
                    'channelId' => '100000000000000001',
                    'channelName' => 'raid-signups',
                    'channelType' => 'GUILD_TEXT',
                    'templateId' => 'wowclassic',
                    'templateEmoteId' => '0',
                    'title' => 'Weekly Raid',
                    'description' => '',
                    'startTime' => 1700000000,
                    'endTime' => 1700007200,
                    'closingTime' => 1699999800,
                    'date' => '2023-11-14',
                    'time' => '20:00',
                    'advancedSettings' => [],
                    'classes' => [],
                    'roles' => [],
                    'signUps' => $signUps,
                    'lastUpdated' => 1699999000,
                    'color' => '0,0,0',
                ])
            )->byDefault();
        });
    }

    private function makeResource(Event $event): array
    {
        $event->load('raids.bosses.media', 'assignments.group', 'characters.rank');

        return (new EventResource($event))->toArray(new Request);
    }

    #[Test]
    public function it_returns_all_expected_top_level_keys(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('start_time', $array);
        $this->assertArrayHasKey('end_time', $array);
        $this->assertArrayHasKey('duration', $array);
        $this->assertArrayHasKey('channel', $array);
        $this->assertArrayHasKey('background', $array);
        $this->assertArrayHasKey('assignments', $array);
        $this->assertArrayHasKey('composition', $array);
        $this->assertArrayHasKey('raids', $array);
    }

    #[Test]
    public function it_returns_null_background_when_no_raids_attached(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertNull($array['background']);
    }

    #[Test]
    public function it_returns_background_string_based_on_first_raid(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();

        $raid = Raid::factory()->create(['id' => 1]);
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        $array = $this->makeResource($event);

        $this->assertSame(RaidBackground::KARAZHAN->value, $array['background']);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame($event->id, $array['id']);
        $this->assertSame($event->title, $array['title']);
        $this->assertSame($event->start_time->toIso8601String(), $array['start_time']);
        $this->assertSame($event->end_time->toIso8601String(), $array['end_time']);
    }

    #[Test]
    public function it_returns_duration_as_seconds_between_start_and_end(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame($event->start_time->diffInSeconds($event->end_time), $array['duration']);
    }

    #[Test]
    public function it_returns_channel_as_an_array(): void
    {
        $this->mockChannel(id: '999888777', name: 'raid-chat', position: 3);
        $this->mockRaidHelper();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

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
        $this->mockRaidHelper();

        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayNotHasKey('channel', $array);
    }

    #[Test]
    public function it_returns_null_start_time_when_event_has_no_start_time(): void
    {
        $this->mockChannel();
        $event = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['start_time']);
    }

    #[Test]
    public function it_returns_null_end_time_when_event_has_no_end_time(): void
    {
        $this->mockChannel();
        $event = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['end_time']);
    }

    #[Test]
    public function it_returns_null_duration_when_event_has_no_start_or_end_time(): void
    {
        $this->mockChannel();
        $event = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['duration']);
    }

    #[Test]
    public function it_returns_null_channel_when_channel_id_is_null(): void
    {
        $event = Event::factory()->template()->create(['channel_id' => null, 'start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['channel']);
    }

    // ============ Assignments ============

    #[Test]
    public function it_returns_event_level_assignments_excluding_boss_scoped_ones(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();

        $raid = Raid::factory()->create();
        $boss = Boss::factory()->for($raid)->create();
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        EventAssignment::factory()->for($event)->create(['boss_id' => null]);
        EventAssignment::factory()->for($event)->create(['boss_id' => $boss->id]);

        $array = $this->makeResource($event);

        $this->assertArrayHasKey('groups', $array['assignments']);
        $this->assertArrayHasKey('ungrouped', $array['assignments']);
        $this->assertCount(1, $array['assignments']['ungrouped']);
    }

    #[Test]
    public function it_returns_boss_level_assignments_inside_the_boss(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();

        $raid = Raid::factory()->create();
        $boss = Boss::factory()->for($raid)->create();
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        EventAssignment::factory()->for($event)->create(['boss_id' => $boss->id]);

        $array = $this->makeResource($event);

        $bossAssignments = $array['raids'][0]['bosses'][0]['assignments'];
        $this->assertArrayHasKey('groups', $bossAssignments);
        $this->assertArrayHasKey('ungrouped', $bossAssignments);
        $this->assertCount(1, $bossAssignments['ungrouped']);
    }

    // ============ Raids and bosses ============

    #[Test]
    public function it_returns_raids_with_expected_shape(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();

        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        $array = $this->makeResource($event);

        $this->assertCount(1, $array['raids']);
        $this->assertSame('Karazhan', $array['raids'][0]['name']);
        $this->assertSame($raid->slug, $array['raids'][0]['slug']);
        $this->assertSame($raid->max_players, $array['raids'][0]['max_players']);
        $this->assertArrayHasKey('bosses', $array['raids'][0]);
    }

    #[Test]
    public function it_returns_bosses_with_expected_shape(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();

        $raid = Raid::factory()->create();
        $boss = Boss::factory()->for($raid)->create(['name' => 'Attumen', 'notes' => 'Kill adds first']);
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        $array = $this->makeResource($event);

        $bossData = $array['raids'][0]['bosses'][0];
        $this->assertSame($boss->id, $bossData['id']);
        $this->assertSame('Attumen', $bossData['name']);
        $this->assertSame($boss->slug, $bossData['slug']);
        $this->assertSame($boss->encounter_order, $bossData['encounter_order']);
        $this->assertSame('Kill adds first', $bossData['notes']);
        $this->assertIsArray($bossData['images']);
        $this->assertArrayHasKey('groups', $bossData['assignments']);
        $this->assertArrayHasKey('ungrouped', $bossData['assignments']);
    }

    // ============ Composition — groups ============

    #[Test]
    public function it_returns_composition_with_groups_and_bench_keys(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayHasKey('groups', $array['composition']);
        $this->assertArrayHasKey('bench', $array['composition']);
    }

    #[Test]
    public function it_returns_groups_with_correct_character_shape(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();

        $raid = Raid::factory()->create(['max_players' => 10]);
        $event = Event::factory()->hasAttached($raid, [], 'raids')->create();
        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
            'is_leader' => false,
            'is_loot_councillor' => false,
            'is_loot_master' => false,
        ]);

        $array = $this->makeResource($event);

        $this->assertCount(1, $array['composition']['groups']);
        $group = $array['composition']['groups'][0];
        $this->assertSame(1, $group['group_number']);
        $this->assertArrayHasKey('is_team', $group);

        $char = $group['characters'][0];
        $this->assertSame($character->id, $char['id']);
        $this->assertSame($character->name, $char['name']);
        $this->assertArrayHasKey('playable_class', $char);
        $this->assertArrayHasKey('rank', $char);
        $this->assertArrayHasKey('name', $char['rank']);
        $this->assertArrayHasKey('position', $char['rank']);
        $this->assertArrayNotHasKey('id', $char['rank']);
        $this->assertArrayNotHasKey('count_attendance', $char['rank']);
        $this->assertSame(1, $char['slot_number']);
        $this->assertTrue($char['is_confirmed']);
        $this->assertArrayHasKey('is_leader', $char);
        $this->assertArrayHasKey('is_loot_councillor', $char);
        $this->assertArrayHasKey('is_loot_master', $char);
    }

    #[Test]
    public function it_returns_empty_groups_when_no_characters(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame([], $array['composition']['groups']);
    }

    // ============ Composition — bench ============

    #[Test]
    public function it_returns_empty_bench_when_no_characters_in_comp(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame([], $array['composition']['bench']);
    }

    #[Test]
    public function it_returns_benched_characters_not_in_comp(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();

        $inComp = Character::factory()->withRank()->create(['name' => 'Jaina']);
        $benchedChar = Character::factory()->withRank()->create(['name' => 'Thrall']);

        $event = Event::factory()->create();
        $event->characters()->attach($inComp->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'is_confirmed' => true,
            'is_benched' => false,
        ]);
        $event->characters()->attach($benchedChar->id, [
            'slot_number' => null,
            'group_number' => null,
            'is_confirmed' => false,
            'is_benched' => true,
        ]);

        $array = $this->makeResource($event);

        $this->assertCount(1, $array['composition']['bench']);
        $this->assertSame($benchedChar->name, $array['composition']['bench'][0]['name']);
    }

    #[Test]
    public function it_returns_bench_characters_with_expected_shape(): void
    {
        $this->mockChannel();
        $this->mockRaidHelper();

        $benchedChar = Character::factory()->withRank()->create(['name' => 'Thrall']);

        $event = Event::factory()->create();
        $event->characters()->attach($benchedChar->id, [
            'slot_number' => null,
            'group_number' => null,
            'is_confirmed' => false,
            'is_benched' => true,
        ]);

        $array = $this->makeResource($event);

        $bench = $array['composition']['bench'][0];
        $this->assertArrayHasKey('id', $bench);
        $this->assertArrayHasKey('name', $bench);
        $this->assertArrayHasKey('playable_class', $bench);
        $this->assertArrayHasKey('rank', $bench);
        $this->assertArrayHasKey('name', $bench['rank']);
        $this->assertArrayHasKey('position', $bench['rank']);
        $this->assertArrayNotHasKey('slot_number', $bench);
        $this->assertArrayNotHasKey('is_confirmed', $bench);
    }
}
