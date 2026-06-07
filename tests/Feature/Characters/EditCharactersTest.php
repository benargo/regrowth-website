<?php

namespace Tests\Feature\Characters;

use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EditCharactersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function officer(): User
    {
        return User::factory()->withPermissions('view-officer-dashboard', 'update-characters')->create();
    }

    private function member(): User
    {
        return User::factory()->withPermissions('view-officer-dashboard')->create();
    }

    private function characterSlug(Character $character): string
    {
        return Str::slug($character->name);
    }

    // =========================================================================
    // Edit
    // =========================================================================

    #[Test]
    public function edit_requires_authentication(): void
    {
        $character = Character::factory()->create();

        $response = $this->get(route('characters.edit', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function edit_requires_update_characters_permission(): void
    {
        $character = Character::factory()->create();
        $user = $this->member();

        $response = $this->actingAs($user)->get(route('characters.edit', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));

        $response->assertForbidden();
    }

    #[Test]
    public function edit_renders_with_class_specs_and_current_attached(): void
    {
        $class = PlayableClass::factory()->create();
        $specs = PlayableSpecialization::factory()->count(3)->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();
        $character->specializations()->attach($specs->first(), ['is_raid_spec' => true]);

        $user = $this->officer();

        $response = $this->actingAs($user)->get(route('characters.edit', [
            'character' => $character,
            'slug' => $this->characterSlug($character),
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Characters/Edit')
            ->has('character')
            ->has('character.specializations', 1)
            ->has('specializations', 3)
        );
    }

    // =========================================================================
    // Update
    // =========================================================================

    #[Test]
    public function update_requires_authentication(): void
    {
        $character = Character::factory()->create();

        $response = $this->patch(route('characters.update', $character), [
            'specialization_ids' => [],
            'raid_specialization_id' => null,
            'is_loot_councillor' => false,
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function update_requires_update_characters_permission(): void
    {
        $character = Character::factory()->create();
        $user = $this->member();

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'specialization_ids' => [],
            'raid_specialization_id' => null,
            'is_loot_councillor' => false,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_attaches_and_detaches_specs(): void
    {
        $class = PlayableClass::factory()->create();
        $specs = PlayableSpecialization::factory()->count(3)->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();
        $character->specializations()->attach($specs->get(0));

        $user = $this->officer();

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'specialization_ids' => [$specs->get(1)->id, $specs->get(2)->id],
            'raid_specialization_id' => null,
            'is_loot_councillor' => false,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseMissing('pivot_character_specializations', [
            'character_id' => $character->id,
            'playable_specialization_id' => $specs->get(0)->id,
        ]);
        $this->assertDatabaseHas('pivot_character_specializations', [
            'character_id' => $character->id,
            'playable_specialization_id' => $specs->get(1)->id,
        ]);
        $this->assertDatabaseHas('pivot_character_specializations', [
            'character_id' => $character->id,
            'playable_specialization_id' => $specs->get(2)->id,
        ]);
    }

    #[Test]
    public function update_sets_raid_spec_via_pivot(): void
    {
        $class = PlayableClass::factory()->create();
        $specs = PlayableSpecialization::factory()->count(2)->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $user = $this->officer();

        $this->actingAs($user)->patch(route('characters.update', $character), [
            'specialization_ids' => [$specs->get(0)->id, $specs->get(1)->id],
            'raid_specialization_id' => $specs->get(0)->id,
            'is_loot_councillor' => false,
        ]);

        $this->assertDatabaseHas('pivot_character_specializations', [
            'character_id' => $character->id,
            'playable_specialization_id' => $specs->get(0)->id,
            'is_raid_spec' => true,
        ]);
        $this->assertDatabaseHas('pivot_character_specializations', [
            'character_id' => $character->id,
            'playable_specialization_id' => $specs->get(1)->id,
            'is_raid_spec' => false,
        ]);
    }

    #[Test]
    public function update_rejects_spec_from_different_class(): void
    {
        $class = PlayableClass::factory()->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $otherClass = PlayableClass::factory()->create();
        $otherSpec = PlayableSpecialization::factory()->for($otherClass, 'playableClass')->create();

        $user = $this->officer();

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'specialization_ids' => [$otherSpec->id],
            'raid_specialization_id' => null,
            'is_loot_councillor' => false,
        ]);

        $response->assertSessionHasErrors('specialization_ids.0');
    }

    #[Test]
    public function update_rejects_raid_spec_not_in_selected(): void
    {
        $class = PlayableClass::factory()->create();
        $specs = PlayableSpecialization::factory()->count(2)->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $user = $this->officer();

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'specialization_ids' => [$specs->get(0)->id],
            'raid_specialization_id' => $specs->get(1)->id,
            'is_loot_councillor' => false,
        ]);

        $response->assertSessionHasErrors('raid_specialization_id');
    }

    #[Test]
    public function update_allows_no_specs(): void
    {
        $class = PlayableClass::factory()->create();
        $spec = PlayableSpecialization::factory()->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();
        $character->specializations()->attach($spec, ['is_raid_spec' => true]);

        $user = $this->officer();

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'specialization_ids' => [],
            'raid_specialization_id' => null,
            'is_loot_councillor' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseEmpty('pivot_character_specializations');
    }

    #[Test]
    public function update_persists_loot_councillor_toggle(): void
    {
        $character = Character::factory()->withPlayableClass()->create(['is_loot_councillor' => false]);

        $user = $this->officer();

        $this->actingAs($user)->patch(route('characters.update', $character), [
            'specialization_ids' => [],
            'raid_specialization_id' => null,
            'is_loot_councillor' => true,
        ]);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_loot_councillor' => true,
        ]);
    }
}
