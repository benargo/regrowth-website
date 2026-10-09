<?php

namespace Tests\Feature\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Jobs\WarcraftLogs\FetchGuildTags;
use App\Models\GameVersion;
use App\Models\Report;
use App\Models\User;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class GuildControllerTest extends DashboardTestCase
{
    #[Test]
    public function it_redirects_guests_to_login(): void
    {
        $response = $this->get(route('management.warcraftlogs.guilds.index'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    #[Group('happy-path')]
    public function it_lists_every_guild_with_its_game_versions_and_tag_count(): void
    {
        $guild = Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);
        $gameVersion = GameVersion::factory()->forGuild($guild)->create();
        GuildTag::factory()->forGuild($guild)->count(2)->create();
        Guild::factory()->create(['id' => 598032, 'namespace' => WarcraftLogsNamespace::Classic]);

        $response = $this->actingAs($this->userWith('view-warcraft-logs-guilds'))->get(route('management.warcraftlogs.guilds.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/WarcraftLogs/Guilds/Index')
            ->has('guilds', 2)
            ->where('guilds.0.id', 598032)
            ->where('guilds.1.id', 774848)
            ->where('guilds.1.namespace', ['value' => 'anniversary', 'label' => 'The Burning Crusade Classic Anniversary'])
            ->where('guilds.1.guild_tags_count', 2)
            ->has('guilds.1.game_versions', 1)
            ->where('guilds.1.game_versions.0.id', $gameVersion->id)
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_the_index_without_view_guilds(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.warcraftlogs.guilds.index'));

        $response->assertForbidden();
    }

    // ==================== create and store ====================

    #[Test]
    public function it_renders_the_create_page_with_the_namespace_options(): void
    {
        $response = $this->actingAs($this->userWith('create-warcraft-logs-guilds'))->get(route('management.warcraftlogs.guilds.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/WarcraftLogs/Guilds/Create')
            ->has('namespaces', count(WarcraftLogsNamespace::cases()))
            ->where('namespaces.0', ['value' => 'anniversary', 'label' => 'The Burning Crusade Classic Anniversary'])
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_the_create_page_without_create_guilds(): void
    {
        $response = $this->actingAs($this->userWith('view-warcraft-logs-guilds'))->get(route('management.warcraftlogs.guilds.create'));

        $response->assertForbidden();
    }

    #[Test]
    #[Group('happy-path')]
    public function it_stores_a_guild_fetches_its_tags_and_shows_it(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->userWith('create-warcraft-logs-guilds'))->post(route('management.warcraftlogs.guilds.store'), [
            'id' => 774848,
            'namespace' => 'anniversary',
        ]);

        $response->assertRedirect(route('management.warcraftlogs.guilds.show', 774848));
        $response->assertSessionHas('success', 'Added Warcraft Logs guild 774848.');
        $this->assertSame(WarcraftLogsNamespace::Anniversary, Guild::find(774848)?->namespace);
        Queue::assertPushed(FetchGuildTags::class, fn (FetchGuildTags $job): bool => $job->guild->id === 774848);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_store_without_create_guilds(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->userWith('view-warcraft-logs-guilds', 'update-warcraft-logs-guilds'))->post(route('management.warcraftlogs.guilds.store'), [
            'id' => 774848,
            'namespace' => 'anniversary',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('warcraft_logs_guilds', 0);
        Queue::assertNothingPushed();
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_an_id_and_a_namespace(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->userWith('create-warcraft-logs-guilds'))->post(route('management.warcraftlogs.guilds.store'), []);

        $response->assertInvalid([
            'id' => 'Enter the Warcraft Logs guild ID.',
            'namespace' => 'Choose which Warcraft Logs site the guild is on.',
        ]);
        $this->assertDatabaseCount('warcraft_logs_guilds', 0);
        Queue::assertNothingPushed();
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_id_that_is_not_a_whole_number(): void
    {
        $response = $this->actingAs($this->userWith('create-warcraft-logs-guilds'))->post(route('management.warcraftlogs.guilds.store'), [
            'id' => 'regrowth',
            'namespace' => 'anniversary',
        ]);

        $response->assertInvalid(['id' => 'The Warcraft Logs guild ID field must be an integer.']);
        $this->assertDatabaseCount('warcraft_logs_guilds', 0);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_an_id_below_one(): void
    {
        $response = $this->actingAs($this->userWith('create-warcraft-logs-guilds'))->post(route('management.warcraftlogs.guilds.store'), [
            'id' => 0,
            'namespace' => 'anniversary',
        ]);

        $response->assertInvalid(['id' => 'The Warcraft Logs guild ID field must be at least 1.']);
        $this->assertDatabaseCount('warcraft_logs_guilds', 0);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_guild_that_was_already_added(): void
    {
        Queue::fake();
        Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);

        $response = $this->actingAs($this->userWith('create-warcraft-logs-guilds'))->post(route('management.warcraftlogs.guilds.store'), [
            'id' => 774848,
            'namespace' => 'classic',
        ]);

        $response->assertInvalid(['id' => 'This Warcraft Logs guild has already been added.']);
        $this->assertSame(WarcraftLogsNamespace::Anniversary, Guild::find(774848)->namespace);
        Queue::assertNothingPushed();
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_namespace_outside_the_enum(): void
    {
        $response = $this->actingAs($this->userWith('create-warcraft-logs-guilds'))->post(route('management.warcraftlogs.guilds.store'), [
            'id' => 774848,
            'namespace' => 'ANNIVERSARY',
        ]);

        $response->assertInvalid(['namespace' => 'The selected Warcraft Logs site is invalid.']);
        $this->assertDatabaseCount('warcraft_logs_guilds', 0);
    }

    // ==================== show ====================

    #[Test]
    #[Group('happy-path')]
    public function it_shows_the_guild_with_its_tags_and_what_a_delete_would_detach(): void
    {
        $guild = Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);
        GameVersion::factory()->forGuild($guild)->create();
        $zulu = GuildTag::factory()->forGuild($guild)->countsAttendance()->create(['name' => 'Zulu']);
        $alpha = GuildTag::factory()->forGuild($guild)->doesNotCountAttendance()->create(['name' => 'Alpha']);
        GuildTag::factory()->forGuild()->create(['name' => 'Another guild']);
        Report::factory()->forGuild($guild)->count(3)->create();

        $response = $this->actingAs($this->userWith('view-warcraft-logs-guilds'))->get(route('management.warcraftlogs.guilds.show', $guild));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/WarcraftLogs/Guilds/Show')
            ->where('guild.id', 774848)
            ->where('guild.namespace.value', 'anniversary')
            ->where('guild.guild_tags', [
                ['id' => $alpha->id, 'name' => 'Alpha', 'count_attendance' => false, 'guild_id' => 774848],
                ['id' => $zulu->id, 'name' => 'Zulu', 'count_attendance' => true, 'guild_id' => 774848],
            ])
            ->has('guild.game_versions', 1)
            ->where('guild.game_versions_count', 1)
            ->where('guild.guild_tags_count', 2)
            ->where('guild.reports_count', 3)
            ->has('namespaces', count(WarcraftLogsNamespace::cases()))
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_the_guild_page_without_view_guilds(): void
    {
        $guild = Guild::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.warcraftlogs.guilds.show', $guild));

        $response->assertForbidden();
    }

    #[Test]
    public function it_returns_404_for_an_unknown_guild(): void
    {
        $response = $this->actingAs($this->userWith('view-warcraft-logs-guilds'))->get(route('management.warcraftlogs.guilds.show', 999999));

        $response->assertNotFound();
    }

    // ==================== update ====================

    #[Test]
    #[Group('happy-path')]
    public function it_changes_the_namespace_and_returns_to_the_guild(): void
    {
        $guild = Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);

        $response = $this->actingAs($this->userWith('update-warcraft-logs-guilds'))
            ->from(route('management.warcraftlogs.guilds.show', $guild))
            ->patch(route('management.warcraftlogs.guilds.update', $guild), ['namespace' => 'classic']);

        $response->assertRedirect(route('management.warcraftlogs.guilds.show', $guild));
        $response->assertSessionHas('success', 'Saved Warcraft Logs guild 774848.');
        $this->assertSame(WarcraftLogsNamespace::Classic, $guild->fresh()->namespace);
    }

    #[Test]
    #[Group('edge-case')]
    public function it_never_changes_the_guild_id(): void
    {
        $guild = Guild::factory()->create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);

        $this->actingAs($this->userWith('update-warcraft-logs-guilds'))
            ->patch(route('management.warcraftlogs.guilds.update', $guild), ['id' => 1, 'namespace' => 'classic']);

        $this->assertNull(Guild::find(1));
        $this->assertSame(WarcraftLogsNamespace::Classic, Guild::find(774848)->namespace);
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_a_namespace_on_update(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Anniversary]);

        $response = $this->actingAs($this->userWith('update-warcraft-logs-guilds'))
            ->patch(route('management.warcraftlogs.guilds.update', $guild), ['namespace' => '']);

        $response->assertInvalid(['namespace' => 'Choose which Warcraft Logs site the guild is on.']);
        $this->assertSame(WarcraftLogsNamespace::Anniversary, $guild->fresh()->namespace);
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_namespace_outside_the_enum_on_update(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Anniversary]);

        $response = $this->actingAs($this->userWith('update-warcraft-logs-guilds'))
            ->patch(route('management.warcraftlogs.guilds.update', $guild), ['namespace' => 'ANNIVERSARY']);

        $response->assertInvalid(['namespace' => 'The selected Warcraft Logs site is invalid.']);
        $this->assertSame(WarcraftLogsNamespace::Anniversary, $guild->fresh()->namespace);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_update_without_update_guilds(): void
    {
        $guild = Guild::factory()->create(['namespace' => WarcraftLogsNamespace::Anniversary]);

        $response = $this->actingAs($this->userWith('view-warcraft-logs-guilds', 'update-warcraft-logs-tags'))
            ->patch(route('management.warcraftlogs.guilds.update', $guild), ['namespace' => 'classic']);

        $response->assertForbidden();
        $this->assertSame(WarcraftLogsNamespace::Anniversary, $guild->fresh()->namespace);
    }

    // ==================== destroy ====================

    #[Test]
    #[Group('happy-path')]
    public function it_deletes_a_guild_and_returns_to_the_index(): void
    {
        $guild = Guild::factory()->create(['id' => 774848]);
        $guildTag = GuildTag::factory()->forGuild($guild)->create();

        $response = $this->actingAs($this->userWith('delete-warcraft-logs-guilds'))->delete(route('management.warcraftlogs.guilds.destroy', $guild));

        $response->assertRedirect(route('management.warcraftlogs.guilds.index'));
        $response->assertSessionHas('success', 'Deleted Warcraft Logs guild 774848.');
        $this->assertModelMissing($guild);
        $this->assertModelMissing($guildTag);
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_destroy_without_delete_guilds(): void
    {
        $guild = Guild::factory()->create();
        $guildTag = GuildTag::factory()->forGuild($guild)->create();

        $response = $this->actingAs($this->userWith('view-warcraft-logs-guilds', 'update-warcraft-logs-guilds'))->delete(route('management.warcraftlogs.guilds.destroy', $guild));

        $response->assertForbidden();
        $this->assertModelExists($guild);
        $this->assertSame($guild->id, $guildTag->fresh()->warcraft_logs_guild_id);
    }

    // ==================== helpers ====================

    private function userWith(string ...$permissions): User
    {
        return User::factory()->withPermissions(...$permissions)->create();
    }
}
