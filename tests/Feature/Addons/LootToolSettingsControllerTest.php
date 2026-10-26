<?php

namespace Tests\Feature\Addons;

use App\Actions\GameVersion\RememberGameVersion;
use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Permission;
use App\Models\User;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('platform')]
class LootToolSettingsControllerTest extends DashboardTestCase
{
    protected Guild $guild;

    protected GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $officerRole = DiscordRole::find('829021769448816691');
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'update-characters', 'guard_name' => 'web']));
        $officerRole->givePermissionTo(Permission::firstOrCreate(['name' => 'update-warcraft-logs-tags', 'guard_name' => 'web']));

        $this->guild = Guild::factory()->create();
        $this->gameVersion = GameVersion::factory()->fetchableRoster()->forGuild($this->guild)->create();
        RememberGameVersion::run($this->gameVersion->slug);
    }

    #[Test]
    public function show_requires_authentication(): void
    {
        $response = $this->get(route('addons.loot-tool.settings'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function show_forbids_non_officer_users(): void
    {
        foreach (['guest', 'member', 'raider'] as $state) {
            $user = User::factory()->{$state}()->create();

            $this->actingAs($user)->get(route('addons.loot-tool.settings'))->assertForbidden();
        }
    }

    // ==================== show ====================

    #[Test]
    public function show_renders_the_settings_page_for_the_current_game_version(): void
    {
        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Addons/LootTool/Settings')
            ->where('gameVersion.data.id', $this->gameVersion->id)
        );
    }

    #[Test]
    public function show_lists_only_the_current_game_versions_councillors_ranks_and_tags(): void
    {
        $otherVersion = GameVersion::factory()->create();
        Character::factory()->forGameVersion($this->gameVersion)->main()->lootCouncillor()->create(['name' => 'Zoe']);
        Character::factory()->forGameVersion($this->gameVersion)->main()->lootCouncillor()->create(['name' => 'Alice']);
        Character::factory()->forGameVersion($otherVersion)->main()->lootCouncillor()->create(['name' => 'Elsewhere']);
        GuildRank::factory()->for($this->gameVersion)->create(['name' => 'Raider', 'sort_order' => 1]);
        GuildRank::factory()->for($this->gameVersion)->create(['name' => 'Officer', 'sort_order' => 0]);
        GuildRank::factory()->for($otherVersion)->create(['name' => 'Elsewhere']);
        GuildTag::factory()->forGuild($this->guild)->create(['name' => 'Beta']);
        GuildTag::factory()->forGuild($this->guild)->create(['name' => 'Alpha']);
        GuildTag::factory()->forGuild()->create(['name' => 'Elsewhere']);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('councillors.data', fn ($councillors) => collect($councillors)->pluck('name')->all() === ['Alice', 'Zoe'])
            ->where('ranks.data', fn ($ranks) => collect($ranks)->pluck('name')->all() === ['Officer', 'Raider'])
            ->where('tags.data', fn ($tags) => collect($tags)->pluck('name')->all() === ['Alpha', 'Beta'])
        );
    }

    #[Test]
    public function show_lists_no_tags_when_the_game_version_has_no_warcraft_logs_guild(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        GuildTag::factory()->create(['name' => 'Unassigned']);
        RememberGameVersion::run($gameVersion->slug);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.settings'));

        $response->assertInertia(fn (Assert $page) => $page->has('tags.data', 0));
    }

    #[Test]
    public function show_councillors_include_portrait_url_when_media_attached(): void
    {
        Storage::fake('s3');
        $character = Character::factory()->forGameVersion($this->gameVersion)->main()->lootCouncillor()->create();
        $character->addMediaFromString('BINARY')
            ->usingFileName('character_15678.jpg')
            ->withCustomProperties(['size' => 135])
            ->toMediaCollection(Character::MEDIA_COLLECTION);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('councillors.data.0.portrait_url', fn ($url) => str_contains($url, 'character_15678.jpg'))
        );
    }

    #[Test]
    public function show_councillors_meta_reports_total_mains_and_alts(): void
    {
        Character::factory()->forGameVersion($this->gameVersion)->main()->lootCouncillor()->withUniqueName()->create();
        Character::factory()->forGameVersion($this->gameVersion)->main()->lootCouncillor()->withUniqueName()->create();
        Character::factory()->forGameVersion($this->gameVersion)->lootCouncillor()->withUniqueName()->create();

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('councillors.data', 2)
            ->where('councillors.meta.total', 3)
            ->where('councillors.meta.mains', 2)
            ->where('councillors.meta.alts', 1)
        );
    }

    #[Test]
    public function show_lists_only_the_current_game_versions_mains_as_candidates(): void
    {
        Character::factory()->forGameVersion($this->gameVersion)->main()->create(['name' => 'MainCharacter']);
        Character::factory()->forGameVersion($this->gameVersion)->create(['name' => 'AltCharacter']);
        Character::factory()->forGameVersion(GameVersion::factory()->create())->main()->create(['name' => 'Elsewhere']);

        $response = $this->actingAs($this->officer)->get(route('addons.loot-tool.settings'));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('characters')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('characters.data', fn ($characters) => collect($characters)->pluck('name')->all() === ['MainCharacter'])
            )
        );
    }

    // ==================== update ====================

    #[Test]
    public function update_saves_the_settings_and_redirects_back(): void
    {
        $councillor = Character::factory()->forGameVersion($this->gameVersion)->main()->withUniqueName()->create();
        $rank = GuildRank::factory()->for($this->gameVersion)->doesNotCountAttendance()->create();
        $tag = GuildTag::factory()->forGuild($this->guild)->doesNotCountAttendance()->create();
        RememberGameVersion::run(GameVersion::factory()->fetchableRoster()->forGuild()->create()->slug);

        $response = $this->actingAs($this->officer)
            ->from(route('addons.loot-tool.settings'))
            ->put(route('addons.loot-tool.settings.update'), [
                'game_version' => $this->gameVersion->slug,
                'councillor_ids' => [$councillor->id],
                'counted_rank_ids' => [$rank->id],
                'counted_tag_ids' => [$tag->id],
            ]);

        $response->assertRedirect(route('addons.loot-tool.settings'));
        $response->assertSessionHas('success', 'Addon settings saved.');
        $this->assertTrue($councillor->fresh()->is_loot_councillor);
        $this->assertTrue($rank->fresh()->count_attendance);
        $this->assertTrue($tag->fresh()->count_attendance);
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_ids_from_another_game_version(): void
    {
        $otherVersion = GameVersion::factory()->fetchableRoster()->forGuild()->create();
        $councillor = Character::factory()->forGameVersion($this->gameVersion)->lootCouncillor()->withUniqueName()->create();
        $otherCharacter = Character::factory()->forGameVersion($otherVersion)->withUniqueName()->create();
        $otherRank = GuildRank::factory()->for($otherVersion)->doesNotCountAttendance()->create();
        $otherTag = GuildTag::factory()->forGuild($otherVersion->warcraftLogsGuild)->doesNotCountAttendance()->create();

        RememberGameVersion::run($otherVersion->slug);

        $response = $this->actingAs($this->officer)->putJson(route('addons.loot-tool.settings.update'), [
            'game_version' => $this->gameVersion->slug,
            'councillor_ids' => [$otherCharacter->id],
            'counted_rank_ids' => [$otherRank->id],
            'counted_tag_ids' => [$otherTag->id],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['councillor_ids.0', 'counted_rank_ids.0', 'counted_tag_ids.0']);
        $this->assertTrue($councillor->fresh()->is_loot_councillor);
        $this->assertFalse($otherCharacter->fresh()->is_loot_councillor);
        $this->assertFalse($otherRank->fresh()->count_attendance);
        $this->assertFalse($otherTag->fresh()->count_attendance);
    }

    #[Group('validation')]
    #[Test]
    public function update_requires_a_known_game_version(): void
    {
        $this->actingAs($this->officer)
            ->putJson(route('addons.loot-tool.settings.update'), ['game_version' => 'no-such-version', 'councillor_ids' => []])
            ->assertJsonValidationErrors(['game_version']);

        $this->actingAs($this->officer)
            ->putJson(route('addons.loot-tool.settings.update'), ['councillor_ids' => []])
            ->assertJsonValidationErrors(['game_version']);
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_councillor_changes_without_update_characters(): void
    {
        $user = $this->officerWithPermissions(['edit-datasets', 'update-warcraft-logs-tags']);
        $character = Character::factory()->forGameVersion($this->gameVersion)->withUniqueName()->create();

        $response = $this->actingAs($user)->put(route('addons.loot-tool.settings.update'), [
            'game_version' => $this->gameVersion->slug,
            'councillor_ids' => [$character->id],
        ]);

        $response->assertForbidden();
        $this->assertFalse($character->fresh()->is_loot_councillor);
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_rank_changes_without_edit_datasets(): void
    {
        $user = $this->officerWithPermissions(['update-characters', 'update-warcraft-logs-tags']);
        $rank = GuildRank::factory()->for($this->gameVersion)->create();

        $response = $this->actingAs($user)->put(route('addons.loot-tool.settings.update'), [
            'game_version' => $this->gameVersion->slug,
            'counted_rank_ids' => [],
        ]);

        $response->assertForbidden();
        $this->assertTrue($rank->fresh()->count_attendance);
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_tag_changes_without_update_warcraft_logs_tags(): void
    {
        $user = $this->officerWithPermissions(['update-characters', 'edit-datasets']);
        $tag = GuildTag::factory()->forGuild($this->guild)->countsAttendance()->create();

        $response = $this->actingAs($user)->put(route('addons.loot-tool.settings.update'), [
            'game_version' => $this->gameVersion->slug,
            'counted_tag_ids' => [],
        ]);

        $response->assertForbidden();
        $this->assertTrue($tag->fresh()->count_attendance);
    }

    #[Group('authorization')]
    #[Test]
    public function update_allows_sections_the_officer_may_change_without_the_other_permissions(): void
    {
        $user = $this->officerWithPermissions(['update-characters']);
        $character = Character::factory()->forGameVersion($this->gameVersion)->withUniqueName()->create();

        $response = $this->actingAs($user)
            ->from(route('addons.loot-tool.settings'))
            ->put(route('addons.loot-tool.settings.update'), [
                'game_version' => $this->gameVersion->slug,
                'councillor_ids' => [$character->id],
            ]);

        $response->assertRedirect(route('addons.loot-tool.settings'));
        $this->assertTrue($character->fresh()->is_loot_councillor);
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_members(): void
    {
        $character = Character::factory()->forGameVersion($this->gameVersion)->withUniqueName()->create();

        $response = $this->actingAs(User::factory()->member()->create())->put(route('addons.loot-tool.settings.update'), [
            'game_version' => $this->gameVersion->slug,
            'councillor_ids' => [$character->id],
        ]);

        $response->assertForbidden();
        $this->assertFalse($character->fresh()->is_loot_councillor);
    }

    // ==================== helpers ====================

    /**
     * Create a user whose only role grants the officer dashboard plus the given permissions.
     *
     * @param  list<string>  $permissions
     */
    protected function officerWithPermissions(array $permissions): User
    {
        $role = DiscordRole::factory()->create();
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'view-officer-dashboard', 'guard_name' => 'web']));

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->discordRoles()->attach($role);

        return $user;
    }
}
