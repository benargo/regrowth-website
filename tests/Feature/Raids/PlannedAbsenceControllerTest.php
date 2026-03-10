<?php

namespace Tests\Feature\Raids;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PlannedAbsenceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 6, 'is_visible' => true]
        );
        $memberRole = DiscordRole::firstOrCreate(
            ['id' => '829022020301094922'],
            ['name' => 'Member', 'position' => 3, 'is_visible' => true]
        );

        $viewPermission = Permission::firstOrCreate(['name' => 'view-planned-absences', 'guard_name' => 'web']);
        $createPermission = Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']);
        $updatePermission = Permission::firstOrCreate(['name' => 'update-planned-absences', 'guard_name' => 'web']);

        $deletePermission = Permission::firstOrCreate(['name' => 'delete-planned-absences', 'guard_name' => 'web']);

        $officerRole->givePermissionTo($viewPermission);
        $officerRole->givePermissionTo($createPermission);
        $officerRole->givePermissionTo($updatePermission);
        $officerRole->givePermissionTo($deletePermission);
        $memberRole->givePermissionTo($viewPermission);
    }

    // ==================== index: Access Control ====================

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->get(route('raids.absences.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function index_allows_authenticated_user(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertOk();
    }

    // ==================== index: Inertia Response ====================

    #[Test]
    public function index_renders_correct_inertia_component(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/PlannedAbsences/Index')
        );
    }

    // ==================== index: Deferred Props ====================

    #[Test]
    public function index_returns_empty_collection_when_no_absences_exist(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/PlannedAbsences/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('plannedAbsences.data', 0)
            )
        );
    }

    #[Test]
    public function index_deferred_planned_absences_returns_collection_with_expected_structure(): void
    {
        $user = User::factory()->member()->create();
        PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/PlannedAbsences/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('plannedAbsences.data', 1)
                ->has('plannedAbsences.data.0', fn (Assert $absence) => $absence
                    ->has('id')
                    ->has('character')
                    ->has('start_date')
                    ->has('end_date')
                    ->has('reason')
                    ->has('created_by')
                    ->has('created_at')
                )
            )
        );
    }

    #[Test]
    public function index_orders_absences_by_start_date(): void
    {
        $user = User::factory()->member()->create();

        $later = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDays(5),
        ]);
        $sooner = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->get(route('raids.absences.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('plannedAbsences.data', 2)
                ->where('plannedAbsences.data.0.id', $sooner->id)
                ->where('plannedAbsences.data.1.id', $later->id)
            )
        );
    }

    // ==================== create: Access Control ====================

    #[Test]
    public function create_requires_authentication(): void
    {
        $response = $this->get(route('raids.absences.create'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function create_returns_403_without_create_permission(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertForbidden();
    }

    #[Test]
    public function create_allows_user_with_create_permission(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertOk();
    }

    // ==================== create: Inertia Response ====================

    #[Test]
    public function create_renders_correct_inertia_component(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/PlannedAbsences/Form')
        );
    }

    #[Test]
    public function create_passes_only_main_characters_to_page(): void
    {
        $main = Character::factory()->create(['is_main' => true]);
        Character::factory()->create(['is_main' => false]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/PlannedAbsences/Form')
            ->has('characters.data', 1)
            ->where('characters.data.0.id', $main->id)
        );
    }

    // ==================== create: action prop ====================

    #[Test]
    public function create_passes_action_url_to_page(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('action', route('raids.absences.store'))
        );
    }

    // ==================== edit: Access Control ====================

    #[Test]
    public function edit_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->get(route('raids.absences.edit', $absence));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function edit_requires_authorization(): void
    {
        $member = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($member)->get(route('raids.absences.edit', $absence));

        $response->assertForbidden();
    }

    #[Test]
    public function edit_allows_owner_without_update_permission(): void
    {
        $owner = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $owner->id]);

        $response = $this->actingAs($owner)->get(route('raids.absences.edit', $absence));

        $response->assertOk();
    }

    #[Test]
    public function edit_allows_user_with_update_permission(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertOk();
    }

    // ==================== edit: Inertia Response ====================

    #[Test]
    public function edit_renders_correct_inertia_component(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/PlannedAbsences/Form')
        );
    }

    #[Test]
    public function edit_passes_planned_absence_to_page(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-07',
            'reason' => 'On holiday.',
        ]);

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/PlannedAbsences/Form')
            ->has('plannedAbsence.data', fn (Assert $data) => $data
                ->where('id', $absence->id)
                ->has('character')
                ->where('start_date', '2026-06-01')
                ->where('end_date', '2026-06-07')
                ->where('reason', 'On holiday.')
                ->has('created_by')
                ->has('created_at')
            )
        );
    }

    #[Test]
    public function edit_passes_only_main_characters_to_page(): void
    {
        $main = Character::factory()->create(['is_main' => true]);
        Character::factory()->create(['is_main' => false]);

        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->create(['character_id' => $main->id, 'created_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('characters.data', 1)
            ->where('characters.data.0.id', $main->id)
        );
    }

    #[Test]
    public function edit_passes_action_url_to_page(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->get(route('raids.absences.edit', $absence));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('action', route('raids.absences.update', $absence))
        );
    }

    // ==================== store: Access Control ====================

    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->postJson(route('raids.absences.store'));

        $response->assertUnauthorized();
    }

    #[Test]
    public function store_requires_authorization(): void
    {
        $user = User::factory()->member()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => '2026-04-01',
            'reason' => 'Going on holiday.',
        ]);

        $response->assertForbidden();
    }

    // ==================== store: Happy Paths ====================

    #[Test]
    public function store_creates_absence_with_character_id(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-07',
            'reason' => 'Going on holiday.',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id', 'character', 'start_date', 'end_date', 'reason', 'created_by']]);
        $response->assertJsonPath('data.character.id', $character->id);
        $response->assertJsonPath('data.start_date', '2026-04-01');
        $response->assertJsonPath('data.end_date', '2026-04-07');
        $this->assertDatabaseHas('planned_absences', [
            'character_id' => $character->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function store_creates_absence_with_character_name(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create(['name' => 'Aragorn']);

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Aragorn',
            'start_date' => '2026-04-01',
            'reason' => 'Scouting the Misty Mountains.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.character.id', $character->id);
    }

    #[Test]
    public function store_creates_absence_with_name_matching_diacritics(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create(['name' => 'Déo']);

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Deo',
            'start_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.character.id', $character->id);
    }

    #[Test]
    public function store_creates_absence_without_end_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => '2026-04-01',
            'reason' => 'Indefinite absence.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.end_date', null);
    }

    // ==================== store: Special Responses ====================

    #[Test]
    public function store_returns_300_when_multiple_characters_match_name(): void
    {
        $user = User::factory()->officer()->create();
        Character::factory()->main()->create(['name' => 'Déo']);
        Character::factory()->main()->create(['name' => 'Deò']);

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Deo',
            'start_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertStatus(300);
        $response->assertJsonStructure(['message', 'characters' => [['id', 'name']]]);
        $this->assertCount(2, $response->json('characters'));
    }

    #[Test]
    public function store_returns_400_when_character_is_not_main(): void
    {
        $user = User::factory()->officer()->create();
        $mainCharacter = Character::factory()->main()->create(['name' => 'Mainchar']);
        $altCharacter = Character::factory()->create(['name' => 'Altchar', 'is_main' => false]);
        DB::table('character_links')->insert([
            'linked_character_id' => $altCharacter->id,
            'character_id' => $mainCharacter->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Altchar',
            'start_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'The specified character is not a main character.');
        $response->assertJsonPath('suggestion', 'Mainchar');
    }

    #[Test]
    public function store_returns_400_with_null_suggestion_when_no_main_exists(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create(['name' => 'Altchar', 'is_main' => false]);

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('suggestion', null);
    }

    // ==================== store: Validation ====================

    #[Test]
    public function store_requires_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'start_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    #[Test]
    public function store_requires_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_date']);
    }

    #[Test]
    public function store_requires_reason(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => '2026-04-01',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['reason']);
    }

    #[Test]
    public function store_validates_end_date_must_be_after_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->main()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => $character->id,
            'start_date' => '2026-04-07',
            'end_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function store_validates_character_name_max_length(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'TwelveCharsX',
            'start_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    #[Test]
    public function store_validates_character_name_no_spaces(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Two Words',
            'start_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    #[Test]
    public function store_validates_character_name_no_numbers(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->postJson(route('raids.absences.store'), [
            'character' => 'Char1',
            'start_date' => '2026-04-01',
            'reason' => 'Away for a week.',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    // ==================== update: Access Control ====================

    #[Test]
    public function update_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->patchJson(route('raids.absences.update', $absence));

        $response->assertUnauthorized();
    }

    #[Test]
    public function update_requires_authorization(): void
    {
        $member = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($member)->patchJson(route('raids.absences.update', $absence), [
            'reason' => 'Updated reason.',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_forbids_character_change_without_update_permission(): void
    {
        $owner = User::factory()->member()->create();
        $newCharacter = Character::factory()->main()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $owner->id]);

        $response = $this->actingAs($owner)->patchJson(route('raids.absences.update', $absence), [
            'character' => $newCharacter->id,
        ]);

        $response->assertForbidden();
    }

    // ==================== update: Happy Paths ====================

    #[Test]
    public function update_updates_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['start_date' => '2026-04-01']);

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'start_date' => '2026-05-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'start_date' => '2026-05-01 00:00:00',
        ]);
    }

    #[Test]
    public function update_updates_end_date(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-07',
        ]);

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'end_date' => '2026-04-14',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'end_date' => '2026-04-14 00:00:00',
        ]);
    }

    #[Test]
    public function update_updates_reason(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['reason' => 'Old reason.']);

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'reason' => 'New reason.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'reason' => 'New reason.',
        ]);
    }

    #[Test]
    public function update_updates_character_with_permission(): void
    {
        $user = User::factory()->officer()->create();
        $originalCharacter = Character::factory()->main()->create();
        $newCharacter = Character::factory()->main()->create();
        $absence = PlannedAbsence::factory()->create([
            'character_id' => $originalCharacter->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'character' => $newCharacter->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'character_id' => $newCharacter->id,
        ]);
    }

    #[Test]
    public function update_can_set_end_date_to_null(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-07',
        ]);

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'end_date' => null,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'end_date' => null,
        ]);
    }

    #[Test]
    public function update_owner_can_update_non_character_fields_without_permission(): void
    {
        $owner = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create([
            'created_by' => $owner->id,
            'reason' => 'Old reason.',
        ]);

        $response = $this->actingAs($owner)->patchJson(route('raids.absences.update', $absence), [
            'reason' => 'Updated reason.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('planned_absences', [
            'id' => $absence->id,
            'reason' => 'Updated reason.',
        ]);
    }

    // ==================== update: Special Responses ====================

    #[Test]
    public function update_returns_400_when_character_is_not_main(): void
    {
        $user = User::factory()->officer()->create();
        $altCharacter = Character::factory()->create(['is_main' => false]);
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'character' => $altCharacter->id,
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('message', 'The specified character is not a main character.');
    }

    // ==================== update: Validation ====================

    #[Test]
    public function update_validates_end_date_must_be_after_start_date(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['start_date' => '2026-04-07']);

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'end_date' => '2026-04-01',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function update_validates_character_must_exist(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->patchJson(route('raids.absences.update', $absence), [
            'character' => 99999,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['character']);
    }

    // ==================== destroy: Access Control ====================

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertUnauthorized();
    }

    #[Test]
    public function destroy_requires_authorization(): void
    {
        $member = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($member)->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertForbidden();
    }

    // ==================== destroy: Happy Paths ====================

    #[Test]
    public function destroy_deletes_absence_with_permission(): void
    {
        $user = User::factory()->officer()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create();

        $response = $this->actingAs($user)->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertRedirect();
        $this->assertSoftDeleted('planned_absences', ['id' => $absence->id]);
    }

    #[Test]
    public function destroy_owner_can_delete_their_own_absence(): void
    {
        $owner = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $owner->id]);

        $response = $this->actingAs($owner)->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertRedirect();
        $this->assertSoftDeleted('planned_absences', ['id' => $absence->id]);
    }

    #[Test]
    public function destroy_owner_cannot_delete_another_users_absence(): void
    {
        $owner = User::factory()->member()->create();
        $other = User::factory()->member()->create();
        $absence = PlannedAbsence::factory()->withCharacter()->create(['created_by' => $other->id]);

        $response = $this->actingAs($owner)->deleteJson(route('raids.absences.destroy', $absence));

        $response->assertForbidden();
        $this->assertDatabaseHas('planned_absences', ['id' => $absence->id]);
    }
}
