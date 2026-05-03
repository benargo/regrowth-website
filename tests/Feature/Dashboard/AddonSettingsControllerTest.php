<?php

namespace Tests\Feature\Dashboard;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\User;
use App\Models\GuildTag;
use App\Services\WarcraftLogs\GuildTags;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class AddonSettingsControllerTest extends DashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock GuildTags to return empty tags by default
        // This prevents API calls during tests that don't specifically test guild tags
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')
            ->andReturn(collect())
            ->byDefault();

        $this->app->instance(GuildTags::class, $guildTags);
    }

    // ==========================================
    // Settings Endpoint Tests
    // ==========================================

    #[Test]
    public function settings_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.addon.settings'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function settings_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertForbidden();
    }

    #[Test]
    public function settings_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertForbidden();
    }

    #[Test]
    public function settings_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('dashboard.addon.settings'));

        $response->assertForbidden();
    }

    #[Test]
    public function settings_allows_officer_users(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.settings'));

        $response->assertOk();
    }

    #[Test]
    public function settings_renders_inertia_page(): void
    {
        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Addon/Settings')
        );
    }

    #[Test]
    public function settings_includes_councillors_in_settings(): void
    {
        $councillor = Character::factory()->lootCouncillor()->create(['name' => 'SettingsCouncillor']);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('settings.councillors', 1)
            ->where('settings.councillors.0.name', 'SettingsCouncillor')
        );
    }

    #[Test]
    public function settings_includes_guild_ranks_in_settings(): void
    {
        // Clear any existing ranks and create our test rank
        GuildRank::query()->delete();
        GuildRank::factory()->create(['name' => 'Test Rank', 'position' => 1]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.settings'));

        // Note: GuildRank model transforms names to title case
        $response->assertInertia(fn (Assert $page) => $page
            ->has('settings.ranks', 1)
            ->where('settings.ranks.0.name', 'Test Rank')
        );
    }

    #[Test]
    public function settings_includes_guild_tags_in_settings(): void
    {
        $tag = GuildTag::factory()->countsAttendance()->create(['name' => 'TestTag']);

        // Mock the WarcraftLogs GuildService to return our tag
        $guildTags = Mockery::mock(GuildTags::class);
        $guildTags->shouldReceive('toCollection')
            ->andReturn(collect([$tag]));
        $this->app->instance(GuildTags::class, $guildTags);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('settings.tags', 1)
            ->where('settings.tags.0.name', 'TestTag')
            ->where('settings.tags.0.count_attendance', true)
        );
    }

    #[Test]
    public function settings_includes_characters_as_deferred_prop(): void
    {
        Character::factory()->create(['name' => 'DeferredCharacter']);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('characters')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('characters')
                ->where('characters', fn ($characters) => collect($characters)->contains('name', 'DeferredCharacter'))
            )
        );
    }

    #[Test]
    public function settings_councillors_are_ordered_by_name(): void
    {
        Character::factory()->lootCouncillor()->create(['name' => 'Zoe']);
        Character::factory()->lootCouncillor()->create(['name' => 'Alice']);
        Character::factory()->lootCouncillor()->create(['name' => 'Mike']);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('settings.councillors', function ($councillors) {
                $names = collect($councillors)->pluck('name')->toArray();

                return $names === ['Alice', 'Mike', 'Zoe'];
            })
        );
    }

    #[Test]
    public function settings_ranks_are_ordered_by_position(): void
    {
        // Clear any existing ranks and create our test ranks
        GuildRank::query()->delete();
        GuildRank::factory()->create(['name' => 'Officer', 'position' => 2]);
        GuildRank::factory()->create(['name' => 'Guild Master', 'position' => 1]);
        GuildRank::factory()->create(['name' => 'Member', 'position' => 3]);

        $response = $this->actingAs($this->officer)->get(route('dashboard.addon.settings'));

        // Note: GuildRank model transforms names to title case
        $response->assertInertia(fn (Assert $page) => $page
            ->has('settings.ranks', 3)
            ->where('settings.ranks.0.name', 'Guild Master')
            ->where('settings.ranks.1.name', 'Officer')
            ->where('settings.ranks.2.name', 'Member')
        );
    }

    // ==========================================
    // Add Councillor Endpoint Tests
    // ==========================================

    #[Test]
    public function add_councillor_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.addon.settings.councillors.add'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function add_councillor_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function add_councillor_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function add_councillor_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($user)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function add_councillor_allows_officer_users(): void
    {
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($this->officer)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function add_councillor_requires_character_name(): void
    {
        $response = $this->actingAs($this->officer)->post(route('dashboard.addon.settings.councillors.add'), []);

        $response->assertSessionHasErrors(['character_name']);
    }

    #[Test]
    public function add_councillor_requires_character_name_to_be_string(): void
    {
        $response = $this->actingAs($this->officer)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 12345,
        ]);

        $response->assertSessionHasErrors(['character_name']);
    }

    #[Test]
    public function add_councillor_requires_character_to_exist(): void
    {
        $response = $this->actingAs($this->officer)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => 'NonExistentCharacter',
        ]);

        $response->assertSessionHasErrors(['character_name']);
    }

    #[Test]
    public function add_councillor_sets_character_as_loot_councillor(): void
    {
        $character = Character::factory()->create(['name' => 'NewCouncillor', 'is_loot_councillor' => false]);

        $this->assertFalse($character->is_loot_councillor);

        $response = $this->actingAs($this->officer)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertRedirect();
        $this->assertTrue($character->fresh()->is_loot_councillor);
    }

    #[Test]
    public function add_councillor_redirects_back(): void
    {
        $character = Character::factory()->create(['name' => 'TestChar']);

        $response = $this->actingAs($this->officer)
            ->from(route('dashboard.addon.settings'))
            ->post(route('dashboard.addon.settings.councillors.add'), [
                'character_name' => $character->name,
            ]);

        $response->assertRedirect(route('dashboard.addon.settings'));
    }

    #[Test]
    public function add_councillor_is_idempotent(): void
    {
        $character = Character::factory()->lootCouncillor()->create(['name' => 'AlreadyCouncillor']);

        $this->assertTrue($character->is_loot_councillor);

        $response = $this->actingAs($this->officer)->post(route('dashboard.addon.settings.councillors.add'), [
            'character_name' => $character->name,
        ]);

        $response->assertRedirect();
        $this->assertTrue($character->fresh()->is_loot_councillor);
    }

    // ==========================================
    // Remove Councillor Endpoint Tests
    // ==========================================

    #[Test]
    public function remove_councillor_requires_authentication(): void
    {
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function remove_councillor_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    #[Test]
    public function remove_councillor_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    #[Test]
    public function remove_councillor_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertForbidden();
    }

    #[Test]
    public function remove_councillor_allows_officer_users(): void
    {
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($this->officer)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect();
    }

    #[Test]
    public function remove_councillor_unsets_character_as_loot_councillor(): void
    {
        $character = Character::factory()->lootCouncillor()->create(['name' => 'RemoveMe']);

        $this->assertTrue($character->is_loot_councillor);

        $response = $this->actingAs($this->officer)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect();
        $this->assertFalse($character->fresh()->is_loot_councillor);
    }

    #[Test]
    public function remove_councillor_redirects_back(): void
    {
        $character = Character::factory()->lootCouncillor()->create();

        $response = $this->actingAs($this->officer)
            ->from(route('dashboard.addon.settings'))
            ->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect(route('dashboard.addon.settings'));
    }

    #[Test]
    public function remove_councillor_returns_404_for_nonexistent_character(): void
    {
        $response = $this->actingAs($this->officer)->delete(route('dashboard.addon.settings.councillors.remove', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function remove_councillor_is_idempotent(): void
    {
        $character = Character::factory()->create(['name' => 'NotCouncillor', 'is_loot_councillor' => false]);

        $this->assertFalse($character->is_loot_councillor);

        $response = $this->actingAs($this->officer)->delete(route('dashboard.addon.settings.councillors.remove', $character));

        $response->assertRedirect();
        $this->assertFalse($character->fresh()->is_loot_councillor);
    }
}
