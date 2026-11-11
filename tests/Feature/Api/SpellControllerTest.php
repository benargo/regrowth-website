<?php

namespace Tests\Feature\Api;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('raiding')]
class SpellControllerTest extends TestCase
{
    use RefreshDatabase;

    protected DiscordRole $editorRole;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->editorRole = DiscordRole::create([
            'id' => '829022020301094923',
            'name' => 'Editor',
            'position' => 2,
            'is_visible' => true, 'grants_permissions' => true,
        ]);
        $this->editorRole->givePermissionTo(Permission::firstOrCreate(['name' => 'edit-datasets', 'guard_name' => 'web']));
    }

    // ─── store() ──────────────────────────────────────────────────────────────

    #[Test]
    public function it_creates_a_spell(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        Passport::actingAs($user);

        $response = $this->postJson(route('api.spells.store'), [
            'name' => 'Avenging Wrath',
            'type' => 'Magic',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('spells', ['name' => 'Avenging Wrath', 'type' => 'Magic']);
        $response->assertJsonPath('name', 'Avenging Wrath');
    }

    #[Group('authorization')]
    #[Test]
    public function it_returns_403_on_store_without_edit_datasets_permission(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user);

        $response = $this->postJson(route('api.spells.store'), [
            'name' => 'Avenging Wrath',
            'type' => 'Magic',
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function it_returns_401_on_store_for_guests(): void
    {
        $response = $this->postJson(route('api.spells.store'), [
            'name' => 'Avenging Wrath',
            'type' => 'Magic',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseMissing('spells', ['name' => 'Avenging Wrath']);
    }

    #[Group('authorization')]
    #[Test]
    public function it_returns_401_on_store_with_only_a_web_session(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        $response = $this->actingAs($user)->postJson(route('api.spells.store'), [
            'name' => 'Avenging Wrath',
            'type' => 'Magic',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseMissing('spells', ['name' => 'Avenging Wrath']);
    }

    #[Group('validation')]
    #[Test]
    public function it_returns_422_when_name_is_missing_on_store(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        Passport::actingAs($user);

        $response = $this->postJson(route('api.spells.store'), [
            'type' => 'Magic',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    #[Group('validation')]
    #[Test]
    public function it_returns_422_when_type_is_invalid_on_store(): void
    {
        $user = User::factory()->create();
        $user->discordRoles()->attach($this->editorRole->id);

        Passport::actingAs($user);

        $response = $this->postJson(route('api.spells.store'), [
            'name' => 'Avenging Wrath',
            'type' => 'InvalidType',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('type');
    }
}
