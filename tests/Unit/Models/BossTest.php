<?php

namespace Tests\Unit\Models;

use App\Contracts\Models\BelongsToGameVersion;
use App\Contracts\Models\DatasetModel;
use App\Contracts\Models\EditLockable;
use App\Contracts\Models\HasUsage;
use App\Events\RaidingDataChanged;
use App\Jobs\BuildAddonExportFile;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use App\Policies\DatasetPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\EloquentSortable\Sortable;
use Spatie\MediaLibrary\HasMedia;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
class BossTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Boss::class;
    }

    #[Test]
    public function it_uses_bosses_table(): void
    {
        $model = new Boss;

        $this->assertSame('bosses', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new Boss;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Boss;

        $this->assertFillable($model, [
            'name',
            'sort_order',
            'notes',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new Boss;

        $this->assertFillableAttribute($model, [
            'name',
            'sort_order',
            'notes',
        ]);
    }

    // ==================== persistence and factory ====================

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $raid = Raid::factory()->create();

        $boss = $this->create([
            'name' => 'Prince Malchezaar',
            'raid_id' => $raid->id,
            'sort_order' => 1,
        ]);

        $this->assertTableHas(['name' => 'Prince Malchezaar']);
        $this->assertModelExists($boss);
    }

    #[Test]
    public function it_can_be_created_with_notes(): void
    {
        $raid = Raid::factory()->create();
        $notes = 'Remember to use all cooldowns on this boss';

        $boss = $this->create([
            'name' => 'Prince Malchezaar',
            'raid_id' => $raid->id,
            'sort_order' => 1,
            'notes' => $notes,
        ]);

        $this->assertTableHas(['notes' => $notes]);
        $this->assertSame($notes, $boss->notes);
    }

    #[Test]
    public function it_refuses_two_bosses_with_the_same_name_in_one_raid(): void
    {
        $raid = Raid::factory()->create();
        $this->create(['name' => 'Moroes', 'raid_id' => $raid->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->create(['name' => 'Moroes', 'raid_id' => $raid->id]);
    }

    #[Test]
    public function it_allows_the_same_name_in_different_raids(): void
    {
        $first = $this->create(['name' => 'Moroes', 'raid_id' => Raid::factory()->create()->id]);
        $second = $this->create(['name' => 'Moroes', 'raid_id' => Raid::factory()->create()->id]);

        $this->assertModelExists($first);
        $this->assertModelExists($second);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $boss = $this->create();

        $this->assertNotEmpty($boss->name);
        $this->assertNotNull($boss->raid_id);
        $this->assertNotNull($boss->sort_order);
        $this->assertModelExists($boss);
    }

    #[Test]
    public function factory_order_state_sets_sort_order(): void
    {
        $boss = $this->factory()->order(5)->create();

        $this->assertSame(5, $boss->sort_order);
    }

    // ==================== sort order ====================

    #[Test]
    public function it_implements_sortable_interface(): void
    {
        $this->assertInstanceOf(Sortable::class, new Boss);
    }

    #[Test]
    public function it_casts_sort_order_to_integer(): void
    {
        $this->assertCasts(new Boss, ['sort_order' => 'integer']);
    }

    #[Test]
    public function it_assigns_sort_order_one_to_the_first_boss_in_a_raid(): void
    {
        $raid = Raid::factory()->create();

        $boss = $this->create(['raid_id' => $raid->id]);

        $this->assertSame(1, $boss->sort_order);
    }

    #[Test]
    public function it_scopes_sort_order_increments_per_raid(): void
    {
        $raidA = Raid::factory()->create();
        $raidB = Raid::factory()->create();

        $this->create(['raid_id' => $raidA->id]);
        $this->create(['raid_id' => $raidA->id]);

        $firstInRaidB = $this->create(['raid_id' => $raidB->id]);

        $this->assertSame(1, $firstInRaidB->sort_order);
    }

    #[Test]
    public function it_overwrites_an_explicitly_provided_sort_order_on_create(): void
    {
        $boss = $this->create(['sort_order' => 5]);

        $this->assertSame(1, $boss->sort_order);
        $this->assertSame(1, $boss->fresh()->sort_order);
    }

    // ==================== relationships ====================

    #[Test]
    public function it_belongs_to_a_raid(): void
    {
        $raid = Raid::factory()->create();
        $boss = $this->create(['raid_id' => $raid->id]);

        $this->assertRelation($boss, 'raid', BelongsTo::class);
        $this->assertTrue($boss->raid->is($raid));
    }

    #[Test]
    public function it_has_many_items(): void
    {
        $boss = $this->factory()->withItems(2)->create();

        $this->assertRelation($boss, 'items', HasMany::class);
        $this->assertCount(2, $boss->items);
        $this->assertInstanceOf(Item::class, $boss->items->first());
    }

    #[Test]
    public function it_has_many_comments_through_items(): void
    {
        $boss = $this->factory()->withComments(2)->create();

        $this->assertRelation($boss, 'comments', HasManyThrough::class);
        $this->assertCount(2, $boss->comments);
        $this->assertInstanceOf(Comment::class, $boss->comments->first());
    }

    #[Test]
    public function comments_are_scoped_to_item_commentable_type(): void
    {
        $boss = $this->factory()->withComments(2)->create();
        $item = $boss->items->first();

        // Insert a comment with a different commentable_type pointing at the same item ID
        Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => User::class,
        ]);

        $this->assertCount(2, $boss->comments);
    }

    #[Test]
    public function factory_with_items_state_creates_items(): void
    {
        $boss = $this->factory()->withItems(3)->create();

        $this->assertCount(3, $boss->items);
        $this->assertTrue($boss->items->every(fn (Item $item) => $item->boss_id === $boss->id));
    }

    #[Test]
    public function factory_with_comments_state_creates_comments_through_items(): void
    {
        $boss = $this->factory()->withComments(2)->create();

        $this->assertCount(1, $boss->items);
        $this->assertCount(2, $boss->comments);
    }

    // ==================== media ====================

    #[Test]
    public function it_implements_has_media_interface(): void
    {
        $boss = $this->create();

        $this->assertInstanceOf(HasMedia::class, $boss);
    }

    #[Test]
    public function it_can_add_media(): void
    {
        Storage::fake('public');

        $boss = $this->create();
        $boss->addMediaFromString('fake image content')
            ->usingFileName('test-image.png')
            ->toMediaCollection('default');

        $this->assertNotEmpty($boss->getMedia('default'));
    }

    // ==================== slug ====================

    #[Test]
    public function it_generates_slug_from_name(): void
    {
        $boss = $this->create(['name' => 'Prince Malchezaar']);

        $this->assertSame('prince-malchezaar', $boss->slug);
    }

    // ==================== assignments relationship ====================

    #[Test]
    public function it_has_many_assignments(): void
    {
        $boss = $this->create();
        $event = Event::factory()->create();
        EventAssignment::factory()->count(2)->create(['event_id' => $event->id, 'boss_id' => $boss->id]);

        $this->assertInstanceOf(HasMany::class, $boss->assignments());
        $this->assertCount(2, $boss->assignments);
        $this->assertInstanceOf(EventAssignment::class, $boss->assignments->first());
    }

    // ==================== dataset contracts ====================

    #[Test]
    public function it_is_an_edit_locked_dataset_model_with_usage_and_a_game_version(): void
    {
        $boss = new Boss;

        $this->assertInstanceOf(DatasetModel::class, $boss);
        $this->assertInstanceOf(EditLockable::class, $boss);
        $this->assertInstanceOf(HasUsage::class, $boss);
        $this->assertInstanceOf(BelongsToGameVersion::class, $boss);
    }

    #[Test]
    public function it_is_authorised_by_the_dataset_policy(): void
    {
        $this->assertInstanceOf(DatasetPolicy::class, Gate::getPolicyFor(Boss::class));
    }

    #[Test]
    public function it_keeps_its_edit_lock_under_its_own_cache_key(): void
    {
        $boss = $this->create();
        $user = User::factory()->create();

        $boss->acquireEditLock($user);

        $this->assertSame($user->id, Cache::get("bosses.{$boss->id}.editor"));
    }

    #[Test]
    public function it_belongs_to_its_raids_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $boss = Boss::factory()->for(Raid::factory()->for(Phase::factory()->for($gameVersion)))->create();

        $this->assertSame($gameVersion->id, $boss->game_version_id);
    }

    #[Test]
    public function it_has_no_game_version_when_its_raids_phase_has_none(): void
    {
        $boss = Boss::factory()->for(Raid::factory()->for(Phase::factory()->state(['game_version_id' => null])))->create();

        $this->assertNull($boss->game_version_id);
    }

    // ==================== event relationships ====================

    #[Test]
    public function it_splits_its_events_into_past_upcoming_and_templates(): void
    {
        $this->freezeTime();
        $boss = $this->create();
        $past = $this->eventInHours(-48, -45);
        $inProgress = $this->eventInHours(-1, 2);
        $upcoming = $this->eventInHours(24, 27);
        $template = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        foreach ([$past, $inProgress, $upcoming, $template] as $event) {
            $event->bosses()->attach($boss->id, ['sort_order' => 1]);
        }

        $this->assertInstanceOf(BelongsToMany::class, $boss->events());
        $this->assertEqualsCanonicalizing([$past->id, $inProgress->id, $upcoming->id, $template->id], $boss->events->modelKeys());
        $this->assertSame([$past->id], $boss->pastEvents->modelKeys());
        $this->assertEqualsCanonicalizing([$inProgress->id, $upcoming->id], $boss->upcomingEvents->modelKeys());
        $this->assertSame([$template->id], $boss->templateEvents->modelKeys());
    }

    #[Test]
    public function it_splits_its_assignments_into_past_and_upcoming_and_lists_its_upcoming_assignment_groups(): void
    {
        $this->freezeTime();
        $boss = $this->create();
        $past = $this->eventInHours(-48, -45);
        $upcoming = $this->eventInHours(24, 27);
        $pastAssignment = EventAssignment::factory()->forBoss($boss)->create(['event_id' => $past->id]);
        $upcomingAssignment = EventAssignment::factory()->forBoss($boss)->create(['event_id' => $upcoming->id]);
        $upcomingGroup = EventAssignmentGroup::factory()->forBoss($boss)->create(['event_id' => $upcoming->id]);

        $this->assertInstanceOf(HasMany::class, $boss->assignmentGroups());
        $this->assertSame([$pastAssignment->id], $boss->pastAssignments->modelKeys());
        $this->assertSame([$upcomingAssignment->id], $boss->upcomingAssignments->modelKeys());
        $this->assertSame([$upcomingGroup->id], $boss->upcomingAssignmentGroups->modelKeys());
    }

    // ==================== usage ====================

    #[Test]
    public function it_is_in_use_while_items_drop_from_it(): void
    {
        Bus::fake([BuildAddonExportFile::class]);
        $boss = $this->create();
        $this->assertFalse($boss->isInUse());

        Item::factory()->fromBoss($boss)->create();

        $this->assertTrue($boss->isInUse());
    }

    #[Test]
    #[TestWith([24, 27], 'upcoming')]
    #[TestWith([-1, 2], 'in progress')]
    public function it_is_in_use_while_an_upcoming_or_in_progress_event_links_it(int $startsInHours, int $endsInHours): void
    {
        $this->freezeTime();
        $boss = $this->create();

        $this->eventInHours($startsInHours, $endsInHours)->bosses()->attach($boss->id, ['sort_order' => 1]);

        $this->assertTrue($boss->isInUse());
    }

    #[Test]
    public function it_is_in_use_while_an_upcoming_assignment_or_assignment_group_targets_it(): void
    {
        $this->freezeTime();
        $withAssignment = $this->create();
        $withGroup = $this->create();
        $upcoming = $this->eventInHours(24, 27);

        EventAssignment::factory()->forBoss($withAssignment)->create(['event_id' => $upcoming->id]);
        EventAssignmentGroup::factory()->forBoss($withGroup)->create(['event_id' => $upcoming->id]);

        $this->assertTrue($withAssignment->isInUse());
        $this->assertTrue($withGroup->isInUse());
    }

    #[Test]
    public function it_is_not_in_use_with_only_past_links_or_event_templates(): void
    {
        $this->freezeTime();
        $boss = $this->create();
        $past = $this->eventInHours(-48, -45);
        $past->bosses()->attach($boss->id, ['sort_order' => 1]);
        EventAssignment::factory()->forBoss($boss)->create(['event_id' => $past->id]);
        EventAssignmentGroup::factory()->forBoss($boss)->create(['event_id' => $past->id]);
        Event::factory()->template()->create(['start_time' => null, 'end_time' => null])
            ->bosses()->attach($boss->id, ['sort_order' => 1]);

        $this->assertFalse($boss->isInUse());
    }

    #[Test]
    public function with_usage_counts_counts_items_and_upcoming_links(): void
    {
        $this->freezeTime();
        Bus::fake([BuildAddonExportFile::class]);
        $boss = $this->create();
        Item::factory()->count(2)->fromBoss($boss)->create();
        $this->eventInHours(24, 27)->bosses()->attach($boss->id, ['sort_order' => 1]);

        $counted = Boss::withUsageCounts()->find($boss->id);

        $this->assertSame(2, $counted->items_count);
        $this->assertSame(1, $counted->upcoming_events_count);
        $this->assertSame(0, $counted->upcoming_assignments_count);
        $this->assertSame(0, $counted->upcoming_assignment_groups_count);
    }

    // ==================== model events ====================

    #[Test]
    public function it_dispatches_raiding_data_changed_when_created(): void
    {
        EventFacade::fake([RaidingDataChanged::class]);

        $boss = Boss::factory()->create();

        $this->assertSame(1, $this->dispatchedFor($boss));
    }

    #[Test]
    public function it_dispatches_raiding_data_changed_when_updated(): void
    {
        $boss = Boss::factory()->create();
        EventFacade::fake([RaidingDataChanged::class]);

        $boss->update(['name' => 'Renamed']);

        $this->assertSame(1, $this->dispatchedFor($boss));
    }

    #[Test]
    public function it_dispatches_raiding_data_changed_when_deleted(): void
    {
        $boss = Boss::factory()->create();
        EventFacade::fake([RaidingDataChanged::class]);

        $boss->delete();

        $this->assertSame(1, $this->dispatchedFor($boss));
    }

    #[Test]
    public function it_flushes_the_raiding_cache_after_the_transaction_commits(): void
    {
        $boss = $this->create();
        Cache::tags(['raiding', 'events'])->put('probe', true);

        DB::transaction(function () use ($boss): void {
            $boss->update(['name' => 'Renamed']);

            $this->assertTrue(Cache::tags(['raiding', 'events'])->has('probe'));
        });

        $this->assertFalse(Cache::tags(['raiding', 'events'])->has('probe'));
    }

    // ==================== helpers ====================

    private function eventInHours(int $startsInHours, int $endsInHours): Event
    {
        return Event::factory()->live()->create([
            'start_time' => now()->addHours($startsInHours),
            'end_time' => now()->addHours($endsInHours),
        ]);
    }

    private function dispatchedFor(Model $model): int
    {
        return EventFacade::dispatched(RaidingDataChanged::class, fn (RaidingDataChanged $event): bool => $event->model->is($model))->count();
    }
}
