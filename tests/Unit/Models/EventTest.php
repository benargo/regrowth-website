<?php

namespace Tests\Unit\Models;

use App\Casts\AsBinaryColor;
use App\Enums\SignupStatus;
use App\Models\Boss;
use App\Models\Character;
use App\Models\DiscordChannel;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\EventBoss;
use App\Models\EventCharacter;
use App\Models\EventRaid;
use App\Models\GameVersion;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
class EventTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Event::class;
    }

    #[Test]
    public function it_uses_events_table(): void
    {
        $model = new Event;

        $this->assertSame('events', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Event;

        $this->assertFillable($model, [
            'id',
            'raid_helper_event_id',
            'title',
            'start_time',
            'end_time',
            'color',
            'channel_id',
            'is_template',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new Event;

        $this->assertFillableAttribute($model, [
            'id',
            'raid_helper_event_id',
            'title',
            'start_time',
            'end_time',
            'color',
            'channel_id',
            'is_template',
        ]);
    }

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $model = new Event;

        $this->assertHidden($model, ['channel_id', 'is_template']);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Event;

        $this->assertCasts($model, [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'color' => AsBinaryColor::class,
            'is_template' => 'boolean',
        ]);
    }

    #[Test]
    public function is_template_defaults_to_false(): void
    {
        $model = new Event;

        $this->assertFalse($model->is_template);
    }

    #[Test]
    public function template_factory_state_sets_is_template_true(): void
    {
        $event = Event::factory()->template()->make();

        $this->assertTrue($event->is_template);
    }

    // ==================== color ====================

    #[Test]
    public function color_is_nullable(): void
    {
        $event = $this->create(['color' => null]);

        $this->assertNull($event->color);
        $this->assertModelExists($event);
    }

    #[Test]
    public function color_setter_accepts_hex_string_without_hash(): void
    {
        $event = $this->create(['color' => '8b7ed0']);

        $this->assertSame('8b7ed0', $event->color);
    }

    #[Test]
    public function color_setter_accepts_hex_string_with_hash(): void
    {
        $event = $this->create(['color' => '#8b7ed0']);

        $this->assertSame('8b7ed0', $event->color);
    }

    #[Test]
    public function color_setter_accepts_integer_hex_literal(): void
    {
        $event = $this->create(['color' => 0x8B7ED0]);

        $this->assertSame('8b7ed0', $event->color);
    }

    #[Test]
    public function color_setter_accepts_rgb_string(): void
    {
        $event = $this->create(['color' => '34,110,115']);

        $this->assertSame('226e73', $event->color);
    }

    #[Group('error-handling')]
    #[Test]
    public function color_setter_throws_for_invalid_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->create(['color' => 'not-a-color']);
    }

    #[Test]
    public function it_can_be_created(): void
    {
        $event = $this->create(['title' => 'Naxxramas Night']);

        $this->assertTableHas(['title' => 'Naxxramas Night']);
        $this->assertModelExists($event);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        $event = $this->create();

        $this->assertNotNull($event->created_at);
        $this->assertNotNull($event->updated_at);
    }

    #[Test]
    public function raid_helper_event_id_is_unique(): void
    {
        $this->create(['raid_helper_event_id' => 'unique-123']);

        $this->assertUniqueConstraint(fn () => $this->create(['raid_helper_event_id' => 'unique-123']));
    }

    // prunable

    #[Test]
    public function prunable_returns_a_builder(): void
    {
        $event = new Event;

        $this->assertInstanceOf(Builder::class, $event->prunable());
    }

    #[Test]
    public function prunable_includes_events_that_ended_more_than_one_month_ago(): void
    {
        $event = $this->create(['end_time' => now()->subMonth()->subSecond()]);

        $ids = (new Event)->prunable()->pluck('id')->toArray();

        $this->assertContains($event->id, $ids);
    }

    #[Test]
    public function prunable_includes_events_that_ended_long_ago(): void
    {
        $event = $this->create(['end_time' => now()->subYear()]);

        $ids = (new Event)->prunable()->pluck('id')->toArray();

        $this->assertContains($event->id, $ids);
    }

    #[Test]
    public function prunable_includes_events_whose_end_time_is_exactly_one_month_ago(): void
    {
        $event = $this->create(['end_time' => now()->subMonth()]);

        $ids = (new Event)->prunable()->pluck('id')->toArray();

        $this->assertContains($event->id, $ids);
    }

    #[Test]
    public function prunable_excludes_events_that_ended_less_than_one_month_ago(): void
    {
        $event = $this->create(['end_time' => now()->subMonth()->addSecond()]);

        $ids = (new Event)->prunable()->pluck('id')->toArray();

        $this->assertNotContains($event->id, $ids);
    }

    #[Test]
    public function prunable_excludes_events_that_have_not_yet_ended(): void
    {
        $event = $this->create(['end_time' => now()->addSecond()]);

        $ids = (new Event)->prunable()->pluck('id')->toArray();

        $this->assertNotContains($event->id, $ids);
    }

    #[Test]
    public function prunable_excludes_template_events(): void
    {
        $template = $this->create([
            'end_time' => now()->subMonth()->subSecond(),
            'is_template' => true,
        ]);

        $ids = (new Event)->prunable()->pluck('id')->toArray();

        $this->assertNotContains($template->id, $ids);
    }

    #[Test]
    public function prunable_includes_non_template_events_that_ended_more_than_one_month_ago(): void
    {
        $event = $this->create([
            'end_time' => now()->subMonth()->subSecond(),
            'is_template' => false,
        ]);

        $ids = (new Event)->prunable()->pluck('id')->toArray();

        $this->assertContains($event->id, $ids);
    }

    // scopes

    #[Test]
    public function live_scope_returns_only_non_template_events(): void
    {
        $template = $this->create(['is_template' => true]);
        $regular = $this->create(['is_template' => false]);

        $ids = Event::live()->pluck('id')->toArray();

        $this->assertNotContains($template->id, $ids);
        $this->assertContains($regular->id, $ids);
    }

    #[Test]
    public function templates_scope_returns_only_template_events(): void
    {
        $template = $this->create(['is_template' => true]);
        $regular = $this->create(['is_template' => false]);

        $ids = Event::templates()->pluck('id')->toArray();

        $this->assertContains($template->id, $ids);
        $this->assertNotContains($regular->id, $ids);
    }

    // duration

    #[Test]
    public function duration_returns_difference_in_seconds(): void
    {
        $startTime = now();
        $endTime = $startTime->copy()->addHours(2);

        $event = Event::factory()->make([
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        $this->assertEquals(7200, $event->duration);
    }

    // assignmentGroups

    #[Test]
    public function it_has_many_assignment_groups(): void
    {
        $event = $this->create();
        EventAssignmentGroup::factory()->count(2)->create(['event_id' => $event->id]);

        $this->assertInstanceOf(HasMany::class, $event->assignmentGroups());
        $this->assertCount(2, $event->assignmentGroups);
        $this->assertInstanceOf(EventAssignmentGroup::class, $event->assignmentGroups->first());
    }

    // assignments

    #[Test]
    public function it_has_many_assignments(): void
    {
        $event = $this->create();
        EventAssignment::factory()->count(2)->create(['event_id' => $event->id]);

        $this->assertInstanceOf(HasMany::class, $event->assignments());
        $this->assertCount(2, $event->assignments);
        $this->assertInstanceOf(EventAssignment::class, $event->assignments->first());
    }

    // bosses

    #[Test]
    public function bosses_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->bosses());
    }

    #[Test]
    public function it_can_attach_bosses(): void
    {
        $event = $this->create();
        $boss = Boss::factory()->create();

        $event->bosses()->attach($boss->id);

        $this->assertCount(1, $event->bosses);
        $this->assertSame($boss->id, $event->bosses->first()->id);
    }

    #[Test]
    public function bosses_returns_empty_collection_when_none_attached(): void
    {
        $event = $this->create();

        $this->assertCount(0, $event->bosses);
    }

    #[Test]
    public function bosses_use_the_event_boss_pivot(): void
    {
        $event = $this->create();
        $boss = Boss::factory()->create();

        $event->bosses()->attach($boss->id);

        $pivot = $event->bosses->first()->pivot;

        $this->assertInstanceOf(EventBoss::class, $pivot);
        $this->assertNotNull($pivot->sort_order);
    }

    #[Test]
    public function bosses_are_ordered_by_pivot_sort_order(): void
    {
        $event = $this->create();

        // Created in reverse so id order and pivot order differ.
        $third = Boss::factory()->create();
        $second = Boss::factory()->create();
        $first = Boss::factory()->create();

        // The pivot does not number itself on create, so each row's position
        // comes from the sort_order passed to attach().
        $event->bosses()->attach($first->id, ['sort_order' => 1]);
        $event->bosses()->attach($second->id, ['sort_order' => 2]);
        $event->bosses()->attach($third->id, ['sort_order' => 3]);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $event->bosses->pluck('id')->all(),
        );
    }

    #[Test]
    public function deleting_event_cascades_to_bosses_pivot(): void
    {
        $event = $this->create();
        $boss = Boss::factory()->create();
        $event->bosses()->attach($boss->id);

        $event->delete();

        $this->assertDatabaseMissing('pivot_events_bosses', [
            'event_id' => $event->id,
        ]);
    }

    // channel

    #[Test]
    public function channel_returns_the_stored_channel(): void
    {
        $channel = DiscordChannel::factory()->create();
        $event = Event::factory()->for($channel, 'channel')->create();

        $this->assertTrue($event->channel->is($channel));
    }

    #[Test]
    public function channel_returns_a_soft_deleted_channel(): void
    {
        $channel = DiscordChannel::factory()->trashed()->create();
        $event = Event::factory()->for($channel, 'channel')->create();

        $this->assertTrue($event->channel->is($channel));
    }

    #[Test]
    public function a_template_has_no_channel(): void
    {
        $event = Event::factory()->template()->create();

        $this->assertNull($event->channel_id);
        $this->assertNull($event->channel);
    }

    // characters

    #[Test]
    public function characters_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->characters());
    }

    #[Test]
    public function it_can_attach_characters(): void
    {
        $event = $this->create();
        $character = Character::factory()->create();

        $event->characters()->attach($character->id);

        $this->assertCount(1, $event->characters);
        $this->assertSame($character->id, $event->characters->first()->id);
    }

    #[Test]
    public function characters_returns_empty_collection_when_none_attached(): void
    {
        $event = $this->create();

        $this->assertCount(0, $event->characters);
    }

    #[Test]
    public function characters_use_event_character_pivot(): void
    {
        $event = $this->create();
        $character = Character::factory()->create();

        $event->characters()->attach($character->id, [
            'slot_number' => 3,
            'group_number' => 1,
            'signup_status' => SignupStatus::Confirmed->value,
        ]);

        $pivot = $event->characters->first()->pivot;

        $this->assertInstanceOf(EventCharacter::class, $pivot);
        $this->assertSame(3, $pivot->slot_number);
        $this->assertSame(1, $pivot->group_number);
        $this->assertSame(SignupStatus::Confirmed, $pivot->signup_status);
    }

    #[Test]
    public function deleting_event_cascades_to_pivot(): void
    {
        $event = $this->create();
        $character = Character::factory()->create();
        $event->characters()->attach($character->id);

        $event->delete();

        $this->assertDatabaseMissing('pivot_events_characters', [
            'event_id' => $event->id,
        ]);
    }

    // gameVersion

    #[Test]
    public function it_belongs_to_a_game_version(): void
    {
        $event = $this->factory()->forGameVersion(GameVersion::factory()->create())->create();

        $this->assertRelation($event, 'gameVersion', BelongsTo::class);
    }

    #[Test]
    public function factory_for_game_version_state_attaches_a_raid_in_that_version(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $event = $this->factory()->forGameVersion($gameVersion)->create();

        $this->assertSame($gameVersion->id, $event->raids->sole()->phase->game_version_id);
        $this->assertSame($gameVersion->id, $event->fresh()->game_version_id);
    }

    // leaders

    #[Test]
    public function leaders_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->leaders());
    }

    #[Test]
    public function leaders_returns_only_characters_with_is_leader_true(): void
    {
        $event = $this->factory()->withLeader()->create();
        $nonLeader = Character::factory()->create();
        $event->characters()->attach($nonLeader->id, ['is_leader' => false]);

        $this->assertCount(1, $event->leaders);
        $this->assertTrue($event->leaders->first()->pivot->is_leader);
    }

    #[Test]
    public function leaders_returns_empty_when_no_leaders_attached(): void
    {
        $event = $this->create();
        $character = Character::factory()->create();
        $event->characters()->attach($character->id, ['is_leader' => false]);

        $this->assertCount(0, $event->leaders);
    }

    // lootCouncillors

    #[Test]
    public function loot_councillors_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->lootCouncillors());
    }

    #[Test]
    public function loot_councillors_returns_only_characters_with_is_loot_councillor_true(): void
    {
        $event = $this->factory()->withLootCouncillor()->create();
        $regular = Character::factory()->create();
        $event->characters()->attach($regular->id, ['is_loot_councillor' => false]);

        $this->assertCount(1, $event->lootCouncillors);
        $this->assertTrue($event->lootCouncillors->first()->pivot->is_loot_councillor);
    }

    #[Test]
    public function loot_councillors_returns_empty_when_none_attached(): void
    {
        $event = $this->create();

        $this->assertCount(0, $event->lootCouncillors);
    }

    // lootMasters

    #[Test]
    public function loot_masters_returns_only_characters_with_is_loot_master_true(): void
    {
        $event = $this->create();
        $master = Character::factory()->create();
        $regular = Character::factory()->create();
        $event->characters()->attach($master->id, ['is_loot_master' => true]);
        $event->characters()->attach($regular->id, ['is_loot_master' => false]);

        $masterIds = $event->lootMasters->pluck('id')->all();

        $this->assertSame([$master->id], $masterIds);
    }

    // raids

    #[Test]
    public function raids_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->raids());
    }

    #[Test]
    public function it_can_attach_raids(): void
    {
        $event = $this->create();
        $raid = Raid::factory()->create();

        $event->raids()->attach($raid->id);

        $this->assertCount(1, $event->raids);
        $this->assertSame($raid->id, $event->raids->first()->id);
    }

    #[Test]
    public function raids_returns_empty_collection_when_none_attached(): void
    {
        $event = $this->create();

        $this->assertCount(0, $event->raids);
    }

    #[Test]
    public function raids_use_the_event_raid_pivot(): void
    {
        $event = $this->create();
        $raid = Raid::factory()->create();

        $event->raids()->attach($raid->id);

        $pivot = $event->raids->first()->pivot;

        $this->assertInstanceOf(EventRaid::class, $pivot);
        $this->assertNotNull($pivot->sort_order);
    }

    #[Test]
    public function raids_are_ordered_by_pivot_sort_order(): void
    {
        $event = $this->create();

        // Created in reverse so id order and pivot order differ.
        $third = Raid::factory()->create();
        $second = Raid::factory()->create();
        $first = Raid::factory()->create();

        // The pivot does not number itself on create, so each row's position
        // comes from the sort_order passed to attach().
        $event->raids()->attach($first->id, ['sort_order' => 1]);
        $event->raids()->attach($second->id, ['sort_order' => 2]);
        $event->raids()->attach($third->id, ['sort_order' => 3]);

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $event->raids->pluck('id')->all(),
        );
    }

    #[Test]
    public function deleting_event_cascades_to_raids_pivot(): void
    {
        $event = $this->create();
        $raid = Raid::factory()->create();
        $event->raids()->attach($raid->id);

        $event->delete();

        $this->assertDatabaseMissing('pivot_events_raids', [
            'event_id' => $event->id,
        ]);
    }

    // users

    #[Test]
    public function users_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->users());
    }

    #[Test]
    public function it_can_attach_users(): void
    {
        $event = $this->create();
        $user = User::factory()->create();

        $event->users()->attach($user->id);

        $this->assertCount(1, $event->users);
        $this->assertSame($user->id, $event->users->first()->id);
    }

    #[Test]
    public function users_returns_empty_collection_when_none_attached(): void
    {
        $event = $this->create();

        $this->assertCount(0, $event->users);
    }

    #[Test]
    public function deleting_event_cascades_to_users_pivot(): void
    {
        $event = $this->create();
        $user = User::factory()->create();
        $event->users()->attach($user->id);

        $event->delete();

        $this->assertDatabaseMissing('pivot_events_users', [
            'event_id' => $event->id,
        ]);
    }

    #[Test]
    public function with_users_factory_state_attaches_given_users(): void
    {
        $users = User::factory()->count(2)->create();

        $event = Event::factory()->withUsers($users->all())->create();

        $this->assertEqualsCanonicalizing($users->pluck('id')->all(), $event->users->pluck('id')->all());
    }

    #[Test]
    public function with_users_factory_state_creates_users_when_none_given(): void
    {
        $event = Event::factory()->withUsers(count: 3)->create();

        $this->assertCount(3, $event->users);
    }

    // slottedUsers

    #[Test]
    public function slotted_users_returns_belongs_to_many_relationship(): void
    {
        $model = new Event;

        $this->assertInstanceOf(BelongsToMany::class, $model->slottedUsers());
    }

    #[Test]
    public function slotted_users_returns_only_attached_users_with_a_slotted_character(): void
    {
        $event = $this->create();
        [$slotted, $unslotted, $unattached, $multiSlotted] = User::factory()->count(4)->create()->all();
        $event->users()->attach([$slotted->id, $unslotted->id, $multiSlotted->id]);

        $event->characters()->attach(Character::factory()->create(['user_id' => $slotted->id])->id, ['slot_number' => 1]);
        $event->characters()->attach(Character::factory()->create(['user_id' => $unslotted->id])->id, ['slot_number' => null]);
        $event->characters()->attach(Character::factory()->create(['user_id' => $unattached->id])->id, ['slot_number' => 2]);
        $event->characters()->attach(Character::factory()->create(['user_id' => $multiSlotted->id])->id, ['slot_number' => 3]);
        $event->characters()->attach(Character::factory()->create(['user_id' => $multiSlotted->id])->id, ['slot_number' => 4]);

        $this->assertEqualsCanonicalizing(
            [$slotted->id, $multiSlotted->id],
            $event->slottedUsers->pluck('id')->all(),
        );
    }

    #[Test]
    public function slotted_users_is_scoped_per_event_when_eager_loaded(): void
    {
        $slottedEvent = $this->create();
        $unslottedEvent = $this->create();
        $user = User::factory()->create();
        $character = Character::factory()->create(['user_id' => $user->id]);
        $slottedEvent->users()->attach($user->id);
        $unslottedEvent->users()->attach($user->id);
        $slottedEvent->characters()->attach($character->id, ['slot_number' => 1]);
        $unslottedEvent->characters()->attach($character->id, ['slot_number' => null]);

        $events = Event::with('slottedUsers')->whereKey([$slottedEvent->id, $unslottedEvent->id])->get()->keyBy('id');

        $this->assertSame([$user->id], $events[$slottedEvent->id]->slottedUsers->pluck('id')->all());
        $this->assertCount(0, $events[$unslottedEvent->id]->slottedUsers);
    }
}
