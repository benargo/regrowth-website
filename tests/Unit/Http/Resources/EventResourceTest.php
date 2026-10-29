<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\SignupStatus;
use App\Http\Resources\EventResource;
use App\Models\Boss;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\GameVersion;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
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
        $event = Event::factory()->create()->load('raids.bosses.media', 'bosses.media', 'assignments.group', 'characters.rank');

        $array = (new EventResource($event))->resolve(new Request);

        $this->assertSame(
            ['id', 'title', 'start_time', 'end_time', 'duration', 'updated_at', 'color', 'banner_url', 'banner_position', 'assignments', 'composition', 'raids'],
            array_keys($array),
        );
    }

    // ==================== id, title, start and end times ====================

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
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
        $event = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['start_time']);
    }

    #[Test]
    public function it_returns_null_end_time_when_event_has_no_end_time(): void
    {
        $event = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['end_time']);
    }

    // ==================== duration ====================

    #[Test]
    public function it_returns_duration_as_seconds_between_start_and_end(): void
    {
        $event = Event::factory()->create(['start_time' => '2026-09-18 19:00:00', 'end_time' => '2026-09-18 22:00:00']);

        $array = $this->makeResource($event);

        $this->assertSame(10800.0, $array['duration']);
    }

    #[Test]
    public function it_returns_null_duration_when_event_has_no_start_or_end_time(): void
    {
        $event = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['duration']);
    }

    // ==================== color ====================

    #[Test]
    public function it_returns_null_color_when_event_has_no_color(): void
    {
        $event = Event::factory()->create(['color' => null]);

        $array = $this->makeResource($event);

        $this->assertNull($array['color']);
    }

    #[Test]
    public function it_returns_color_as_hex_string(): void
    {
        $event = Event::factory()->create(['color' => '34,110,115']);

        $array = $this->makeResource($event);

        $this->assertSame('226e73', $array['color']);
    }

    // ==================== banner ====================

    #[Group('laravel-media-library')]
    #[Test]
    public function banner_comes_from_the_first_raid_in_pivot_order_that_has_one(): void
    {
        Storage::fake('s3');
        $plain = Raid::factory()->create();
        $second = Raid::factory()->withBanner()->create();
        $first = Raid::factory()->withBanner('center bottom')->create();
        $event = Event::factory()->create();
        $event->raids()->attach($second->id, ['sort_order' => 3]);
        $event->raids()->attach($plain->id, ['sort_order' => 1]);
        $event->raids()->attach($first->id, ['sort_order' => 2]);

        $array = (new EventResource($event->load('raids.media')))->resolve(new Request);

        $this->assertSame($first->getFirstMediaUrl(Raid::MEDIA_BANNER, Raid::MEDIA_CONVERSION_HEADER), $array['banner_url']);
        $this->assertSame('center bottom', $array['banner_position']);
    }

    #[Test]
    public function banner_is_null_when_no_attached_raid_has_one(): void
    {
        $event = Event::factory()->create();
        $event->raids()->attach(Raid::factory()->create()->id, ['sort_order' => 1]);

        $array = (new EventResource($event->load('raids.media')))->resolve(new Request);

        $this->assertArrayHasKey('banner_url', $array);
        $this->assertArrayHasKey('banner_position', $array);
        $this->assertNull($array['banner_url']);
        $this->assertNull($array['banner_position']);
    }

    #[Test]
    public function banner_is_null_when_the_event_has_no_raids(): void
    {
        $event = Event::factory()->create();

        $array = (new EventResource($event->load('raids.media')))->resolve(new Request);

        $this->assertArrayHasKey('banner_url', $array);
        $this->assertArrayHasKey('banner_position', $array);
        $this->assertNull($array['banner_url']);
        $this->assertNull($array['banner_position']);
    }

    #[Test]
    public function banner_is_missing_when_raids_are_not_loaded(): void
    {
        $array = (new EventResource(Event::factory()->create()->fresh()))->resolve(new Request);

        $this->assertArrayNotHasKey('banner_url', $array);
        $this->assertArrayNotHasKey('banner_position', $array);
    }

    #[Test]
    public function banner_is_missing_when_raid_media_is_not_loaded(): void
    {
        $event = Event::factory()->create();
        $event->raids()->attach(Raid::factory()->create());

        $array = (new EventResource($event->load('raids')))->resolve(new Request);

        $this->assertArrayNotHasKey('banner_url', $array);
        $this->assertArrayNotHasKey('banner_position', $array);
    }

    #[Test]
    #[TestWith(['members'])]
    #[TestWith(['visitors'])]
    public function banner_is_not_in_the_members_or_visitors_scope(string $scope): void
    {
        Storage::fake('s3');
        $event = Event::factory()->create();
        $event->raids()->attach(Raid::factory()->withBanner()->create()->id, ['sort_order' => 1]);

        $array = (new EventResource($event->load('raids.media')))->scope($scope)->resolve(new Request);

        $this->assertArrayNotHasKey('banner_url', $array);
        $this->assertArrayNotHasKey('banner_position', $array);
    }

    // ==================== assignments ====================

    #[Test]
    public function it_returns_event_level_assignments_excluding_boss_scoped_ones(): void
    {

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
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayHasKey('groups', $array['composition']);
        $this->assertArrayHasKey('bench', $array['composition']);
    }

    #[Test]
    public function it_returns_groups_with_correct_character_shape(): void
    {

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
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame([], $array['composition']['groups']);
    }

    #[Test]
    public function it_excludes_benched_characters_from_groups(): void
    {

        $event = Event::factory()->hasAttached(Raid::factory()->state(['max_players' => 10]), [], 'raids')->create();
        $inComp = $this->attachToComposition($event, groupNumber: 1, slotNumber: 1);
        $this->attachToComposition($event, groupNumber: null, slotNumber: null, isBenched: true);

        $array = $this->makeResource($event);

        $this->assertSame([$inComp->id], array_column($array['composition']['groups'][0]['characters'], 'id'));
    }

    #[Test]
    public function it_orders_groups_by_group_number_and_characters_by_slot_number(): void
    {

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
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertSame([], $array['composition']['bench']);
    }

    #[Test]
    public function it_returns_benched_characters_not_in_comp(): void
    {

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
        $event = Event::factory()->withUsers()->create();

        $array = (new EventResource($event->fresh()))->resolve(new Request);

        $this->assertArrayNotHasKey('users', $array);
    }

    #[Test]
    public function it_returns_users_when_loaded(): void
    {
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
        $event = Event::factory()->withUsers()->create()->fresh()->load('users', 'raids');

        $this->assertArrayNotHasKey('users', $this->makeMemberResource($event));
        $this->assertArrayNotHasKey('users', $this->makeVisitorResource($event));
    }

    // ==================== channel ====================

    #[Test]
    public function it_has_no_channel_key(): void
    {
        $this->fakeDiscord([]);
        $event = Event::factory()->create();

        $array = $this->makeResource($event);

        $this->assertArrayNotHasKey('channel', $array);
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_omits_relation_fields_that_are_not_loaded(): void
    {
        $array = (new EventResource(Event::factory()->create()->fresh()))->resolve(new Request);

        foreach (['assignments', 'composition', 'raids', 'users', 'game_version'] as $key) {
            $this->assertArrayNotHasKey($key, $array);
        }
    }

    // ==================== members scope ====================

    #[Test]
    public function the_member_scope_returns_exactly_the_expected_keys(): void
    {
        $event = Event::factory()->create();

        $array = $this->makeMemberResource($event);

        $this->assertSame(['id', 'title', 'start_time', 'end_time', 'duration'], array_keys($array));
    }

    #[Test]
    public function the_member_scope_returns_correct_scalar_fields(): void
    {
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
        $event = Event::factory()->create(['start_time' => '2026-09-18 19:00:00', 'end_time' => '2026-09-18 22:00:00']);

        $array = $this->makeMemberResource($event);

        $this->assertSame(10800.0, $array['duration']);
    }

    #[Test]
    public function the_member_scope_does_not_include_composition_raids_or_assignments(): void
    {
        $event = Event::factory()->create();

        $array = $this->makeMemberResource($event);

        $this->assertArrayNotHasKey('composition', $array);
        $this->assertArrayNotHasKey('raids', $array);
        $this->assertArrayNotHasKey('assignments', $array);
    }

    // ==================== visitors scope ====================

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
    public function the_visitor_scope_lists_raids_without_bosses(): void
    {
        $event = Event::factory()->live()->create();
        $event->raids()->attach(Raid::factory()->create(['name' => 'Black Temple']), ['sort_order' => 1]);

        $raids = $this->makeVisitorResource($event)['raids'];

        $this->assertSame(['Black Temple'], array_column($raids, 'name'));
        $this->assertArrayNotHasKey('bosses', $raids[0]);
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

    // ==================== template scope ====================

    #[Test]
    public function the_template_scope_returns_exactly_the_expected_keys(): void
    {
        $event = Event::factory()->template()->create();

        $array = $this->makeTemplateResource($event);

        $this->assertSame(['id', 'title', 'updated_at', 'raids'], array_keys($array));
    }

    #[Test]
    public function the_template_scope_returns_updated_at_as_iso8601(): void
    {
        $event = Event::factory()->template()->create();

        $array = $this->makeTemplateResource($event);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['updated_at']);
    }

    #[Test]
    public function the_template_scope_lists_the_template_raids(): void
    {
        $event = Event::factory()->template()->create();
        $event->raids()->attach(Raid::factory()->create(['name' => 'Black Temple']), ['sort_order' => 1]);

        $raids = $this->makeTemplateResource($event)['raids'];

        $this->assertSame(['Black Temple'], array_column($raids, 'name'));
    }

    #[Test]
    public function the_default_scope_still_returns_raids_as_full_objects(): void
    {
        $event = Event::factory()->create();
        $event->raids()->attach(Raid::factory()->create(['name' => 'Black Temple']), ['sort_order' => 1]);

        $raids = $this->makeResource($event)['raids'];

        $this->assertIsArray($raids[0]);
        $this->assertArrayHasKey('bosses', $raids[0]);
        $this->assertSame('Black Temple', $raids[0]['name']);
    }

    // ==================== game version ====================

    #[Test]
    public function the_member_scope_includes_the_game_version_icon_fields_when_loaded(): void
    {
        $array = (new EventResource($this->unsavedEventInGameVersion()))
            ->scope('members')
            ->resolve(new Request);

        $this->assertSame(['id', 'title', 'start_time', 'end_time', 'duration', 'game_version'], array_keys($array));
        $this->assertSame(['id' => 7, 'title' => 'World of Warcraft: Forever', 'color' => '226e73', 'icon_url' => null], $array['game_version']);
    }

    #[Test]
    public function the_visitor_scope_includes_the_game_version_icon_fields_when_loaded(): void
    {
        $event = $this->unsavedEventInGameVersion()->setRelation('raids', new EloquentCollection);

        $array = (new EventResource($event))->scope('visitors')->resolve(new Request);

        $this->assertSame(['id', 'title', 'start_time', 'end_time', 'game_version', 'raids'], array_keys($array));
        $this->assertSame(['id' => 7, 'title' => 'World of Warcraft: Forever', 'color' => '226e73', 'icon_url' => null], $array['game_version']);
    }

    #[Test]
    public function game_version_is_null_when_the_loaded_event_has_none(): void
    {
        $event = $this->unsavedEventInGameVersion()
            ->setRelation('gameVersion', null)
            ->setRelation('raids', new EloquentCollection);

        $array = (new EventResource($event))->scope('visitors')->resolve(new Request);

        $this->assertArrayHasKey('game_version', $array);
        $this->assertNull($array['game_version']);
    }

    #[Test]
    public function the_template_scope_never_includes_the_game_version(): void
    {
        $event = $this->unsavedEventInGameVersion()->setRelation('raids', new EloquentCollection);

        $array = (new EventResource($event))->scope('template')->resolve(new Request);

        $this->assertSame(['id', 'title', 'updated_at', 'raids'], array_keys($array));
    }

    #[Test]
    public function the_default_shape_places_the_full_game_version_after_color(): void
    {
        $array = (new EventResource($this->unsavedEventInGameVersion()))->resolve(new Request);

        $this->assertSame(['id', 'title', 'start_time', 'end_time', 'duration', 'updated_at', 'color', 'game_version'], array_keys($array));
        $this->assertSame('forever', $array['game_version']['slug']);
    }

    // ==================== collections ====================

    #[Test]
    public function the_members_scope_applies_to_every_item_in_a_collection(): void
    {
        $events = Event::factory()->count(2)->create();

        $array = EventResource::collection($events)->scope('members')->resolve(new Request);

        $this->assertCount(2, $array);

        foreach ($array as $event) {
            $this->assertArrayHasKey('duration', $event);
            $this->assertArrayNotHasKey('composition', $event);
        }
    }

    #[Test]
    public function the_visitors_scope_applies_to_collections_built_from_models(): void
    {
        $events = Event::factory()->live()->count(2)->create()->load('raids');

        $array = $events->toResourceCollection(EventResource::class)->scope('visitors')->resolve(new Request);

        $this->assertCount(2, $array);

        foreach ($array as $event) {
            $this->assertArrayHasKey('raids', $event);
            $this->assertArrayNotHasKey('duration', $event);
        }
    }

    #[Test]
    #[Group('contract')]
    public function event_resource_declares_the_members_visitors_and_template_scopes(): void
    {
        $this->assertSame(['members', 'template', 'visitors'], array_keys(EventResource::definedScopes()));
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

    private function makeResource(Event $event): array
    {
        $event->load('raids.bosses.media', 'bosses.media', 'assignments.group', 'characters.rank');

        return (new EventResource($event))->resolve(new Request);
    }

    private function makeMemberResource(Event $event): array
    {
        return (new EventResource($event))
            ->scope('members')
            ->resolve(new Request);
    }

    private function makeVisitorResource(Event $event): array
    {
        return (new EventResource($event->load('raids')))->scope('visitors')->resolve(new Request);
    }

    private function makeTemplateResource(Event $event): array
    {
        return (new EventResource($event->load('raids')))->scope('template')->resolve(new Request);
    }

    private function unsavedEventInGameVersion(): Event
    {
        $gameVersion = (new GameVersion)->forceFill([
            'id' => 7,
            'title' => 'World of Warcraft: Forever',
            'slug' => 'forever',
            'color' => '226e73',
        ])->setRelation('media', new EloquentCollection);

        return (new Event)
            ->forceFill([
                'id' => '0199f2a4-6c1e-7a51-9d1b-1f3c2e4a5b6c',
                'title' => 'Karazhan',
                'start_time' => '2026-11-07 19:00:00',
                'end_time' => '2026-11-07 22:00:00',
            ])
            ->setRelation('gameVersion', $gameVersion);
    }
}
