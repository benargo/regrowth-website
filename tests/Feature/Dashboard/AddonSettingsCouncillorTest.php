<?php

namespace Tests\Feature\Dashboard;

use App\Models\Character;
use App\Models\User;
use App\Services\WarcraftLogs\GuildTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AddonSettingsCouncillorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock GuildTags to return empty tags by default
        // This prevents API calls during tests that don't specifically test attendance
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')
            ->andReturn(collect())
            ->byDefault();

        $this->app->instance(GuildTags::class, $guildTags);
    }

    // ==========================================
    // Add Councillor Tests
    // ==========================================

    public function test_add_councillor_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 'TestCharacter',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_add_councillor_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 'TestCharacter',
        ]);

        $response->assertForbidden();
    }

    public function test_add_councillor_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 'TestCharacter',
        ]);

        $response->assertForbidden();
    }

    public function test_add_councillor_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 'TestCharacter',
        ]);

        $response->assertForbidden();
    }

    public function test_add_councillor_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertRedirect();
    }

    public function test_add_councillor_sets_character_is_loot_councillor_to_true(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->create(['is_loot_councillor' => false]);

        $this->assertFalse($character->is_loot_councillor);

        $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $character->refresh();

        $this->assertTrue($character->is_loot_councillor);
    }

    public function test_add_councillor_validates_character_name_is_required(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), []);

        $response->assertSessionHasErrors(['character_name']);
    }

    public function test_add_councillor_validates_character_name_must_be_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 12345,
        ]);

        $response->assertSessionHasErrors(['character_name']);
    }

    public function test_add_councillor_validates_character_name_must_exist(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 'NonexistentCharacter',
        ]);

        $response->assertSessionHasErrors(['character_name']);
    }

    public function test_add_councillor_does_not_affect_other_characters(): void
    {
        $user = User::factory()->officer()->create();
        $character1 = Character::factory()->create(['is_loot_councillor' => false]);
        $character2 = Character::factory()->create(['is_loot_councillor' => false]);

        $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character1->name,
        ]);

        $character1->refresh();
        $character2->refresh();

        $this->assertTrue($character1->is_loot_councillor);
        $this->assertFalse($character2->is_loot_councillor);
    }

    // ==========================================
    // Remove Councillor Tests
    // ==========================================

    public function test_remove_councillor_requires_authentication(): void
    {
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect('/login');
    }

    public function test_remove_councillor_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    public function test_remove_councillor_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    public function test_remove_councillor_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    public function test_remove_councillor_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect();
    }

    public function test_remove_councillor_sets_character_is_loot_councillor_to_false(): void
    {
        $user = User::factory()->officer()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $this->assertTrue($character->is_loot_councillor);

        $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $character->refresh();

        $this->assertFalse($character->is_loot_councillor);
    }

    public function test_remove_councillor_does_not_affect_other_characters(): void
    {
        $user = User::factory()->officer()->create();
        $character1 = Character::factory()->lootCouncillor()->create();
        $character2 = Character::factory()->lootCouncillor()->create();

        $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character1));

        $character1->refresh();
        $character2->refresh();

        $this->assertFalse($character1->is_loot_councillor);
        $this->assertTrue($character2->is_loot_councillor);
    }

    public function test_remove_councillor_returns_404_for_nonexistent_character(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', 99999));

        $response->assertNotFound();
    }
}
