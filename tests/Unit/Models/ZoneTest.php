<?php

namespace Tests\Unit\Models;

use App\Casts\AsDifficultyCollection;
use App\Casts\AsExpansion;
use App\Models\Raids\Report;
use App\Models\Zone;
use App\Services\WarcraftLogs\ValueObjects\DifficultyData;
use App\Services\WarcraftLogs\ValueObjects\ExpansionData;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class ZoneTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Zone::class;
    }

    #[Test]
    public function it_uses_wcl_zones_table(): void
    {
        $model = new Zone;

        $this->assertSame('wcl_zones', $model->getTable());
    }

    #[Test]
    public function it_uses_non_incrementing_integer_id(): void
    {
        $model = new Zone;

        $this->assertSame('id', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
        $this->assertSame('int', $model->getKeyType());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Zone;

        $this->assertFillable($model, [
            'id',
            'name',
            'difficulties',
            'expansion',
            'is_frozen',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $model = new Zone;

        $this->assertCasts($model, [
            'difficulties' => AsDifficultyCollection::class,
            'expansion' => AsExpansion::class,
            'is_frozen' => 'boolean',
        ]);
    }

    #[Test]
    public function it_has_expected_hidden_attributes(): void
    {
        $model = new Zone;

        $this->assertHidden($model, [
            'created_at',
            'updated_at',
        ]);
    }

    #[Test]
    public function it_has_default_is_frozen_of_false(): void
    {
        $model = new Zone;

        $this->assertFalse($model->is_frozen);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $expansion = new ExpansionData(id: 5, name: 'The War Within');
        $difficulties = [new DifficultyData(id: 3, name: 'Normal', sizes: [10, 25])];

        $zone = $this->create([
            'name' => 'Nerub-ar Palace',
            'expansion' => $expansion,
            'difficulties' => $difficulties,
        ]);

        $this->assertTableHas(['name' => 'Nerub-ar Palace']);
        $this->assertModelExists($zone);
    }

    #[Test]
    public function it_casts_difficulties_to_a_collection_of_difficulty_objects(): void
    {
        $difficulties = [
            new DifficultyData(id: 3, name: 'Normal', sizes: [10, 25]),
            new DifficultyData(id: 4, name: 'Heroic', sizes: [10, 25]),
        ];

        $zone = $this->create(['difficulties' => $difficulties]);

        $this->assertInstanceOf(Collection::class, $zone->difficulties);
        $this->assertCount(2, $zone->difficulties);
        $this->assertInstanceOf(DifficultyData::class, $zone->difficulties->first());
        $this->assertSame('Normal', $zone->difficulties->first()->name);
        $this->assertSame('Heroic', $zone->difficulties->last()->name);
    }

    #[Test]
    public function it_casts_expansion_to_an_expansion_object(): void
    {
        $expansion = new ExpansionData(id: 5, name: 'The War Within');

        $zone = $this->create(['expansion' => $expansion]);

        $this->assertInstanceOf(ExpansionData::class, $zone->expansion);
        $this->assertSame(5, $zone->expansion->id);
        $this->assertSame('The War Within', $zone->expansion->name);
    }

    #[Test]
    public function it_casts_is_frozen_to_boolean(): void
    {
        $zone = $this->create(['is_frozen' => true]);

        $this->assertTrue($zone->is_frozen);
    }

    #[Test]
    public function it_has_many_reports(): void
    {
        $zone = $this->create();

        $this->assertInstanceOf(HasMany::class, $zone->reports());
    }

    #[Test]
    public function it_can_have_reports(): void
    {
        $zone = $this->create();
        Report::factory()->count(3)->create(['zone_id' => $zone->id]);

        $this->assertCount(3, $zone->reports);
    }

    #[Test]
    public function reports_returns_empty_collection_when_none_associated(): void
    {
        $zone = $this->create();

        $this->assertCount(0, $zone->reports);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $zone = $this->create();

        $this->assertNotEmpty($zone->name);
        $this->assertInstanceOf(Collection::class, $zone->difficulties);
        $this->assertInstanceOf(ExpansionData::class, $zone->expansion);
        $this->assertModelExists($zone);
    }

    #[Test]
    public function factory_frozen_state_sets_is_frozen_to_true(): void
    {
        $zone = $this->factory()->frozen()->create();

        $this->assertTrue($zone->is_frozen);
    }

    #[Test]
    public function factory_not_frozen_state_sets_is_frozen_to_false(): void
    {
        $zone = $this->factory()->notFrozen()->create();

        $this->assertFalse($zone->is_frozen);
    }

    #[Test]
    public function factory_with_expansion_state_sets_expansion(): void
    {
        $expansion = new ExpansionData(id: 9, name: 'Midnight');

        $zone = $this->factory()->withExpansion($expansion)->create();

        $this->assertSame(9, $zone->expansion->id);
        $this->assertSame('Midnight', $zone->expansion->name);
    }

    #[Test]
    public function factory_with_difficulties_state_sets_difficulties(): void
    {
        $difficulties = [new DifficultyData(id: 14, name: 'Mythic', sizes: [20])];

        $zone = $this->factory()->withDifficulties($difficulties)->create();

        $this->assertCount(1, $zone->difficulties);
        $this->assertSame('Mythic', $zone->difficulties->first()->name);
        $this->assertSame([20], $zone->difficulties->first()->sizes);
    }
}
