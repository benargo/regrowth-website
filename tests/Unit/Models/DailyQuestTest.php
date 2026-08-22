<?php

namespace Tests\Unit\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\DailyQuestType;
use App\Enums\Instance;
use App\Models\DailyQuest;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Tests\Support\ModelTestCase;

#[Group('daily-quests')]
class DailyQuestTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DailyQuest::class;
    }

    #[Test]
    public function it_implements_media_library_contracts(): void
    {
        $model = new DailyQuest;

        $this->assertInstanceOf(HasMedia::class, $model);
        $this->assertInstanceOf(HasBlizzardIcons::class, $model);
    }

    #[Test]
    public function it_stores_a_single_blizzard_icon(): void
    {
        Storage::fake('public');

        $quest = DailyQuest::factory()->cooking()->create();

        $quest->addMediaFromString('BINARY')
            ->usingFileName('inv_misc_food_15.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $quest->addMediaFromString('BINARY2')
            ->usingFileName('trade_fishing.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $this->assertCount(1, $quest->getMedia('blizzard_icons'));
        $this->assertSame('trade_fishing.jpg', $quest->getFirstMedia('blizzard_icons')->file_name);
    }

    #[Test]
    public function it_uses_daily_quests_table(): void
    {
        $model = new DailyQuest;

        $this->assertSame('daily_quests', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new DailyQuest;

        $this->assertFillable($model, [
            'name',
            'type',
            'instance',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new DailyQuest;

        $this->assertFillableAttribute($model, [
            'name',
            'type',
            'instance',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new DailyQuest;

        $this->assertCasts($model, [
            'type' => DailyQuestType::class,
            'instance' => Instance::class,
        ]);
    }

    #[Test]
    public function it_hides_timestamps(): void
    {
        $model = new DailyQuest;

        $this->assertHidden($model, [
            'created_at',
            'updated_at',
        ]);
    }

    // ==================== persistence ====================

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $quest = $this->create([
            'name' => 'Test Quest',
            'type' => DailyQuestType::Cooking,
            'instance' => null,
        ]);

        $this->assertTableHas(['name' => 'Test Quest']);
        $this->assertModelExists($quest);
    }

    // ==================== factory states ====================

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $quest = $this->create();

        $this->assertNotEmpty($quest->name);
        $this->assertNotNull($quest->type);
        $this->assertModelExists($quest);
    }

    #[Test]
    public function factory_cooking_state_creates_cooking_quest(): void
    {
        $quest = $this->factory()->cooking()->create();

        $this->assertSame(DailyQuestType::Cooking, $quest->type);
        $this->assertNull($quest->instance);
    }

    #[Test]
    public function factory_fishing_state_creates_fishing_quest(): void
    {
        $quest = $this->factory()->fishing()->create();

        $this->assertSame(DailyQuestType::Fishing, $quest->type);
        $this->assertNull($quest->instance);
    }

    #[Test]
    public function factory_instance_state_creates_instance_quest(): void
    {
        $quest = $this->factory()->instance()->create();

        $this->assertSame(DailyQuestType::Dungeon, $quest->type);
        $this->assertNotNull($quest->instance);
        $this->assertInstanceOf(Instance::class, $quest->instance);
    }

    #[Test]
    public function factory_heroic_state_creates_heroic_quest(): void
    {
        $quest = $this->factory()->heroic()->create();

        $this->assertSame(DailyQuestType::Heroic, $quest->type);
        $this->assertNotNull($quest->instance);
        $this->assertInstanceOf(Instance::class, $quest->instance);
    }

    #[Test]
    public function factory_pvp_state_creates_pvp_quest(): void
    {
        $quest = $this->factory()->pvp()->create();

        $this->assertSame(DailyQuestType::PvP, $quest->type);
        $this->assertNotNull($quest->instance);
    }

    // ==================== rewards relationship ====================

    #[Test]
    public function rewards_relationship_returns_attached_items_with_quantity(): void
    {
        $quest = $this->create();

        $item = Item::factory()->create();

        $quest->rewards()->attach($item->id, ['quantity' => 3]);

        $this->assertCount(1, $quest->rewards);
        $this->assertTrue($quest->rewards->contains($item));
        $this->assertSame(3, (int) $quest->rewards->first()->pivot->quantity);
    }

    // ==================== display name ====================

    #[Test]
    public function display_name_returns_plain_name_for_non_dungeon_quests(): void
    {
        $quest = $this->create(['name' => 'Crocolisks in the City', 'type' => DailyQuestType::Fishing->value, 'instance' => null]);

        $this->assertSame('Crocolisks in the City', $quest->display_name);
    }

    #[Test]
    public function display_name_appends_instance_name_for_dungeon_quests(): void
    {
        $quest = $this->create([
            'name' => 'Wanted: Shadowy Executioner',
            'type' => DailyQuestType::Dungeon->value,
            'instance' => Instance::ShadowLabyrinth->value,
        ]);

        $this->assertSame('Wanted: Shadowy Executioner (Shadow Labyrinth)', $quest->display_name);
    }

    #[Test]
    public function display_name_appends_instance_name_for_heroic_quests(): void
    {
        $quest = $this->create([
            'name' => 'Wanted: Shadowy Executioner',
            'type' => DailyQuestType::Heroic->value,
            'instance' => Instance::ShadowLabyrinth->value,
        ]);

        $this->assertSame('Wanted: Shadowy Executioner (Shadow Labyrinth)', $quest->display_name);
    }
}
