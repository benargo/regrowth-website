<?php

namespace Tests\Unit\Models;

use App\Enums\CharacterRole;
use App\Models\Character;
use App\Models\CharacterSpecialisation;
use App\Models\PlayableClass;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class CharacterSpecialisationTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return CharacterSpecialisation::class;
    }

    #[Test]
    public function it_uses_character_specialisations_table(): void
    {
        $model = new CharacterSpecialisation;

        $this->assertSame('character_specialisations', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new CharacterSpecialisation;

        $this->assertFillable($model, [
            'playable_class_id',
            'role',
            'name',
        ]);
    }

    #[Test]
    public function it_casts_role_to_character_role_enum(): void
    {
        $model = new CharacterSpecialisation;

        $this->assertCasts($model, [
            'role' => CharacterRole::class,
        ]);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $specialisation = $this->create();

        $this->assertModelExists($specialisation);
        $this->assertNotEmpty($specialisation->name);
        $this->assertInstanceOf(CharacterRole::class, $specialisation->role);
    }

    #[Test]
    public function factory_tank_state_sets_tank_role(): void
    {
        $specialisation = $this->factory()->tank()->create();

        $this->assertSame(CharacterRole::tank, $specialisation->role);
    }

    #[Test]
    public function factory_healer_state_sets_healer_role(): void
    {
        $specialisation = $this->factory()->healer()->create();

        $this->assertSame(CharacterRole::healer, $specialisation->role);
    }

    #[Test]
    public function factory_damage_state_sets_damage_role(): void
    {
        $specialisation = $this->factory()->damage()->create();

        $this->assertSame(CharacterRole::damage, $specialisation->role);
    }

    // playableClass

    #[Test]
    public function playable_class_returns_belongs_to_relationship(): void
    {
        $model = new CharacterSpecialisation;

        $this->assertInstanceOf(BelongsTo::class, $model->playableClass());
    }

    #[Test]
    public function playable_class_returns_associated_playable_class(): void
    {
        $playableClass = PlayableClass::factory()->create();
        $specialisation = $this->create(['playable_class_id' => $playableClass->id]);

        $this->assertTrue($specialisation->playableClass->is($playableClass));
    }

    // characters

    #[Test]
    public function characters_returns_has_many_relationship(): void
    {
        $model = new CharacterSpecialisation;

        $this->assertInstanceOf(HasMany::class, $model->characters());
    }

    #[Test]
    public function characters_returns_empty_collection_when_none_associated(): void
    {
        $specialisation = $this->create();

        $this->assertCount(0, $specialisation->characters);
    }

    #[Test]
    public function characters_returns_associated_characters(): void
    {
        $specialisation = $this->create();
        Character::factory()->count(2)->create(['specialisation_id' => $specialisation->id]);

        $this->assertCount(2, $specialisation->fresh()->characters);
    }
}
