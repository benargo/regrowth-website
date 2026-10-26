<?php

namespace Tests\Feature\Actions\Addon;

use App\Actions\Addon\UpdateAddonSettings;
use App\Jobs\Addon\BuildExportFile;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ReleasesAddonExportLocks;
use Tests\TestCase;

#[Group('platform')]
class UpdateAddonSettingsTest extends TestCase
{
    use RefreshDatabase;
    use ReleasesAddonExportLocks;

    // ==================== loot councillors ====================

    #[Test]
    public function it_sets_the_loot_councillors_of_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $current = Character::factory()->forGameVersion($gameVersion)->main()->lootCouncillor()->withUniqueName()->create();
        $chosen = Character::factory()->forGameVersion($gameVersion)->withUniqueName()->create();

        UpdateAddonSettings::run($gameVersion, ['councillor_ids' => [$chosen->id]]);

        $this->assertFalse($current->fresh()->is_loot_councillor);
        $this->assertTrue($chosen->fresh()->is_loot_councillor);
    }

    #[Test]
    public function it_unflags_alt_councillors_that_are_not_listed(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $altCouncillor = Character::factory()->forGameVersion($gameVersion)->lootCouncillor()->withUniqueName()->create();
        $chosen = Character::factory()->forGameVersion($gameVersion)->main()->withUniqueName()->create();

        UpdateAddonSettings::run($gameVersion, ['councillor_ids' => [$chosen->id]]);

        $this->assertFalse($altCouncillor->fresh()->is_loot_councillor);
        $this->assertTrue($chosen->fresh()->is_loot_councillor);
    }

    #[Test]
    public function it_unflags_the_alts_of_an_unflagged_main_even_when_they_are_listed(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $main = Character::factory()->forGameVersion($gameVersion)->main()->lootCouncillor()->withUniqueName()->create();
        $alt = Character::factory()->forGameVersion($gameVersion)->lootCouncillor()->withUniqueName()->create();
        $main->linkedCharacters()->attach($alt->id);

        UpdateAddonSettings::run($gameVersion, ['councillor_ids' => [$alt->id]]);

        $this->assertFalse($main->fresh()->is_loot_councillor);
        $this->assertFalse($alt->fresh()->is_loot_councillor);
    }

    #[Test]
    public function it_flags_the_alts_of_a_flagged_main_even_when_they_are_not_listed(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $main = Character::factory()->forGameVersion($gameVersion)->main()->withUniqueName()->create();
        $alt = Character::factory()->forGameVersion($gameVersion)->withUniqueName()->create();
        $main->linkedCharacters()->attach($alt->id);

        UpdateAddonSettings::run($gameVersion, ['councillor_ids' => [$main->id]]);

        $this->assertTrue($main->fresh()->is_loot_councillor);
        $this->assertTrue($alt->fresh()->is_loot_councillor);
    }

    #[Test]
    public function it_keeps_the_alts_of_a_main_that_stays_flagged(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $main = Character::factory()->forGameVersion($gameVersion)->main()->lootCouncillor()->withUniqueName()->create();
        $alt = Character::factory()->forGameVersion($gameVersion)->lootCouncillor()->withUniqueName()->create();
        $main->linkedCharacters()->attach($alt->id);

        UpdateAddonSettings::run($gameVersion, ['councillor_ids' => [$main->id, $alt->id]]);

        $this->assertTrue($main->fresh()->is_loot_councillor);
        $this->assertTrue($alt->fresh()->is_loot_councillor);
    }

    #[Test]
    public function it_leaves_other_game_versions_councillors_untouched(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $otherCouncillor = Character::factory()->forGameVersion(GameVersion::factory()->create())->lootCouncillor()->withUniqueName()->create();

        UpdateAddonSettings::run($gameVersion, ['councillor_ids' => []]);

        $this->assertTrue($otherCouncillor->fresh()->is_loot_councillor);
    }

    // ==================== attendance ====================

    #[Test]
    public function it_sets_which_ranks_count_attendance(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $counted = GuildRank::factory()->for($gameVersion)->create();
        $uncounted = GuildRank::factory()->for($gameVersion)->doesNotCountAttendance()->create();
        $otherVersionRank = GuildRank::factory()->for(GameVersion::factory()->create())->create();

        UpdateAddonSettings::run($gameVersion, ['counted_rank_ids' => [$uncounted->id]]);

        $this->assertFalse($counted->fresh()->count_attendance);
        $this->assertTrue($uncounted->fresh()->count_attendance);
        $this->assertTrue($otherVersionRank->fresh()->count_attendance);
    }

    #[Test]
    public function it_sets_which_tags_count_attendance(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->fetchableRoster()->forGuild($guild)->create();
        $counted = GuildTag::factory()->forGuild($guild)->countsAttendance()->create();
        $uncounted = GuildTag::factory()->forGuild($guild)->doesNotCountAttendance()->create();
        $otherGuildTag = GuildTag::factory()->forGuild()->countsAttendance()->create();

        UpdateAddonSettings::run($gameVersion, ['counted_tag_ids' => [$uncounted->id]]);

        $this->assertFalse($counted->fresh()->count_attendance);
        $this->assertTrue($uncounted->fresh()->count_attendance);
        $this->assertTrue($otherGuildTag->fresh()->count_attendance);
    }

    // ==================== partial updates ====================

    #[Test]
    public function it_leaves_sections_that_are_not_given_untouched(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->fetchableRoster()->forGuild($guild)->create();
        $councillor = Character::factory()->forGameVersion($gameVersion)->lootCouncillor()->withUniqueName()->create();
        $rank = GuildRank::factory()->for($gameVersion)->create();
        $tag = GuildTag::factory()->forGuild($guild)->countsAttendance()->create();

        UpdateAddonSettings::run($gameVersion, []);

        $this->assertTrue($councillor->fresh()->is_loot_councillor);
        $this->assertTrue($rank->fresh()->count_attendance);
        $this->assertTrue($tag->fresh()->count_attendance);
    }

    // ==================== export rebuilds ====================

    #[Test]
    public function it_schedules_one_rebuild_for_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->fetchableRoster()->create();
        $characters = Character::factory()->count(3)->forGameVersion($gameVersion)->withUniqueName()->create();
        $rank = GuildRank::factory()->for($gameVersion)->create();
        $this->releaseAddonExportLocks();

        UpdateAddonSettings::run($gameVersion, [
            'councillor_ids' => $characters->modelKeys(),
            'counted_rank_ids' => [],
        ]);

        Queue::assertPushed(BuildExportFile::class, 1);
        Queue::assertPushed(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id);
        $this->assertFalse($rank->fresh()->count_attendance);
    }

    #[Test]
    public function it_rebuilds_every_version_on_the_guild_when_a_tag_changes(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->fetchableRoster()->forGuild($guild)->create();
        $sibling = GameVersion::factory()->fetchableRoster()->forGuild($guild)->create();
        GameVersion::factory()->fetchableRoster()->forGuild()->create();
        $tag = GuildTag::factory()->forGuild($guild)->doesNotCountAttendance()->create();
        $this->releaseAddonExportLocks();

        UpdateAddonSettings::run($gameVersion, ['counted_tag_ids' => [$tag->id]]);

        Queue::assertPushed(BuildExportFile::class, 2);
        Queue::assertPushed(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $gameVersion->id);
        Queue::assertPushed(BuildExportFile::class, fn (BuildExportFile $job) => $job->gameVersionId === $sibling->id);
    }

    #[Test]
    public function it_schedules_nothing_when_nothing_changed(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->fetchableRoster()->forGuild($guild)->create();
        $councillor = Character::factory()->forGameVersion($gameVersion)->lootCouncillor()->withUniqueName()->create();
        $rank = GuildRank::factory()->for($gameVersion)->create();
        $tag = GuildTag::factory()->forGuild($guild)->countsAttendance()->create();
        $this->releaseAddonExportLocks();

        UpdateAddonSettings::run($gameVersion, [
            'councillor_ids' => [$councillor->id],
            'counted_rank_ids' => [$rank->id],
            'counted_tag_ids' => [$tag->id],
        ]);

        Queue::assertNotPushed(BuildExportFile::class);
    }
}
