<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\RaidBackground;
use App\Enums\SignupStatus;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Http\Resources\EventResource;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
#[Group('raiding')]
class EventResourceTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    public function it_returns_exactly_the_expected_top_level_keys(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame(
            ['id', 'title', 'start_time', 'end_time', 'duration', 'color', 'background', 'assignments', 'composition', 'raids', 'channel'],
            array_keys($array),
        );
    }

    // ==================== id, title, start and end times ====================

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create([
            'title' => 'Karazhan',
            'start_time' => '2026-09-18 19:00:00',
            'end_time' => '2026-09-18 22:00:00',
        ]);

        $array = $this->makeResource($event);

        $this->assertSame($event->id, $array['id']);
        $this->assertSame('Karazhan', $array['title']);
        $this->assertStringStartsWith('2026-09-18T19:00:00', $array['start_time']);
        $this->assertStringStartsWith('2026-09-18T22:00:00', $array['end_time']);
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

    // ==================== duration ====================

    #[Test]
    public function it_returns_duration_as_seconds_between_start_and_end(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create(['start_time' => '2026-09-18 19:00:00', 'end_time' => '2026-09-18 22:00:00']);

        $array = $this->makeResource($event);

        $this->assertSame(10800.0, $array['duration']);
    }

    #[Test]
    public function it_returns_null_duration_when_event_has_no_start_or_end_time(): void
    {
        $this->mockChannel();
        $event = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['duration']);
    }

    // ==================== color ====================

    #[Test]
    public function it_returns_null_color_when_event_has_no_color(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create(['color' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['color']);
    }

    #[Test]
    public function it_returns_color_as_hex_string(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create(['color' => '34,110,115']);

        $array = $this->makeResource($event);

        $this->assertSame('226e73', $array['color']);
    }

    // ==================== background ====================

    #[Test]
    public function it_returns_null_background_when_no_raids_attached(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertNull($array['background']);
    }

    #[Test]
    public function it_returns_background_string_based_on_event_background_css_class(): void
    {
        $this->mockChannel();

        $event = Event::factory()->withBackground(RaidBackground::Karazhan)->create();

        $array = $this->makeResource($event);

        $this->assertSame(RaidBackground::Karazhan->value, $array['background']);
    }

    // ==================== assignments ====================

    #[Test]
    public function it_returns_event_level_assignments_excluding_boss_scoped_ones(): void
    {
        $this->mockChannel();

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

    // ==================== composition — groups ====================

    #[Test]
    public function it_returns_composition_with_groups_and_bench_keys(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayHasKey('groups', $array['composition']);
        $this->assertArrayHasKey('bench', $array['composition']);
    }

    #[Test]
    public function it_returns_groups_with_correct_character_shape(): void
    {
        $this->mockChannel();

        $raid = Raid::factory()->create(['max_players' => 10]);
        $event = Event::factory()->hasAttached($raid, [], 'raids')->create();
        $character = Character::factory()->withRank()->create();
        $event->characters()->attach($character->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'signup_status' => SignupStatus::Confirmed->value,
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
        $this->assertArrayHasKey('sort_order', $char['rank']);
        $this->assertArrayNotHasKey('id', $char['rank']);
        $this->assertArrayNotHasKey('count_attendance', $char['rank']);
        $this->assertSame(1, $char['slot_number']);
        $this->assertSame(SignupStatus::Confirmed, $char['signup_status']);
        $this->assertArrayNotHasKey('is_confirmed', $char);
        $this->assertArrayHasKey('is_leader', $char);
        $this->assertArrayHasKey('is_loot_councillor', $char);
        $this->assertArrayHasKey('is_loot_master', $char);
    }

    #[Test]
    public function it_returns_empty_groups_when_no_characters(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame([], $array['composition']['groups']);
    }

    #[Test]
    public function it_excludes_benched_characters_from_groups(): void
    {
        $this->mockChannel();

        $event = Event::factory()->hasAttached(Raid::factory()->state(['max_players' => 10]), [], 'raids')->create();
        $inComp = $this->attachToComposition($event, groupNumber: 1, slotNumber: 1);
        $this->attachToComposition($event, groupNumber: null, slotNumber: null, isBenched: true);

        $array = $this->makeResource($event);

        $this->assertSame([$inComp->id], array_column($array['composition']['groups'][0]['characters'], 'id'));
    }

    #[Test]
    public function it_orders_groups_by_group_number_and_characters_by_slot_number(): void
    {
        $this->mockChannel();

        $event = Event::factory()->hasAttached(Raid::factory()->state(['max_players' => 10]), [], 'raids')->create();
        $groupTwoSlotOne = $this->attachToComposition($event, groupNumber: 2, slotNumber: 1);
        $groupOneSlotTwo = $this->attachToComposition($event, groupNumber: 1, slotNumber: 2);
        $groupOneSlotOne = $this->attachToComposition($event, groupNumber: 1, slotNumber: 1);

        $groups = $this->makeResource($event)['composition']['groups'];

        $this->assertSame([1, 2], array_column($groups, 'group_number'));
        $this->assertSame([$groupOneSlotOne->id, $groupOneSlotTwo->id], array_column($groups[0]['characters'], 'id'));
        $this->assertSame([$groupTwoSlotOne->id], array_column($groups[1]['characters'], 'id'));
    }

    #[Test]
    public function it_drops_party_groups_beyond_the_raid_size(): void
    {
        $this->mockChannel();

        $event = Event::factory()->hasAttached(Raid::factory()->state(['max_players' => 10]), [], 'raids')->create();
        $this->attachToComposition($event, groupNumber: 1, slotNumber: 5);
        $this->attachToComposition($event, groupNumber: 2, slotNumber: 1);
        $this->attachToComposition($event, groupNumber: 3, slotNumber: 1);

        $groups = $this->makeResource($event)['composition']['groups'];

        $this->assertSame([1, 2], array_column($groups, 'group_number'));
        $this->assertSame([false, false], array_column($groups, 'is_team'));
    }

    #[Test]
    public function it_marks_groups_as_teams_and_keeps_all_of_them_when_a_slot_exceeds_five(): void
    {
        $this->mockChannel();

        $event = Event::factory()->hasAttached(Raid::factory()->state(['max_players' => 10]), [], 'raids')->create();
        $this->attachToComposition($event, groupNumber: 1, slotNumber: 6);
        $this->attachToComposition($event, groupNumber: 2, slotNumber: 1);
        $this->attachToComposition($event, groupNumber: 3, slotNumber: 1);

        $groups = $this->makeResource($event)['composition']['groups'];

        $this->assertSame([1, 2, 3], array_column($groups, 'group_number'));
        $this->assertSame([true, true, true], array_column($groups, 'is_team'));
    }

    // ==================== composition — bench ====================

    #[Test]
    public function it_returns_empty_bench_when_no_characters_in_comp(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame([], $array['composition']['bench']);
    }

    #[Test]
    public function it_returns_benched_characters_not_in_comp(): void
    {
        $this->mockChannel();

        $inComp = Character::factory()->withRank()->create(['name' => 'Jaina']);
        $benchedChar = Character::factory()->withRank()->create(['name' => 'Thrall']);

        $event = Event::factory()->create();
        $event->characters()->attach($inComp->id, [
            'slot_number' => 1,
            'group_number' => 1,
            'signup_status' => SignupStatus::Confirmed->value,
            'is_benched' => false,
        ]);
        $event->characters()->attach($benchedChar->id, [
            'slot_number' => null,
            'group_number' => null,
            'signup_status' => SignupStatus::Unconfirmed->value,
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

        $benchedChar = Character::factory()->withRank()->create(['name' => 'Thrall']);

        $event = Event::factory()->create();
        $event->characters()->attach($benchedChar->id, [
            'slot_number' => null,
            'group_number' => null,
            'signup_status' => SignupStatus::Unconfirmed->value,
            'is_benched' => true,
        ]);

        $array = $this->makeResource($event);

        $bench = $array['composition']['bench'][0];
        $this->assertArrayHasKey('id', $bench);
        $this->assertArrayHasKey('name', $bench);
        $this->assertArrayHasKey('playable_class', $bench);
        $this->assertArrayHasKey('rank', $bench);
        $this->assertArrayHasKey('name', $bench['rank']);
        $this->assertArrayHasKey('sort_order', $bench['rank']);
        $this->assertArrayNotHasKey('slot_number', $bench);
        $this->assertArrayNotHasKey('is_confirmed', $bench);
    }

    // ==================== raids and bosses ====================

    #[Test]
    public function it_returns_raids_with_expected_shape(): void
    {
        $this->mockChannel();

        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id, ['sort_order' => 1]);

        $array = $this->makeResource($event);

        $this->assertCount(1, $array['raids']);
        $this->assertSame('Karazhan', $array['raids'][0]['name']);
        $this->assertSame($raid->slug, $array['raids'][0]['slug']);
        $this->assertSame($raid->max_players, $array['raids'][0]['max_players']);
        $this->assertSame(1, $array['raids'][0]['sort_order']);
        $this->assertArrayHasKey('bosses', $array['raids'][0]);
    }

    #[Test]
    public function it_orders_raids_by_pivot_sort_order(): void
    {
        $this->mockChannel();

        $third = Raid::factory()->create(['name' => 'Karazhan']);
        $second = Raid::factory()->create(['name' => 'Magtheridon']);
        $first = Raid::factory()->create(['name' => 'Gruul']);

        $event = Event::factory()->create();

        $event->raids()->attach($first->id, ['sort_order' => 1]);
        $event->raids()->attach($second->id, ['sort_order' => 2]);
        $event->raids()->attach($third->id, ['sort_order' => 3]);

        $array = $this->makeResource($event);

        $this->assertSame(
            ['Gruul', 'Magtheridon', 'Karazhan'],
            array_column($array['raids'], 'name'),
        );
    }

    #[Test]
    public function it_returns_bosses_with_expected_shape(): void
    {
        $this->mockChannel();

        $raid = Raid::factory()->create();
        $boss = Boss::factory()->for($raid)->create(['name' => 'Attumen', 'notes' => 'Kill adds first']);
        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        $array = $this->makeResource($event);

        $bossData = $array['raids'][0]['bosses'][0];
        $this->assertSame($boss->id, $bossData['id']);
        $this->assertSame('Attumen', $bossData['name']);
        $this->assertSame($boss->slug, $bossData['slug']);
        $this->assertSame($boss->sort_order, $bossData['sort_order']);
        $this->assertSame('Kill adds first', $bossData['notes']);
        $this->assertIsArray($bossData['images']);
        $this->assertFalse($bossData['is_visible']);
        $this->assertArrayHasKey('groups', $bossData['assignments']);
        $this->assertArrayHasKey('ungrouped', $bossData['assignments']);
    }

    // ==================== raids.bosses — is_visible ====================

    #[Test]
    #[Group('contract')]
    public function raids_bosses_expose_is_visible_from_the_event_pivot(): void
    {
        $this->mockChannel();
        $raid = Raid::factory()->create();
        $a = Boss::factory()->for($raid)->order(1)->create();
        $b = Boss::factory()->for($raid)->order(2)->create();

        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        // Only one of the raid's bosses is selected for the event.
        $event->bosses()->attach($b->id);

        $array = $this->makeResource($event);

        // Every one of the raid's bosses is still present, in the raid's own order.
        $this->assertSame([$a->id, $b->id], array_column($array['raids'][0]['bosses'], 'id'));

        // is_visible reflects pivot membership.
        $this->assertSame([false, true], array_column($array['raids'][0]['bosses'], 'is_visible'));
    }

    #[Test]
    public function raids_bosses_are_all_hidden_when_none_are_attached(): void
    {
        $this->mockChannel();
        $raid = Raid::factory()->create();
        Boss::factory()->for($raid)->order(1)->create();

        $event = Event::factory()->create();
        $event->raids()->attach($raid->id);

        $array = $this->makeResource($event);

        $this->assertSame([false], array_column($array['raids'][0]['bosses'], 'is_visible'));
    }

    // ==================== users ====================

    #[Test]
    public function it_omits_users_when_not_loaded(): void
    {
        $this->mockChannel();
        $event = Event::factory()->withUsers()->create();

        $array = (new EventResource($event->fresh()))->resolve(new Request);

        $this->assertArrayNotHasKey('users', $array);
    }

    #[Test]
    public function it_returns_users_when_loaded(): void
    {
        $this->mockChannel();
        $users = User::factory()->count(2)->create();
        $event = Event::factory()->withUsers($users->all())->create();

        $request = new Request;
        $array = (new EventResource($event->fresh()->load('users')))->resolve($request);

        $this->assertArrayHasKey('users', $array);
        $this->assertEqualsCanonicalizing($users->pluck('id')->all(), array_column($array['users']->resolve($request), 'id'));
    }

    #[Test]
    public function the_member_and_visitor_scopes_omit_users_even_when_loaded(): void
    {
        $this->mockChannel();
        $event = Event::factory()->withUsers()->create()->fresh()->load('users', 'raids');

        $this->assertArrayNotHasKey('users', $this->makeMemberResource($event));
        $this->assertArrayNotHasKey('users', $this->makeVisitorResource($event));
    }

    // ==================== channel ====================

    #[Test]
    public function it_returns_channel_as_an_array(): void
    {
        $this->mockChannel(id: '999888777', name: 'raid-chat', position: 3);
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertIsArray($array['channel']);
        $this->assertSame('999888777', $array['channel']['id']);
        $this->assertSame('raid-chat', $array['channel']['name']);
        $this->assertSame(3, $array['channel']['position']);
    }

    #[Test]
    public function it_returns_null_channel_when_channel_id_is_null(): void
    {
        $event = Event::factory()->template()->create(['channel_id' => null, 'start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['channel']);
    }

    #[Test]
    public function it_does_not_call_discord_when_channel_id_is_null(): void
    {
        $this->fakeDiscord([]);

        $event = Event::factory()->template()->create(['channel_id' => null, 'start_time' => null, 'end_time' => null]);

        $this->makeResource($event);

        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_omits_channel_when_discord_api_fails(): void
    {
        Exceptions::fake();
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);

        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayNotHasKey('channel', $array);
        Exceptions::assertReported(fn (ApiException $exception): bool => $exception->getDiscordCode() === 50001);

        Saloon::mockClient()->assertSentCount(1, GetChannelRequest::class);
    }

    // ==================== forMembers ====================

    #[Test]
    public function the_member_scope_returns_exactly_the_expected_keys(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = $this->makeMemberResource($event);

        $this->assertSame(['id', 'title', 'start_time', 'end_time', 'duration', 'channel'], array_keys($array));
    }

    #[Test]
    public function the_member_scope_returns_correct_scalar_fields(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create([
            'title' => 'Karazhan',
            'start_time' => '2026-09-18 19:00:00',
            'end_time' => '2026-09-18 22:00:00',
        ]);

        $array = $this->makeMemberResource($event);

        $this->assertSame($event->id, $array['id']);
        $this->assertSame('Karazhan', $array['title']);
        $this->assertStringStartsWith('2026-09-18T19:00:00', $array['start_time']);
        $this->assertStringStartsWith('2026-09-18T22:00:00', $array['end_time']);
    }

    #[Test]
    public function the_member_scope_returns_duration_as_seconds_between_start_and_end(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create(['start_time' => '2026-09-18 19:00:00', 'end_time' => '2026-09-18 22:00:00']);

        $array = $this->makeMemberResource($event);

        $this->assertSame(10800.0, $array['duration']);
    }

    #[Test]
    public function the_member_scope_returns_channel_as_an_array_with_id_name_and_position(): void
    {
        $this->mockChannel(id: '999888777', name: 'raid-chat', position: 3);
        $event = Event::factory()->create();

        $array = $this->makeMemberResource($event);

        $this->assertIsArray($array['channel']);
        $this->assertSame('999888777', $array['channel']['id']);
        $this->assertSame('raid-chat', $array['channel']['name']);
        $this->assertSame(3, $array['channel']['position']);
    }

    #[Test]
    public function the_member_scope_omits_channel_when_discord_api_fails(): void
    {
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);

        $event = Event::factory()->create();

        $this->assertArrayNotHasKey('channel', $this->makeMemberResource($event));

        Saloon::mockClient()->assertSentCount(1, GetChannelRequest::class);
    }

    #[Test]
    public function the_member_scope_does_not_include_composition_raids_or_assignments(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();

        $array = $this->makeMemberResource($event);

        $this->assertArrayNotHasKey('composition', $array);
        $this->assertArrayNotHasKey('raids', $array);
        $this->assertArrayNotHasKey('assignments', $array);
    }

    // ==================== forVisitors ====================

    #[Test]
    public function the_visitor_scope_exposes_only_public_safe_fields(): void
    {
        $event = Event::factory()->live()->create([
            'title' => 'Karazhan',
            'start_time' => '2026-09-18 19:00:00',
            'end_time' => '2026-09-18 22:00:00',
        ]);

        $data = $this->makeVisitorResource($event);

        $this->assertSame($event->id, $data['id']);
        $this->assertSame('Karazhan', $data['title']);
        $this->assertStringStartsWith('2026-09-18T19:00:00', $data['start_time']);
        $this->assertStringStartsWith('2026-09-18T22:00:00', $data['end_time']);
    }

    #[Test]
    public function the_visitor_scope_lists_raid_names_as_strings(): void
    {
        $event = Event::factory()->live()->create();
        $event->raids()->attach(Raid::factory()->create(['name' => 'Black Temple']), ['sort_order' => 1]);

        $this->assertSame(['Black Temple'], $this->makeVisitorResource($event)['raids']);
    }

    #[Test]
    public function the_visitor_scope_handles_an_event_with_no_raids(): void
    {
        $this->assertSame([], $this->makeVisitorResource(Event::factory()->live()->create())['raids']);
    }

    #[Test]
    public function the_visitor_scope_never_exposes_internal_or_roster_data(): void
    {
        $data = $this->makeVisitorResource(Event::factory()->live()->create());

        $this->assertArrayNotHasKey('channel', $data);
        $this->assertArrayNotHasKey('channel_id', $data);
        $this->assertArrayNotHasKey('composition', $data);
        $this->assertArrayNotHasKey('assignments', $data);
        $this->assertArrayNotHasKey('characters', $data);
        $this->assertArrayNotHasKey('duration', $data);
    }

    #[Test]
    public function the_visitor_scope_never_calls_the_discord_api(): void
    {
        $this->fakeDiscord([]);

        $this->makeVisitorResource(Event::factory()->live()->create());

        Saloon::assertNothingSent();
    }

    #[Test]
    public function the_default_scope_still_returns_raids_as_full_objects(): void
    {
        $this->mockChannel();
        $event = Event::factory()->create();
        $event->raids()->attach(Raid::factory()->create(['name' => 'Black Temple']), ['sort_order' => 1]);

        $raids = $this->makeResource($event)['raids'];

        $this->assertIsArray($raids[0]);
        $this->assertArrayHasKey('bosses', $raids[0]);
        $this->assertSame('Black Temple', $raids[0]['name']);
    }

    // ==================== collections ====================

    #[Test]
    public function collection_for_members_wraps_each_event_in_the_member_scope(): void
    {
        $this->mockChannel();
        $events = Event::factory()->count(2)->create();

        $resources = EventResource::collectionForMembers($events);

        $this->assertCount(2, $resources);
        $resources->each(function (EventResource $resource): void {
            $array = $resource->toArray(new Request);
            $this->assertArrayHasKey('duration', $array);
            $this->assertArrayNotHasKey('composition', $array);
        });
    }

    #[Test]
    public function collection_for_visitors_wraps_each_event_in_the_visitor_scope(): void
    {
        $events = Event::factory()->live()->count(2)->create()->load('raids');

        $resources = EventResource::collectionForVisitors($events);

        $this->assertCount(2, $resources);
        $resources->each(function (EventResource $resource): void {
            $array = $resource->toArray(new Request);
            $this->assertArrayHasKey('raids', $array);
            $this->assertArrayNotHasKey('duration', $array);
            $this->assertArrayNotHasKey('channel', $array);
        });
    }

    // ==================== helpers ====================

    private function attachToComposition(Event $event, ?int $groupNumber, ?int $slotNumber, bool $isBenched = false): Character
    {
        $character = Character::factory()->withRank()->create();

        $event->characters()->attach($character->id, [
            'slot_number' => $slotNumber,
            'group_number' => $groupNumber,
            'signup_status' => SignupStatus::Confirmed->value,
            'is_benched' => $isBenched,
        ]);

        return $character;
    }

    private function mockChannel(string $id = '111222333', ?string $name = 'general', ?int $position = 0): void
    {
        $this->fakeDiscordChannel($id, $name, $position);
    }

    private function makeResource(Event $event): array
    {
        $event->load('raids.bosses.media', 'bosses.media', 'assignments.group', 'characters.rank');

        return (new EventResource($event))->resolve(new Request);
    }

    private function makeMemberResource(Event $event): array
    {
        return EventResource::forMembers($event)->toArray(new Request);
    }

    private function makeVisitorResource(Event $event): array
    {
        return EventResource::forVisitors($event->load('raids'))->toArray(new Request);
    }
}
