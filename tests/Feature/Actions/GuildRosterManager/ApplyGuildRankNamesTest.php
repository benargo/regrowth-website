<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\ApplyGuildRankNames;
use App\Jobs\RefreshGuildRosterAfterEditing;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ApplyGuildRankNamesTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake([RefreshGuildRosterAfterEditing::class]);

        $this->version = GameVersion::factory()->create();
    }

    // ==================== renaming ====================

    #[Test]
    public function it_renames_ranks_in_place_keeping_their_ids_and_attendance(): void
    {
        $master = $this->rank(0, 'Guild Master');
        $social = $this->rank(1, 'Social', countAttendance: false);

        $resized = ApplyGuildRankNames::run($this->version, ['Guildmaster', 'Friends & family']);

        $this->assertFalse($resized);
        $this->assertSame([$master->id, $social->id], $this->ordered()->pluck('id')->all());
        $this->assertSame(['Guildmaster', 'Friends & family'], $this->ordered()->pluck('name')->all());
        $this->assertSame([true, false], $this->ordered()->pluck('count_attendance')->all());
        Bus::assertNotDispatched(RefreshGuildRosterAfterEditing::class);
    }

    #[Test]
    public function it_keeps_the_casing_grm_sends(): void
    {
        $this->rank(0, 'Guild Master');

        ApplyGuildRankNames::run($this->version, ['guild MASTER']);

        $this->assertSame(['guild MASTER'], $this->ordered()->pluck('name')->all());
    }

    #[Test]
    public function it_trims_the_names_grm_sends(): void
    {
        $master = $this->rank(0, 'Guild Master');
        $this->travel(1)->hour();

        ApplyGuildRankNames::run($this->version, [' Guild Master ', "Officer\t"]);

        $this->assertSame(['Guild Master', 'Officer'], $this->ordered()->pluck('name')->all());
        $this->assertTrue($master->updated_at->equalTo($master->fresh()->updated_at));
    }

    #[Test]
    public function it_rejects_a_blank_name_and_leaves_the_ranks_alone(): void
    {
        $this->rank(0, 'Guild Master');

        try {
            ApplyGuildRankNames::run($this->version, ['Guild Master', '   ']);
            $this->fail('A blank rank name was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame(['Guild Master'], $this->ordered()->pluck('name')->all());
        }
    }

    // ==================== resizing ====================

    #[Test]
    public function it_creates_trailing_ranks_that_count_attendance_when_grm_has_more(): void
    {
        $master = $this->rank(0, 'Guild Master');

        $resized = ApplyGuildRankNames::run($this->version, ['Guild Master', 'Officer', 'Raider']);

        $this->assertTrue($resized);
        $this->assertSame($master->id, $this->ordered()->first()->id);
        $this->assertSame(['Guild Master', 'Officer', 'Raider'], $this->ordered()->pluck('name')->all());
        $this->assertSame([0, 1, 2], $this->ordered()->pluck('sort_order')->all());
        $this->assertSame([true, true, true], $this->ordered()->pluck('count_attendance')->all());
    }

    #[Test]
    public function it_deletes_trailing_ranks_and_clears_their_characters_when_grm_has_fewer(): void
    {
        $this->rank(0, 'Guild Master');
        $raider = $this->rank(1, 'Raider');
        $character = Character::factory()->create(['rank_id' => $raider->id, 'game_version_id' => $this->version->id]);

        $resized = ApplyGuildRankNames::run($this->version, ['Guild Master']);

        $this->assertTrue($resized);
        $this->assertModelMissing($raider);
        $this->assertNull($character->fresh()->rank_id);
    }

    #[Test]
    public function it_does_not_schedule_a_blizzard_roster_refresh_when_resizing(): void
    {
        $this->rank(0, 'Guild Master');
        $this->rank(1, 'Raider');

        ApplyGuildRankNames::run($this->version, ['Guild Master']);
        ApplyGuildRankNames::run($this->version, ['Guild Master', 'Officer', 'Raider']);

        Bus::assertNotDispatched(RefreshGuildRosterAfterEditing::class);
    }

    // ==================== unchanged and isolated ranks ====================

    #[Test]
    public function it_writes_nothing_for_an_unchanged_list(): void
    {
        $master = $this->rank(0, 'Guild Master');
        $this->travel(1)->hour();
        Cache::tags(['attendance'])->put('probe', true);

        $resized = ApplyGuildRankNames::run($this->version, ['Guild Master']);

        $this->assertFalse($resized);
        $this->assertTrue($master->updated_at->equalTo($master->fresh()->updated_at));
        $this->assertTrue(Cache::tags(['attendance'])->has('probe'));
    }

    #[Test]
    public function it_leaves_the_ranks_alone_for_an_empty_list(): void
    {
        $this->rank(0, 'Guild Master');

        $this->assertFalse(ApplyGuildRankNames::run($this->version, []));
        $this->assertSame(['Guild Master'], $this->ordered()->pluck('name')->all());
    }

    #[Test]
    public function it_keeps_duplicate_names_at_their_own_positions(): void
    {
        ApplyGuildRankNames::run($this->version, ['Guild Master', 'Member', 'Member']);

        $this->assertSame(['Guild Master', 'Member', 'Member'], $this->ordered()->pluck('name')->all());
        $this->assertSame([0, 1, 2], $this->ordered()->pluck('sort_order')->all());
    }

    #[Test]
    public function it_aligns_by_sort_order_and_reports_a_resize_across_a_gap(): void
    {
        $master = $this->rank(0, 'Guild Master');
        $officer = $this->rank(1, 'Officer');
        $orphan = $this->rank(3, 'Raider');

        $resized = ApplyGuildRankNames::run($this->version, ['Guild Master', 'Officer', 'Veteran']);

        $this->assertTrue($resized);
        $this->assertModelMissing($orphan);
        $this->assertSame([$master->id, $officer->id], $this->ordered()->take(2)->pluck('id')->all());
        $this->assertSame(['Guild Master', 'Officer', 'Veteran'], $this->ordered()->pluck('name')->all());
    }

    #[Test]
    public function it_leaves_other_game_versions_ranks_alone(): void
    {
        $elsewhere = GuildRank::factory()->for(GameVersion::factory())->create(['sort_order' => 0, 'name' => 'Elsewhere']);

        ApplyGuildRankNames::run($this->version, ['Guild Master']);

        $this->assertSame('Elsewhere', $elsewhere->fresh()->name);
    }

    // ==================== helpers ====================

    private function rank(int $sortOrder, string $name, bool $countAttendance = true): GuildRank
    {
        return GuildRank::factory()->for($this->version)->create([
            'sort_order' => $sortOrder,
            'name' => $name,
            'count_attendance' => $countAttendance,
        ]);
    }

    /**
     * @return Collection<int, GuildRank>
     */
    private function ordered(): Collection
    {
        return $this->version->guildRanks()->ordered()->get();
    }
}
