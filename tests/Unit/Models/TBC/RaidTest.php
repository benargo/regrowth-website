<?php

namespace Tests\Unit\Models\TBC;

use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class RaidTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Raid::class;
    }

    #[Test]
    public function it_uses_tbc_raids_table(): void
    {
        $model = new Raid;

        $this->assertSame('tbc_raids', $model->getTable());
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
            'phase_id',
            'max_players',
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
        ]);

        $this->assertTableHas(['name' => 'Karazhan']);
        $this->assertModelExists($raid);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $raid = $this->create();

        $this->assertNotEmpty($raid->name);
        $this->assertNotEmpty($raid->difficulty);
        $this->assertNotNull($raid->phase_id);
        $this->assertNotNull($raid->max_players);
        $this->assertModelExists($raid);
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
    public function it_belongs_to_a_phase(): void
    {
        $phase = Phase::factory()->create();
        $raid = $this->create(['phase_id' => $phase->id]);

        $this->assertRelation($raid, 'phase', BelongsTo::class);
        $this->assertTrue($raid->phase->is($phase));
    }

    #[Test]
    public function it_has_many_bosses(): void
    {
        $raid = $this->create();
        Boss::factory()->count(3)->create(['raid_id' => $raid->id]);

        $this->assertRelation($raid, 'bosses', HasMany::class);
        $this->assertCount(3, $raid->bosses);
    }
}
