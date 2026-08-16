<?php

namespace Tests\Feature\Characters;

use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\PlayableSpecialization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('characters')]
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

    // ==================== edit ====================

    #[Group('authorization')]
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

    #[Group('authorization')]
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

    // ==================== update ====================

    #[Group('authorization')]
    #[Test]
    public function update_requires_authentication(): void
    {
        $character = Character::factory()->create();

        $response = $this->patch(route('characters.update', $character), [
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => [],
                'raid_specialization_id' => null,
            ],
        ]);

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function update_requires_update_characters_permission(): void
    {
        $character = Character::factory()->create();
        $user = $this->member();

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => [],
                'raid_specialization_id' => null,
            ],
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
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => [$specs->get(1)->id, $specs->get(2)->id],
                'raid_specialization_id' => null,
            ],
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
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => [$specs->get(0)->id, $specs->get(1)->id],
                'raid_specialization_id' => $specs->get(0)->id,
            ],
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

    #[Group('validation')]
    #[Test]
    public function update_rejects_spec_from_different_class(): void
    {
        $class = PlayableClass::factory()->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $otherClass = PlayableClass::factory()->create();
        $otherSpec = PlayableSpecialization::factory()->for($otherClass, 'playableClass')->create();

        $user = $this->officer();

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => [$otherSpec->id],
                'raid_specialization_id' => null,
            ],
        ]);

        $response->assertSessionHasErrors('specializations.specialization_ids.0');
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_raid_spec_not_in_selected(): void
    {
        $class = PlayableClass::factory()->create();
        $specs = PlayableSpecialization::factory()->count(2)->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $user = $this->officer();

        $response = $this->actingAs($user)->patch(route('characters.update', $character), [
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => [$specs->get(0)->id],
                'raid_specialization_id' => $specs->get(1)->id,
            ],
        ]);

        $response->assertSessionHasErrors('specializations.raid_specialization_id');
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_raid_specialization_id_without_specialization_ids(): void
    {
        $class = PlayableClass::factory()->create();
        $spec = PlayableSpecialization::factory()->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $response = $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'specializations' => [
                'raid_specialization_id' => $spec->id,
            ],
        ]);

        $response->assertSessionHasErrors('specializations.specialization_ids');
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_raid_spec_not_in_selected_even_with_other_fields_present(): void
    {
        $class = PlayableClass::factory()->create();
        $specs = PlayableSpecialization::factory()->count(2)->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();

        $response = $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'is_loot_councillor' => true,
            'specializations' => [
                'specialization_ids' => [$specs->get(0)->id],
                'raid_specialization_id' => $specs->get(1)->id,
            ],
        ]);

        $response->assertSessionHasErrors('specializations.raid_specialization_id');

        $this->assertDatabaseMissing('pivot_character_specializations', [
            'character_id' => $character->id,
            'playable_specialization_id' => $specs->get(1)->id,
            'is_raid_spec' => true,
        ]);
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
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => [],
                'raid_specialization_id' => null,
            ],
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
            'is_loot_councillor' => true,
            'specializations' => [
                'specialization_ids' => [],
                'raid_specialization_id' => null,
            ],
        ]);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_loot_councillor' => true,
        ]);
    }

    #[Group('validation')]
    #[Test]
    public function update_accepts_payload_without_is_loot_councillor(): void
    {
        $character = Character::factory()->withPlayableClass()->create(['is_loot_councillor' => true]);

        $response = $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            // is_loot_councillor intentionally omitted
            'specializations' => [
                'specialization_ids' => [],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_loot_councillor' => true,
        ]);
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_non_boolean_is_loot_councillor(): void
    {
        $character = Character::factory()->withPlayableClass()->create();

        $response = $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'is_loot_councillor' => 'not-a-boolean',
            'specializations' => [
                'specialization_ids' => [],
            ],
        ]);

        $response->assertSessionHasErrors('is_loot_councillor');
    }

    #[Group('validation')]
    #[Test]
    public function update_accepts_payload_without_specializations(): void
    {
        $class = PlayableClass::factory()->create();
        $specialization = PlayableSpecialization::factory()->for($class, 'playableClass')->create();
        $character = Character::factory()->withPlayableClass($class)->create();
        $character->specializations()->sync([$specialization->id => ['is_raid_spec' => true]]);

        $response = $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'is_loot_councillor' => true,
            // specializations intentionally omitted
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pivot_character_specializations', [
            'character_id' => $character->id,
            'playable_specialization_id' => $specialization->id,
        ]);
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_non_array_specialization_ids(): void
    {
        $character = Character::factory()->withPlayableClass()->create();

        $response = $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => 'not-an-array',
            ],
        ]);

        $response->assertSessionHasErrors('specializations.specialization_ids');
    }

    #[Test]
    public function update_flashes_success_message(): void
    {
        $character = Character::factory()->withPlayableClass()->create();

        $response = $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'is_loot_councillor' => true,
        ]);

        $response->assertSessionHas('success');
    }

    #[Group('validation')]
    #[Test]
    public function update_flashes_error_message_on_validation_failure(): void
    {
        $character = Character::factory()->withPlayableClass()->create();

        $response = $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'specializations' => [
                'specialization_ids' => 'not-an-array',
            ],
        ]);

        $response->assertSessionHasErrors('specializations.specialization_ids');
        $response->assertSessionHas('error');
    }

    #[Test]
    public function update_redirects_back_to_the_referring_page(): void
    {
        $character = Character::factory()->withPlayableClass()->create();

        $referer = route('management.addon.settings');

        $response = $this->actingAs($this->officer())
            ->from($referer)
            ->patch(route('characters.update', $character), [
                'is_loot_councillor' => true,
            ]);

        $response->assertRedirect($referer);
    }

    #[Test]
    public function update_does_not_unset_loot_councillor_when_field_is_absent(): void
    {
        $character = Character::factory()->withPlayableClass()->create(['is_loot_councillor' => true]);

        $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'specializations' => [
                'specialization_ids' => [],
            ],
        ]);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_loot_councillor' => true,
        ]);
    }

    #[Test]
    public function update_propagates_loot_councillor_true_to_linked_characters(): void
    {
        $character = Character::factory()->withPlayableClass()->create(['is_loot_councillor' => false]);
        $alt = Character::factory()->create(['is_loot_councillor' => false]);

        $character->linkedCharacters()->attach($alt);
        $alt->linkedCharacters()->attach($character);

        $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'is_loot_councillor' => true,
            'specializations' => [
                'specialization_ids' => [],
            ],
        ]);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_loot_councillor' => true,
        ]);
        $this->assertDatabaseHas('characters', [
            'id' => $alt->id,
            'is_loot_councillor' => true,
        ]);
    }

    #[Test]
    public function update_propagates_loot_councillor_false_to_linked_characters(): void
    {
        $character = Character::factory()->withPlayableClass()->lootCouncillor()->create();
        $alt = Character::factory()->lootCouncillor()->create();

        $character->linkedCharacters()->attach($alt);
        $alt->linkedCharacters()->attach($character);

        $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'is_loot_councillor' => false,
            'specializations' => [
                'specialization_ids' => [],
            ],
        ]);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_loot_councillor' => false,
        ]);
        $this->assertDatabaseHas('characters', [
            'id' => $alt->id,
            'is_loot_councillor' => false,
        ]);
    }

    #[Test]
    public function update_does_not_affect_unlinked_characters(): void
    {
        $character = Character::factory()->withPlayableClass()->create(['is_loot_councillor' => false]);
        $unrelated = Character::factory()->create(['is_loot_councillor' => false]);

        $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'is_loot_councillor' => true,
            'specializations' => [
                'specialization_ids' => [],
            ],
        ]);

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'is_loot_councillor' => true,
        ]);
        $this->assertDatabaseHas('characters', [
            'id' => $unrelated->id,
            'is_loot_councillor' => false,
        ]);
    }

    #[Test]
    public function update_does_not_touch_linked_characters_when_field_is_absent(): void
    {
        $character = Character::factory()->withPlayableClass()->lootCouncillor()->create();
        $alt = Character::factory()->lootCouncillor()->create();

        $character->linkedCharacters()->attach($alt);
        $alt->linkedCharacters()->attach($character);

        $this->actingAs($this->officer())->patch(route('characters.update', $character), [
            'specializations' => [
                'specialization_ids' => [],
            ],
        ]);

        $this->assertDatabaseHas('characters', [
            'id' => $alt->id,
            'is_loot_councillor' => true,
        ]);
    }
}
