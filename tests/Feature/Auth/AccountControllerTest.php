<?php

namespace Tests\Feature\Auth;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    // ==================== index: Access Control ====================

    #[Test]
    public function index_requires_authentication(): void
    {
        $response = $this->get(route('account.index'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function index_allows_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertOk();
    }

    // ==================== index: Inertia Response ====================

    #[Test]
    public function index_renders_correct_inertia_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account/Index')
        );
    }

    #[Test]
    public function index_returns_user_roles(): void
    {
        $role = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 6, 'is_visible' => true]
        );
        $user = User::factory()->create();
        $user->discordRoles()->attach($role);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account/Index')
            ->has('roles', 1)
            ->where('roles.0.name', 'Officer')
        );
    }

    #[Test]
    public function index_excludes_hidden_roles(): void
    {
        $visibleRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 6, 'is_visible' => true]
        );
        $hiddenRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816692'],
            ['name' => 'HiddenRole', 'position' => 1, 'is_visible' => false]
        );
        $user = User::factory()->create();
        $user->discordRoles()->attach([$visibleRole->id, $hiddenRole->id]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('roles', 1)
            ->where('roles.0.name', 'Officer')
        );
    }

    #[Test]
    public function index_returns_planned_absences_created_by_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        PlannedAbsence::factory()->withCharacter()->create(['created_by' => $user->id]);
        PlannedAbsence::factory()->withCharacter()->create(['created_by' => $other->id]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account/Index')
            ->has('planned_absences.data', 1)
        );
    }

    #[Test]
    public function index_planned_absences_include_character(): void
    {
        $character = Character::factory()->main()->create(['name' => 'Aragorn']);
        $user = User::factory()->create();
        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'created_by' => $user->id,
            'start_date' => '2026-04-01',
            'reason' => 'Holiday.',
        ]);

        $response = $this->actingAs($user)->get(route('account.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Account/Index')
            ->has('planned_absences.data', 1)
            ->has('planned_absences.data.0', fn (Assert $absence) => $absence
                ->has('id')
                ->has('character')
                ->where('character.name', 'Aragorn')
                ->has('start_date')
                ->has('end_date')
                ->has('reason')
                ->has('created_at')
            )
        );
    }
}
