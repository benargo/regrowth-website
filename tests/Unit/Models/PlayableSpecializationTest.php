<?php

namespace Tests\Unit\Models;

use App\Contracts\HasBlizzardIcons;
use App\Enums\PlayableSpecRole;
use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\HasMedia;
use Tests\Support\ModelTestCase;

#[Group('characters')]
class PlayableSpecializationTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return PlayableSpecialization::class;
    }

    #[Test]
    public function it_uses_playable_specializations_table(): void
    {
        $model = new PlayableSpecialization;

        $this->assertSame('playable_specializations', $model->getTable());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new PlayableSpecialization;

        $this->assertFillable($model, [
            'playable_class_id',
            'role',
            'name',
        ]);
    }

    #[Test]
    public function it_implements_media_library_contracts(): void
    {
        $model = new PlayableSpecialization;

        $this->assertInstanceOf(HasMedia::class, $model);
        $this->assertInstanceOf(HasBlizzardIcons::class, $model);
    }

    #[Test]
    public function it_casts_role_to_character_role_enum(): void
    {
        $model = new PlayableSpecialization;

        $this->assertCasts($model, [
            'role' => PlayableSpecRole::class,
        ]);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $specialisation = $this->create();

        $this->assertModelExists($specialisation);
        $this->assertNotEmpty($specialisation->name);
        $this->assertInstanceOf(PlayableSpecRole::class, $specialisation->role);
    }

    #[Test]
    public function factory_tank_state_sets_tank_role(): void
    {
        $specialisation = $this->factory()->tank()->create();

        $this->assertSame(PlayableSpecRole::tank, $specialisation->role);
    }

    #[Test]
    public function factory_healer_state_sets_healer_role(): void
    {
        $specialisation = $this->factory()->healer()->create();

        $this->assertSame(PlayableSpecRole::healer, $specialisation->role);
    }

    #[Test]
    public function factory_damage_state_sets_damage_role(): void
    {
        $specialisation = $this->factory()->damage()->create();

        $this->assertSame(PlayableSpecRole::damage, $specialisation->role);
    }

    // playableClass

    #[Test]
    public function playable_class_returns_belongs_to_relationship(): void
    {
        $model = new PlayableSpecialization;

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
    public function characters_returns_belongs_to_many_relationship(): void
    {
        $model = new PlayableSpecialization;

        $this->assertInstanceOf(BelongsToMany::class, $model->characters());
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
        $characters = Character::factory()->count(2)->create();
        $specialisation->characters()->attach($characters->pluck('id'));

        $this->assertCount(2, $specialisation->fresh()->characters);
    }
}
