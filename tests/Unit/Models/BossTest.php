<?php

namespace Tests\Unit\Models;

use App\Models\Boss;
use App\Models\Comment;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\Item;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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
            'raid_id',
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
            'raid_id',
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
}
