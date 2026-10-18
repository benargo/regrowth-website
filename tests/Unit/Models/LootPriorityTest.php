<?php

namespace Tests\Unit\Models;

use App\Enums\LootPriorityType;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\PlayableClass;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('loot')]
class LootPriorityTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return LootPriority::class;
    }

    #[Test]
    public function it_mass_assigns_the_title_type_and_playable_class(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $priority = LootPriority::create([
            'title' => 'Tank',
            'type' => LootPriorityType::ROLE,
            'playable_class_id' => $playableClass->id,
        ]);

        $this->assertTableHas([
            'id' => $priority->id,
            'title' => 'Tank',
            'type' => 'Role',
            'playable_class_id' => $playableClass->id,
        ]);
    }

    #[Test]
    public function it_reads_the_type_back_as_an_enum(): void
    {
        $priority = $this->factory()->spec()->create();

        $this->assertSame(LootPriorityType::SPEC, $priority->fresh()->type);
    }

    #[Test]
    public function it_hides_timestamps_when_serialised(): void
    {
        $priority = $this->create(['title' => 'Healer']);

        $serialised = $priority->toArray();

        $this->assertSame('Healer', $serialised['title']);
        $this->assertArrayNotHasKey('created_at', $serialised);
        $this->assertArrayNotHasKey('updated_at', $serialised);
    }

    // ==================== withoutMemes ====================

    #[Test]
    public function without_memes_excludes_only_meme_priorities(): void
    {
        $role = $this->factory()->role()->create();
        $classType = $this->factory()->classType()->create();
        $spec = $this->factory()->spec()->create();
        $custom = $this->factory()->custom()->create();
        $this->factory()->meme()->create();

        $ids = LootPriority::withoutMemes()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$role->id, $classType->id, $spec->id, $custom->id], $ids);
    }

    // ==================== media ====================

    #[Test]
    public function it_keeps_only_the_latest_blizzard_icon(): void
    {
        Storage::fake('public');
        $priority = $this->create();

        $priority->addMediaFromString('BINARY')
            ->usingFileName('spell_holy_heal.jpg')
            ->toMediaCollection(LootPriority::MEDIA_COLLECTION);
        $priority->addMediaFromString('BINARY2')
            ->usingFileName('spell_holy_renew.jpg')
            ->toMediaCollection(LootPriority::MEDIA_COLLECTION);

        $this->assertCount(1, $priority->getMedia(LootPriority::MEDIA_COLLECTION));
        $this->assertSame('spell_holy_renew.jpg', $priority->getFirstMedia(LootPriority::MEDIA_COLLECTION)->file_name);
    }

    #[Test]
    public function it_serves_its_blizzard_icon_through_the_signed_icons_route(): void
    {
        Storage::fake('public');
        $priority = $this->create();
        $priority->addMediaFromString('BINARY')
            ->usingFileName('spell_holy_heal.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection(LootPriority::MEDIA_COLLECTION);

        $url = $priority->getFirstMediaUrl(LootPriority::MEDIA_COLLECTION);

        $this->assertStringStartsWith(
            route('icons.show', ['size' => 56, 'name' => 'spell_holy_heal.jpg']).'?signature=',
            $url,
        );
    }

    // ==================== items ====================

    #[Test]
    public function items_returns_the_attached_items_with_their_weights(): void
    {
        $priority = $this->create();
        $sword = Item::factory()->create();
        $shield = Item::factory()->create();
        $sword->priorities()->attach($priority->id, ['weight' => 100]);
        $shield->priorities()->attach($priority->id, ['weight' => 75]);

        $weights = $priority->items->pluck('pivot.weight', 'id')->all();

        $this->assertEqualsCanonicalizing([$sword->id => 100, $shield->id => 75], $weights);
    }

    #[Test]
    public function items_records_timestamps_on_the_pivot(): void
    {
        $priority = $this->create();
        $priority->items()->attach(Item::factory()->create(), ['weight' => 100]);

        $pivot = $priority->items->first()->pivot;

        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
    }

    #[Test]
    public function deleting_the_priority_removes_its_item_pivot_rows_but_keeps_the_item(): void
    {
        $priority = $this->create();
        $item = Item::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);

        $priority->delete();

        $this->assertDatabaseMissing('pivot_items_priorities', [
            'item_id' => $item->id,
            'priority_id' => $priority->id,
        ]);
        $this->assertModelExists($item);
    }

    // ==================== playableClass ====================

    #[Test]
    public function playable_class_returns_the_assigned_class(): void
    {
        $playableClass = PlayableClass::factory()->create();
        $priority = $this->factory()->withPlayableClass($playableClass)->create();

        $this->assertTrue($priority->playableClass->is($playableClass));
    }

    #[Test]
    public function playable_class_is_null_when_unassigned(): void
    {
        $priority = $this->create();

        $this->assertNull($priority->playableClass);
    }

    #[Test]
    public function deleting_the_playable_class_deletes_the_priority(): void
    {
        $playableClass = PlayableClass::factory()->create();
        $priority = $this->factory()->withPlayableClass($playableClass)->create();

        $playableClass->delete();

        $this->assertModelMissing($priority);
    }
}
