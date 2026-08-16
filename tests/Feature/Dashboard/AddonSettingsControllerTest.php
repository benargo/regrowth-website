<?php

namespace Tests\Feature\Dashboard;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class AddonSettingsControllerTest extends DashboardTestCase
{
    #[Test]
    public function settings_requires_authentication(): void
    {
        $response = $this->get(route('management.addon.settings'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function settings_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get(route('management.addon.settings'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function settings_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('management.addon.settings'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function settings_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('management.addon.settings'));

        $response->assertForbidden();
    }

    #[Test]
    public function settings_allows_officer_users(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertOk();
    }

    #[Test]
    public function settings_renders_inertia_page(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Addon/Settings')
        );
    }

    #[Test]
    public function settings_includes_councillors_in_settings(): void
    {
        Character::factory()->main()->lootCouncillor()->create(['name' => 'SettingsCouncillor']);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('councillors.data', 1)
            ->where('councillors.data.0.name', 'SettingsCouncillor')
        );
    }

    #[Test]
    public function settings_councillors_include_portrait_url_when_media_attached(): void
    {
        Storage::fake('public');

        $character = Character::factory()->main()->lootCouncillor()->create(['name' => 'SettingsCouncillor']);
        $character->addMediaFromString('BINARY')
            ->usingFileName('character_15678.jpg')
            ->withCustomProperties(['size' => 135])
            ->toMediaCollection(Character::MEDIA_COLLECTION);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('councillors.data.0.portrait_url', fn ($url) => str_contains($url, 'character_15678.jpg'))
        );
    }

    #[Test]
    public function settings_councillors_have_null_portrait_url_when_no_media_attached(): void
    {
        Character::factory()->main()->lootCouncillor()->create(['name' => 'SettingsCouncillor']);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('councillors.data.0.portrait_url', null)
        );
    }

    #[Test]
    public function settings_councillors_meta_reports_total_mains_and_alts(): void
    {
        Character::factory()->main()->lootCouncillor()->withUniqueName()->create();
        Character::factory()->main()->lootCouncillor()->withUniqueName()->create();
        Character::factory()->lootCouncillor()->withUniqueName()->create();

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('councillors.meta.total', 3)
            ->where('councillors.meta.mains', 2)
            ->where('councillors.meta.alts', 1)
        );
    }

    #[Test]
    public function settings_councillors_data_excludes_alts(): void
    {
        Character::factory()->main()->lootCouncillor()->create(['name' => 'MainCouncillor']);
        Character::factory()->lootCouncillor()->create(['name' => 'AltCouncillor']);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('councillors.data', 1)
            ->where('councillors.data.0.name', 'MainCouncillor')
        );
    }

    #[Test]
    public function settings_includes_guild_ranks_in_settings(): void
    {
        // Clear any existing ranks and create our test rank
        GuildRank::query()->delete();
        GuildRank::factory()->create(['name' => 'Test Rank', 'position' => 1]);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        // Note: GuildRank model transforms names to title case
        $response->assertInertia(fn (Assert $page) => $page
            ->has('ranks.data', 1)
            ->where('ranks.data.0.name', 'Test Rank')
        );
    }

    #[Test]
    public function settings_includes_guild_tags_in_settings(): void
    {
        GuildTag::factory()->countsAttendance()->create(['name' => 'TestTag']);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('tags.data', 1)
            ->where('tags.data.0.name', 'TestTag')
            ->where('tags.data.0.count_attendance', true)
        );
    }

    #[Test]
    public function settings_includes_characters_as_deferred_prop(): void
    {
        Character::factory()->main()->create(['name' => 'DeferredCharacter']);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('characters')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('characters.data')
                ->where('characters.data', fn ($characters) => collect($characters)->contains('name', 'DeferredCharacter'))
            )
        );
    }

    #[Test]
    public function settings_characters_excludes_alts(): void
    {
        Character::factory()->main()->create(['name' => 'MainCharacter']);
        Character::factory()->create(['name' => 'AltCharacter', 'is_main' => false]);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('characters.data', function ($characters) {
                    $names = collect($characters)->pluck('name')->toArray();

                    return $names === ['MainCharacter'];
                })
            )
        );
    }

    #[Test]
    public function settings_councillors_are_ordered_by_name(): void
    {
        Character::factory()->main()->lootCouncillor()->create(['name' => 'Zoe']);
        Character::factory()->main()->lootCouncillor()->create(['name' => 'Alice']);
        Character::factory()->main()->lootCouncillor()->create(['name' => 'Mike']);

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('councillors.data', function ($councillors) {
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

        $response = $this->actingAs($this->officer)->get(route('management.addon.settings'));

        // Note: GuildRank model transforms names to title case
        $response->assertInertia(fn (Assert $page) => $page
            ->has('ranks.data', 3)
            ->where('ranks.data.0.name', 'Guild Master')
            ->where('ranks.data.1.name', 'Officer')
            ->where('ranks.data.2.name', 'Member')
        );
    }
}
