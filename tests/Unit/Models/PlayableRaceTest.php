<?php

namespace Tests\Unit\Models;

use App\Enums\Faction;
use App\Models\PlayableRace;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('characters')]
class PlayableRaceTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return PlayableRace::class;
    }

    #[Test]
    public function it_uses_playable_races_table(): void
    {
        $model = new PlayableRace;

        $this->assertSame('playable_races', $model->getTable());
    }

    #[Test]
    public function it_does_not_use_auto_incrementing_primary_key(): void
    {
        $model = new PlayableRace;

        $this->assertSame('id', $model->getKeyName());
        $this->assertFalse($model->getIncrementing());
    }

    #[Test]
    public function it_does_not_have_timestamps(): void
    {
        $model = new PlayableRace;

        $this->assertFalse($model->usesTimestamps());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new PlayableRace;

        $this->assertFillable($model, [
            'id',
            'name',
            'faction',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $model = new PlayableRace;

        $this->assertFillableAttribute($model, [
            'id',
            'name',
            'faction',
        ]);
    }

    #[Test]
    public function it_casts_faction_to_faction_enum(): void
    {
        $model = new PlayableRace;

        $this->assertCasts($model, ['faction' => Faction::class]);
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $playableRace = $this->create(['name' => 'Human']);

        $this->assertTableHas(['name' => 'Human']);
        $this->assertModelExists($playableRace);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $playableRace = $this->create();

        $this->assertNotEmpty($playableRace->name);
        $this->assertModelExists($playableRace);
    }

    #[Test]
    public function it_enforces_unique_name_constraint(): void
    {
        $this->create(['name' => 'Human']);

        $this->assertUniqueConstraint(function () {
            $this->create(['name' => 'Human']);
        });
    }

    // ==================== characters ====================

    #[Test]
    public function characters_returns_has_many_relationship(): void
    {
        $playableRace = new PlayableRace;

        $this->assertInstanceOf(HasMany::class, $playableRace->characters());
    }

    #[Test]
    public function characters_returns_empty_collection_when_none_associated(): void
    {
        $playableRace = $this->create();

        $this->assertCount(0, $playableRace->characters);
    }
}
