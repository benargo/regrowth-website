<?php

namespace Tests\Unit\Models\TBC;

use App\Models\TBC\DailyQuest;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class DailyQuestTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return DailyQuest::class;
    }

    #[Test]
    public function it_uses_tbc_daily_quests_table(): void
    {
        $model = new DailyQuest;

        $this->assertSame('tbc_daily_quests', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new DailyQuest;

        $this->assertFillable($model, [
            'name',
            'type',
            'instance',
            'mode',
            'rewards',
        ]);
    }

    #[Test]
    public function it_casts_rewards_as_json(): void
    {
        $model = new DailyQuest;

        $this->assertCasts($model, [
            'rewards' => 'json',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $quest = $this->create([
            'name' => 'Test Quest',
            'type' => 'Cooking',
            'instance' => null,
            'mode' => null,
            'rewards' => [['item_id' => 12345, 'quantity' => 1]],
        ]);

        $this->assertTableHas(['name' => 'Test Quest']);
        $this->assertModelExists($quest);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $quest = $this->create();

        $this->assertNotEmpty($quest->name);
        $this->assertNotNull($quest->type);
        $this->assertNotNull($quest->rewards);
        $this->assertIsArray($quest->rewards);
        $this->assertModelExists($quest);
    }

    #[Test]
    public function factory_cooking_state_creates_cooking_quest(): void
    {
        $quest = $this->factory()->cooking()->create();

        $this->assertSame('Cooking', $quest->type);
        $this->assertNull($quest->instance);
        $this->assertNull($quest->mode);
        $this->assertIsArray($quest->rewards);
    }

    #[Test]
    public function factory_fishing_state_creates_fishing_quest(): void
    {
        $quest = $this->factory()->fishing()->create();

        $this->assertSame('Fishing', $quest->type);
        $this->assertNull($quest->instance);
        $this->assertNull($quest->mode);
    }

    #[Test]
    public function factory_instance_state_creates_instance_quest(): void
    {
        $quest = $this->factory()->instance()->create();

        $this->assertSame('Dungeon', $quest->type);
        $this->assertNotNull($quest->instance);
        $this->assertSame('Normal', $quest->mode);
    }

    #[Test]
    public function factory_heroic_state_creates_heroic_quest(): void
    {
        $quest = $this->factory()->heroic()->create();

        $this->assertSame('Dungeon', $quest->type);
        $this->assertNotNull($quest->instance);
        $this->assertSame('Heroic', $quest->mode);
    }

    #[Test]
    public function factory_pvp_state_creates_pvp_quest(): void
    {
        $quest = $this->factory()->pvp()->create();

        $this->assertSame('PvP', $quest->type);
        $this->assertNotNull($quest->instance);
        $this->assertNull($quest->mode);
    }

    #[Test]
    public function rewards_are_stored_and_retrieved_as_array(): void
    {
        $rewards = [
            ['item_id' => 12345, 'quantity' => 2],
            ['item_id' => 67890, 'quantity' => 1],
        ];

        $quest = $this->create(['rewards' => $rewards]);

        $this->assertIsArray($quest->rewards);
        $this->assertCount(2, $quest->rewards);
        $this->assertEquals($rewards, $quest->rewards);
    }
}
