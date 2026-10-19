<?php

namespace Tests\Unit\Models;

use App\Casts\AsBinaryColor;
use App\Contracts\Events\FlushesRaidingCache;
use App\Contracts\Models\BelongsToGameVersion;
use App\Contracts\Models\DatasetModel;
use App\Events\RaidChanged;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\Event;
use App\Models\EventRaid;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use App\Policies\DatasetPolicy;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use ReflectionClass;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
class RaidTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Raid::class;
    }

    #[Test]
    public function it_uses_raids_table(): void
    {
        $model = new Raid;

        $this->assertSame('raids', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new Raid;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Raid;

        $this->assertFillable($model, [
            'name',
            'difficulty',
            'color',
            'phase_id',
            'max_players',
            'max_loot_councillors',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new Raid;

        $this->assertFillableAttribute($model, [
            'name',
            'difficulty',
            'color',
            'phase_id',
            'max_players',
            'max_loot_councillors',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Raid;

        $this->assertCasts($model, [
            'color' => AsBinaryColor::class,
            'max_players' => 'integer',
            'max_loot_councillors' => 'integer',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $phase = Phase::factory()->create();

        $raid = $this->create([
            'name' => 'Karazhan',
            'difficulty' => 'Normal',
            'phase_id' => $phase->id,
            'max_players' => 10,
            'max_loot_councillors' => 3,
        ]);

        $this->assertTableHas(['name' => 'Karazhan']);
        $this->assertModelExists($raid);
    }

    // ==================== color ====================

    #[Test]
    public function color_is_nullable(): void
    {
        $raid = $this->create(['color' => null]);

        $this->assertNull($raid->color);
        $this->assertModelExists($raid);
    }

    #[Test]
    public function color_getter_returns_hex_string(): void
    {
        $raid = $this->create(['color' => '8b7ed0']);

        $this->assertSame('8b7ed0', $raid->color);
    }

    #[Test]
    public function color_setter_accepts_hex_string_without_hash(): void
    {
        $raid = $this->create(['color' => '8b7ed0']);

        $this->assertSame('8b7ed0', $raid->color);
    }

    #[Test]
    public function color_setter_accepts_hex_string_with_hash(): void
    {
        $raid = $this->create(['color' => '#8b7ed0']);

        $this->assertSame('8b7ed0', $raid->color);
    }

    #[Test]
    public function color_setter_accepts_integer_hex_literal(): void
    {
        $raid = $this->create(['color' => 0x8B7ED0]);

        $this->assertSame('8b7ed0', $raid->color);
    }

    #[Group('error-handling')]
    #[Test]
    public function color_setter_throws_for_invalid_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->create(['color' => 'not-a-color']);
    }

    // ==================== max_players ====================

    #[Test]
    public function max_players_is_nullable(): void
    {
        $raid = $this->create(['max_players' => null]);

        $this->assertNull($raid->max_players);
        $this->assertModelExists($raid);
    }

    // ==================== max_loot_councillors ====================

    #[Test]
    public function max_loot_councillors_is_nullable(): void
    {
        $raid = $this->create(['max_loot_councillors' => null]);

        $this->assertNull($raid->max_loot_councillors);
        $this->assertModelExists($raid);
    }

    // ==================== max_groups ====================

    #[Test]
    public function it_calculates_max_groups_from_max_players(): void
    {
        $raid = $this->create(['max_players' => 20]);

        $this->assertSame(4, $raid->max_groups);
    }

    #[Test]
    public function ten_player_raid_has_two_max_groups(): void
    {
        $raid = $this->factory()->tenPlayer()->create();

        $this->assertSame(2, $raid->max_groups);
    }

    #[Test]
    public function twenty_five_player_raid_has_five_max_groups(): void
    {
        $raid = $this->factory()->twentyFivePlayer()->create();

        $this->assertSame(5, $raid->max_groups);
    }

    #[Test]
    public function max_groups_rounds_up_for_non_divisible_player_counts(): void
    {
        $raid = $this->create(['max_players' => 11]);

        $this->assertSame(3, $raid->max_groups);
    }

    #[Test]
    public function max_groups_is_not_persisted_to_the_database(): void
    {
        $raid = $this->create(['max_players' => 10]);

        $this->assertArrayNotHasKey('max_groups', $raid->getAttributes());
        $this->assertSame(2, $raid->max_groups);
    }

    // ==================== slug ====================

    #[Test]
    public function it_generates_a_slug_from_the_name(): void
    {
        $raid = $this->create(['name' => 'Serpentshrine Cavern']);

        $this->assertSame('serpentshrine-cavern', $raid->slug);
    }

    #[Test]
    public function it_generates_a_slug_with_special_characters_removed(): void
    {
        $raid = $this->create(['name' => "Magtheridon's Lair"]);

        $this->assertSame('magtheridons-lair', $raid->slug);
    }

    #[Test]
    public function slug_is_not_persisted_to_the_database(): void
    {
        $raid = $this->create(['name' => 'Karazhan']);

        $this->assertArrayNotHasKey('slug', $raid->getAttributes());
        $this->assertSame('karazhan', $raid->slug);
    }

    // ==================== factory ====================

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $raid = $this->create();

        $this->assertNotEmpty($raid->name);
        $this->assertNotEmpty($raid->difficulty);
        $this->assertNotNull($raid->phase_id);
        $this->assertModelExists($raid);
    }

    #[Test]
    public function factory_with_loot_councillors_state_sets_max_loot_councillors(): void
    {
        $raid = $this->factory()->withLootCouncillors(5)->create();

        $this->assertSame(5, $raid->max_loot_councillors);
    }

    #[Test]
    public function factory_ten_player_state_sets_max_players_to_ten(): void
    {
        $raid = $this->factory()->tenPlayer()->create();

        $this->assertSame(10, $raid->max_players);
    }

    #[Test]
    public function factory_twenty_five_player_state_sets_max_players_to_twenty_five(): void
    {
        $raid = $this->factory()->twentyFivePlayer()->create();

        $this->assertSame(25, $raid->max_players);
    }

    #[Test]
    public function factory_normal_state_sets_difficulty_to_normal(): void
    {
        $raid = $this->factory()->normal()->create();

        $this->assertSame('Normal', $raid->difficulty);
    }

    #[Test]
    public function factory_heroic_state_sets_difficulty_to_heroic(): void
    {
        $raid = $this->factory()->heroic()->create();

        $this->assertSame('Heroic', $raid->difficulty);
    }

    #[Test]
    public function factory_with_bosses_state_creates_bosses(): void
    {
        $raid = $this->factory()->withBosses(3)->create();

        $this->assertCount(3, $raid->bosses);
        $this->assertTrue($raid->bosses->every(fn (Boss $boss) => $boss->raid_id === $raid->id));
    }

    #[Test]
    public function factory_with_comments_state_creates_comments_through_items(): void
    {
        $raid = $this->factory()->withComments(2)->create();

        $this->assertCount(1, $raid->items);
        $this->assertCount(2, $raid->comments);
    }

    // ==================== phase ====================

    #[Test]
    public function it_belongs_to_a_phase(): void
    {
        $phase = Phase::factory()->create();
        $raid = $this->create(['phase_id' => $phase->id]);

        $this->assertRelation($raid, 'phase', BelongsTo::class);
        $this->assertTrue($raid->phase->is($phase));
    }

    // ==================== bosses ====================

    #[Test]
    public function it_has_many_bosses(): void
    {
        $raid = $this->create();
        Boss::factory()->count(3)->create(['raid_id' => $raid->id]);

        $this->assertRelation($raid, 'bosses', HasMany::class);
        $this->assertCount(3, $raid->bosses);
    }

    #[Test]
    public function bosses_are_ordered_by_sort_order(): void
    {
        $raid = $this->create();

        // Created out of encounter order so id order and sort order differ.
        $second = Boss::factory()->for($raid)->order(2)->create();
        $third = Boss::factory()->for($raid)->order(3)->create();
        $first = Boss::factory()->for($raid)->order(1)->create();

        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $raid->bosses->pluck('id')->all()
        );
    }

    // ==================== items ====================

    #[Test]
    public function it_belongs_to_many_items(): void
    {
        $raid = $this->factory()->withItems(2)->create();

        $this->assertRelation($raid, 'items', BelongsToMany::class);
        $this->assertCount(2, $raid->items);
        $this->assertInstanceOf(Item::class, $raid->items->first());
    }

    #[Test]
    public function it_has_many_trash_items(): void
    {
        $raid = $this->factory()->withItems(2)->create();
        Item::factory()->fromBoss(Boss::factory()->create(['raid_id' => $raid->id]))->create();

        $this->assertRelation($raid, 'trashItems', BelongsToMany::class);
        $this->assertCount(2, $raid->trashItems);
        $this->assertTrue($raid->trashItems->every(fn (Item $item) => $item->boss_id === null));
    }

    #[Test]
    public function factory_with_items_state_creates_trash_items(): void
    {
        $raid = $this->factory()->withItems(3)->create();

        $this->assertCount(3, $raid->items);
        $this->assertTrue($raid->items->every(fn (Item $item) => $item->boss_id === null));
    }

    #[Test]
    public function an_item_can_belong_to_two_raids(): void
    {
        $hyjal = $this->create(['name' => 'Hyjal Summit']);
        $blackTemple = $this->create(['name' => 'Black Temple']);
        $item = Item::factory()->trashDrop()->create();

        $item->raids()->attach([$hyjal->id, $blackTemple->id]);

        $this->assertCount(1, $hyjal->trashItems);
        $this->assertCount(1, $blackTemple->trashItems);
        $this->assertSame($item->id, $hyjal->trashItems->first()->id);
        $this->assertSame($item->id, $blackTemple->trashItems->first()->id);
    }

    // ==================== comments ====================

    #[Test]
    public function it_has_many_comments_through_its_items(): void
    {
        $raid = $this->factory()->withComments(2)->create();

        $this->assertRelation($raid, 'comments', HasMany::class);
        $this->assertCount(2, $raid->comments);
        $this->assertInstanceOf(Comment::class, $raid->comments->first());
    }

    #[Test]
    public function comments_are_scoped_to_item_commentable_type(): void
    {
        $raid = $this->factory()->withComments(2)->create();
        $item = $raid->items->first();

        // Insert a comment with a different commentable_type pointing at the same item ID
        Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => User::class,
        ]);

        $this->assertCount(2, $raid->comments);
    }

    // ==================== events ====================

    #[Test]
    public function events_returns_belongs_to_many_relationship(): void
    {
        $model = new Raid;

        $this->assertInstanceOf(BelongsToMany::class, $model->events());
    }

    #[Test]
    public function it_can_attach_events(): void
    {
        $raid = $this->create();
        $event = Event::factory()->create();

        $raid->events()->attach($event->id);

        $this->assertCount(1, $raid->events);
        $this->assertSame($event->id, $raid->events->first()->id);
    }

    #[Test]
    public function events_returns_empty_collection_when_none_attached(): void
    {
        $raid = $this->create();

        $this->assertCount(0, $raid->events);
    }

    #[Test]
    public function events_use_the_event_raid_pivot(): void
    {
        $raid = $this->create();
        $event = Event::factory()->create();

        $raid->events()->attach($event->id);

        $pivot = $raid->events->first()->pivot;

        $this->assertInstanceOf(EventRaid::class, $pivot);
        $this->assertNotNull($pivot->sort_order);
    }

    // ==================== dataset ====================

    #[Test]
    public function it_is_a_dataset_model_governed_by_the_dataset_policy(): void
    {
        $this->assertInstanceOf(DatasetModel::class, new Raid);

        $attributes = (new ReflectionClass(Raid::class))->getAttributes(UsePolicy::class);

        $this->assertNotEmpty($attributes);
        $this->assertSame(DatasetPolicy::class, $attributes[0]->newInstance()->class);
    }

    #[Test]
    public function it_keys_the_edit_lock_cache_entries_under_the_raids_prefix(): void
    {
        $raid = $this->create();
        $officer = User::factory()->officer()->create();

        $raid->acquireEditLock($officer);

        $this->assertSame($officer->id, Cache::get("raids.{$raid->id}.editor"));
        $this->assertTrue(Cache::lock("raids.{$raid->id}.editing")->isLocked());
    }

    #[Test]
    #[TestWith(['heroic', 'Heroic'])]
    #[TestWith(['mythic plus', 'Mythic Plus'])]
    public function it_stores_the_difficulty_in_title_case(string $given, string $stored): void
    {
        $raid = $this->create(['difficulty' => $given]);

        $this->assertSame($stored, $raid->fresh()->difficulty);
    }

    // ==================== usage ====================

    #[Test]
    public function it_is_not_in_use_without_bosses_or_upcoming_events(): void
    {
        $this->assertFalse($this->create()->isInUse());
    }

    #[Test]
    public function it_is_in_use_while_it_has_bosses(): void
    {
        $raid = $this->create();
        Boss::factory()->for($raid)->create();

        $this->assertTrue($raid->isInUse());
    }

    #[Test]
    public function it_is_in_use_while_an_upcoming_event_includes_it(): void
    {
        $this->freezeTime();
        $raid = $this->create();
        $this->attachEvent($raid, now()->addDay(), now()->addDay()->addHours(3));

        $this->assertTrue($raid->isInUse());
    }

    #[Test]
    public function it_is_in_use_while_an_event_that_includes_it_is_in_progress(): void
    {
        $this->freezeTime();
        $raid = $this->create();
        $this->attachEvent($raid, now()->subHour(), now()->addHour());

        $this->assertTrue($raid->isInUse());
    }

    #[Test]
    public function it_is_not_in_use_with_only_past_events_and_items(): void
    {
        $this->freezeTime();
        $raid = $this->factory()->withItems(1)->create();
        $this->attachEvent($raid, now()->subDays(2), now()->subDays(2)->addHours(3));

        $this->assertFalse($raid->isInUse());
    }

    #[Test]
    public function with_usage_counts_counts_bosses_and_upcoming_events(): void
    {
        $this->freezeTime();
        $raid = $this->create();
        Boss::factory()->for($raid)->count(2)->create();
        $this->attachEvent($raid, now()->addDay(), now()->addDay()->addHours(3));
        $this->attachEvent($raid, now()->subDay(), now()->subDay()->addHours(3));

        $counted = Raid::withUsageCounts()->find($raid->id);

        $this->assertSame(2, $counted->bosses_count);
        $this->assertSame(1, $counted->upcoming_events_count);
    }

    // ==================== event timing ====================

    #[Test]
    public function past_events_are_the_events_that_have_ended(): void
    {
        $this->freezeTime();
        $raid = $this->create();
        $past = $this->attachEvent($raid, now()->subDays(2), now()->subDays(2)->addHours(3));
        $this->attachEvent($raid, now()->subHour(), now()->addHour());

        $this->assertSame([$past->id], $raid->pastEvents()->pluck('events.id')->all());
    }

    #[Test]
    public function upcoming_events_include_events_still_in_progress(): void
    {
        $this->freezeTime();
        $raid = $this->create();
        $this->attachEvent($raid, now()->subDays(2), now()->subDays(2)->addHours(3));
        $inProgress = $this->attachEvent($raid, now()->subHour(), now()->addHour());
        $upcoming = $this->attachEvent($raid, now()->addDay(), now()->addDay()->addHours(3));

        $this->assertEqualsCanonicalizing(
            [$inProgress->id, $upcoming->id],
            $raid->upcomingEvents()->pluck('events.id')->all(),
        );
    }

    #[Test]
    public function upcoming_events_include_an_event_ending_now(): void
    {
        $this->freezeTime();
        $raid = $this->create();
        $endingNow = $this->attachEvent($raid, now()->subHours(3), now());

        $this->assertSame([$endingNow->id], $raid->upcomingEvents()->pluck('events.id')->all());
        $this->assertSame([], $raid->pastEvents()->pluck('events.id')->all());
    }

    #[Test]
    public function an_event_template_is_neither_past_nor_upcoming_and_does_not_mark_the_raid_in_use(): void
    {
        $this->freezeTime();
        $raid = $this->create();
        $template = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);
        $raid->events()->attach($template->id, ['sort_order' => 1]);

        $this->assertSame([$template->id], $raid->templateEvents()->pluck('events.id')->all());
        $this->assertSame([], $raid->pastEvents()->pluck('events.id')->all());
        $this->assertSame([], $raid->upcomingEvents()->pluck('events.id')->all());
        $this->assertFalse($raid->isInUse());
    }

    // ==================== game version ====================

    #[Test]
    public function in_game_version_keeps_the_raids_whose_phase_belongs_to_the_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $inVersion = Raid::factory()->for(Phase::factory()->for($gameVersion))->create();
        Raid::factory()->for(Phase::factory()->for(GameVersion::factory()))->create();
        Raid::factory()->for(Phase::factory()->state(['game_version_id' => null]))->create();

        $this->assertSame([$inVersion->id], Raid::inGameVersion($gameVersion)->pluck('id')->all());
    }

    #[Test]
    public function in_game_version_without_a_version_keeps_the_raids_whose_phase_has_none(): void
    {
        Raid::factory()->for(Phase::factory()->for(GameVersion::factory()))->create();
        $unowned = Raid::factory()->for(Phase::factory()->state(['game_version_id' => null]))->create();

        $this->assertSame([$unowned->id], Raid::inGameVersion(null)->pluck('id')->all());
    }

    #[Test]
    public function it_belongs_to_its_phases_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->for($gameVersion))->create();

        $this->assertInstanceOf(BelongsToGameVersion::class, $raid);
        $this->assertSame($gameVersion->id, $raid->game_version_id);
    }

    #[Test]
    public function it_has_no_game_version_when_its_phase_has_none(): void
    {
        $raid = Raid::factory()->for(Phase::factory()->state(['game_version_id' => null]))->create();

        $this->assertNull($raid->game_version_id);
    }

    // ==================== media ====================

    #[Test]
    public function it_lists_the_mime_types_the_banner_accepts(): void
    {
        $this->assertSame(
            ['image/jpeg', 'image/png', 'image/webp'],
            (new Raid)->acceptedMimeTypes(Raid::MEDIA_BANNER),
        );
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function a_new_banner_replaces_the_old_one(): void
    {
        Storage::fake('public');
        $raid = $this->create();

        $raid->addMedia(UploadedFile::fake()->image('old.png'))->toMediaCollection(Raid::MEDIA_BANNER);
        $raid->addMedia(UploadedFile::fake()->image('new.png'))->toMediaCollection(Raid::MEDIA_BANNER);

        $media = $raid->fresh()->getMedia(Raid::MEDIA_BANNER);
        $this->assertCount(1, $media);
        $this->assertSame('new.png', $media->first()->file_name);
    }

    #[Group('laravel-media-library')]
    #[Test]
    #[TestWith(['banner.jpg'])]
    #[TestWith(['banner.png'])]
    #[TestWith(['banner.webp'])]
    public function the_banner_accepts_raster_images(string $fileName): void
    {
        Storage::fake('public');
        $raid = $this->create();

        $raid->addMedia(UploadedFile::fake()->image($fileName))->toMediaCollection(Raid::MEDIA_BANNER);

        $this->assertTrue($raid->fresh()->hasMedia(Raid::MEDIA_BANNER));
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function the_banner_rejects_other_files(): void
    {
        Storage::fake('public');
        $raid = $this->create();

        $this->expectException(FileUnacceptableForCollection::class);

        $raid->addMedia(UploadedFile::fake()->create('banner.pdf', 10, 'application/pdf'))
            ->toMediaCollection(Raid::MEDIA_BANNER);
    }

    #[Test]
    public function banner_url_and_position_are_null_without_a_banner(): void
    {
        $raid = $this->create();

        $this->assertNull($raid->banner_url);
        $this->assertNull($raid->banner_position);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function banner_url_is_the_original_until_the_header_conversion_exists(): void
    {
        Storage::fake('public');
        Queue::fake([PerformConversionsJob::class]);

        $raid = Raid::factory()->withBanner()->create();

        Queue::assertPushed(PerformConversionsJob::class);
        $media = $raid->getFirstMedia(Raid::MEDIA_BANNER);
        $this->assertSame($media->getUrl(), $raid->fresh()->banner_url);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function banner_url_is_the_header_conversion_once_generated(): void
    {
        Storage::fake('public');

        $raid = Raid::factory()->withBanner()->create();

        $media = $raid->getFirstMedia(Raid::MEDIA_BANNER);
        $this->assertTrue($media->fresh()->hasGeneratedConversion(Raid::MEDIA_CONVERSION_HEADER));
        $this->assertSame($media->getUrl(Raid::MEDIA_CONVERSION_HEADER), $raid->fresh()->banner_url);
        $this->assertStringEndsWith('.webp', $raid->fresh()->banner_url);
    }

    #[Group('laravel-media-library')]
    #[Test]
    public function banner_position_reads_the_position_custom_property(): void
    {
        Storage::fake('public');

        $raid = Raid::factory()->withBanner('center bottom')->create();

        $this->assertSame('center bottom', $raid->fresh()->banner_position);
    }

    #[Test]
    public function banner_url_and_position_are_not_serialized(): void
    {
        Storage::fake('public');

        $array = Raid::factory()->withBanner()->create()->fresh()->toArray();

        $this->assertArrayNotHasKey('banner_url', $array);
        $this->assertArrayNotHasKey('banner_position', $array);
    }

    // ==================== model events ====================

    #[Test]
    public function it_dispatches_raid_changed_when_created(): void
    {
        EventFacade::fake([RaidChanged::class]);

        $raid = Raid::factory()->create();

        EventFacade::assertDispatched(RaidChanged::class, fn (RaidChanged $event) => $event->raid->is($raid));
    }

    #[Test]
    public function it_dispatches_raid_changed_when_updated(): void
    {
        $raid = Raid::factory()->create();
        EventFacade::fake([RaidChanged::class]);

        $raid->update(['name' => 'Renamed']);

        EventFacade::assertDispatched(RaidChanged::class, fn (RaidChanged $event) => $event->raid->is($raid));
    }

    #[Test]
    public function it_dispatches_raid_changed_when_deleted(): void
    {
        $raid = Raid::factory()->create();
        EventFacade::fake([RaidChanged::class]);

        $raid->delete();

        EventFacade::assertDispatched(RaidChanged::class, fn (RaidChanged $event) => $event->raid->is($raid));
    }

    #[Group('contract')]
    #[Test]
    public function raid_changed_flushes_the_raiding_cache_after_commit(): void
    {
        $event = new RaidChanged(new Raid);

        $this->assertInstanceOf(FlushesRaidingCache::class, $event);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
    }

    // ==================== helpers ====================

    private function attachEvent(Raid $raid, CarbonInterface $startTime, CarbonInterface $endTime): Event
    {
        $event = Event::factory()->create(['start_time' => $startTime, 'end_time' => $endTime]);
        $raid->events()->attach($event->id, ['sort_order' => 1]);

        return $event;
    }
}
