<?php

namespace Tests\Unit\Models;

use App\Models\Boss;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Tests\Support\ModelTestCase;

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
            'encounter_order',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $raid = Raid::factory()->create();

        $boss = $this->create([
            'name' => 'Prince Malchezaar',
            'raid_id' => $raid->id,
            'encounter_order' => 1,
        ]);

        $this->assertTableHas(['name' => 'Prince Malchezaar']);
        $this->assertModelExists($boss);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $boss = $this->create();

        $this->assertNotEmpty($boss->name);
        $this->assertNotNull($boss->raid_id);
        $this->assertNotNull($boss->encounter_order);
        $this->assertModelExists($boss);
    }

    #[Test]
    public function factory_order_state_sets_encounter_order(): void
    {
        $boss = $this->factory()->order(5)->create();

        $this->assertSame(5, $boss->encounter_order);
    }

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

    #[Test]
    public function it_implements_has_media_interface(): void
    {
        $boss = $this->create();

        $this->assertInstanceOf(HasMedia::class, $boss);
    }

    #[Test]
    public function it_can_add_media(): void
    {
        $boss = $this->create();
        $testFile = storage_path('app/test-image.png');
        file_put_contents($testFile, 'fake image content');

        $boss->addMedia($testFile)->toMediaCollection('default');

        $this->assertNotEmpty($boss->getMedia('default'));
        @unlink($testFile);
    }
}
