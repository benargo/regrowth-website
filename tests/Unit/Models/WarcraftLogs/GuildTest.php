<?php

namespace Tests\Unit\Models\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Models\GameVersion;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use App\Policies\WarcraftLogsGuildPolicy;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class GuildTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Guild::class;
    }

    #[Test]
    public function it_uses_the_warcraft_logs_guilds_table(): void
    {
        $this->assertSame('warcraft_logs_guilds', (new Guild)->getTable());
    }

    #[Test]
    #[Group('auth')]
    public function it_is_governed_by_the_warcraft_logs_guild_policy(): void
    {
        $this->assertInstanceOf(WarcraftLogsGuildPolicy::class, Gate::getPolicyFor(Guild::class));
    }

    #[Test]
    public function it_is_keyed_by_the_warcraft_logs_guild_id(): void
    {
        $guild = new Guild;

        $this->assertSame('id', $guild->getKeyName());
        $this->assertFalse($guild->getIncrementing());
        $this->assertSame('int', $guild->getKeyType());
    }

    #[Test]
    public function it_stores_the_given_warcraft_logs_guild_id(): void
    {
        $guild = Guild::create(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);

        $this->assertSame(774848, $guild->fresh()->id);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $this->assertFillableAttribute(new Guild, ['id', 'namespace']);
    }

    #[Test]
    public function it_casts_the_namespace_to_the_warcraft_logs_namespace_enum(): void
    {
        $guild = $this->create(['namespace' => WarcraftLogsNamespace::Classic]);

        $this->assertCasts($guild, ['id' => 'integer', 'namespace' => WarcraftLogsNamespace::class]);
        $this->assertSame(WarcraftLogsNamespace::Classic, $guild->fresh()->namespace);
    }

    // ==================== relationships ====================

    #[Test]
    public function it_has_many_game_versions(): void
    {
        $guild = $this->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create();
        GameVersion::factory()->forGuild()->create();

        $this->assertRelation($guild, 'gameVersions', HasMany::class);
        $this->assertTrue($guild->gameVersions->sole()->is($gameVersion));
    }

    #[Test]
    public function it_has_many_guild_tags(): void
    {
        $guild = $this->create();
        $guildTag = GuildTag::factory()->forGuild($guild)->create();
        GuildTag::factory()->forGuild()->create();

        $this->assertRelation($guild, 'guildTags', HasMany::class);
        $this->assertTrue($guild->guildTags->sole()->is($guildTag));
    }

    #[Test]
    public function it_has_many_reports(): void
    {
        $guild = $this->create();
        $report = Report::factory()->forGuild($guild)->create();
        Report::factory()->forGuild()->create();

        $this->assertRelation($guild, 'reports', HasMany::class);
        $this->assertTrue($guild->reports->sole()->is($report));
    }

    // ==================== currentGameVersion ====================

    #[Test]
    public function current_game_version_is_the_latest_released_version(): void
    {
        $guild = $this->create();
        GameVersion::factory()->forGuild($guild)->create(['release_date' => now()->subYears(2)]);
        $latest = GameVersion::factory()->forGuild($guild)->create(['release_date' => now()->subMonth()]);

        $this->assertRelation($guild, 'currentGameVersion', HasOne::class);
        $this->assertTrue($guild->currentGameVersion->is($latest));
    }

    #[Test]
    public function current_game_version_skips_versions_released_in_the_future(): void
    {
        $guild = $this->create();
        $released = GameVersion::factory()->forGuild($guild)->create(['release_date' => now()->subMonth()]);
        GameVersion::factory()->forGuild($guild)->create(['release_date' => now()->addMonth()]);

        $this->assertTrue($guild->currentGameVersion->is($released));
    }

    #[Test]
    public function current_game_version_ignores_another_guilds_versions(): void
    {
        $guild = $this->create();
        $own = GameVersion::factory()->forGuild($guild)->create(['release_date' => now()->subYear()]);
        GameVersion::factory()->forGuild()->create(['release_date' => now()->subDay()]);

        $this->assertTrue($guild->currentGameVersion->is($own));
    }

    #[Test]
    public function current_game_version_is_null_when_nothing_is_released(): void
    {
        $guild = $this->create();
        GameVersion::factory()->forGuild($guild)->create(['release_date' => now()->addMonth()]);

        $this->assertNull($guild->currentGameVersion);
    }

    #[Test]
    public function current_game_version_can_be_eager_loaded(): void
    {
        $guild = $this->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => now()->subMonth()]);

        $loaded = Guild::with('currentGameVersion')->find($guild->id);

        $this->assertTrue($loaded->relationLoaded('currentGameVersion'));
        $this->assertTrue($loaded->currentGameVersion->is($gameVersion));
    }

    // ==================== warcraft logs cache ====================

    #[Test]
    public function creating_a_guild_flushes_the_cached_warcraft_logs_api_responses(): void
    {
        Cache::tags(['warcraftlogs', 'warcraftlogs-api-response'])->put('response_key', 'response', now()->addMinutes(5));

        $this->create();

        $this->assertFalse(Cache::tags(['warcraftlogs', 'warcraftlogs-api-response'])->has('response_key'));
    }

    #[Test]
    public function changing_the_namespace_flushes_the_cached_warcraft_logs_api_responses(): void
    {
        $guild = $this->create(['namespace' => WarcraftLogsNamespace::Anniversary]);
        Cache::tags(['warcraftlogs', 'warcraftlogs-api-response'])->put('response_key', 'response', now()->addMinutes(5));

        $guild->update(['namespace' => WarcraftLogsNamespace::Classic]);

        $this->assertFalse(Cache::tags(['warcraftlogs', 'warcraftlogs-api-response'])->has('response_key'));
    }

    #[Test]
    public function saving_without_a_namespace_change_keeps_the_cached_responses(): void
    {
        $guild = $this->create(['namespace' => WarcraftLogsNamespace::Anniversary]);
        Cache::tags(['warcraftlogs', 'warcraftlogs-api-response'])->put('response_key', 'response', now()->addMinutes(5));

        $guild->touch();

        $this->assertTrue(Cache::tags(['warcraftlogs', 'warcraftlogs-api-response'])->has('response_key'));
    }

    #[Test]
    public function changing_the_namespace_leaves_unrelated_cache_tags_alone(): void
    {
        $guild = $this->create(['namespace' => WarcraftLogsNamespace::Anniversary]);
        Cache::tags(['attendance'])->put('unrelated_key', 'unrelated', now()->addMinutes(5));

        $guild->update(['namespace' => WarcraftLogsNamespace::Classic]);

        $this->assertTrue(Cache::tags(['attendance'])->has('unrelated_key'));
    }

    #[Test]
    public function deleting_a_guild_cascades_to_its_tags_but_keeps_their_reports(): void
    {
        $guild = Guild::factory()->create();
        $guildTag = GuildTag::factory()->forGuild($guild)->create();
        $report = Report::factory()->withGuildTag($guildTag)->create();

        $guild->delete();

        $this->assertModelMissing($guildTag);
        $this->assertModelExists($report);
        $this->assertNull($report->fresh()->guild_tag_id);
    }
}
