<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\SyncGuildRanks;
use App\Jobs\RefreshGuildRosterAfterEditing;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
#[Group('characters')]
class SyncGuildRanksTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->gameVersion = GameVersion::factory()->create();
    }

    // ==================== saving ranks ====================

    #[Group('happy-path')]
    #[Test]
    public function it_creates_ranks_numbered_from_zero_in_list_order(): void
    {
        SyncGuildRanks::run($this->gameVersion, [
            ['id' => null, 'name' => 'Guild Master', 'count_attendance' => false],
            ['id' => null, 'name' => 'Raider', 'count_attendance' => true],
        ]);

        $ranks = $this->gameVersion->guildRanks()->ordered()->get();
        $this->assertSame(['Guild Master', 'Raider'], $ranks->pluck('name')->all());
        $this->assertSame([0, 1], $ranks->pluck('sort_order')->all());
        $this->assertSame([false, true], $ranks->pluck('count_attendance')->all());
    }

    #[Test]
    public function it_updates_existing_ranks_in_place_by_id(): void
    {
        $officer = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0, 'name' => 'Officer']);

        SyncGuildRanks::run($this->gameVersion, [
            ['id' => $officer->id, 'name' => 'Officer Alt', 'count_attendance' => false],
        ]);

        $this->assertSame(1, $this->gameVersion->guildRanks()->count());
        $this->assertSame('Officer Alt', $officer->fresh()->name);
        $this->assertFalse($officer->fresh()->count_attendance);
    }

    #[Test]
    public function it_renumbers_ranks_when_they_are_reordered(): void
    {
        $first = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0]);
        $second = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 1]);

        SyncGuildRanks::run($this->gameVersion, [
            ['id' => $second->id, 'name' => $second->name, 'count_attendance' => true],
            ['id' => $first->id, 'name' => $first->name, 'count_attendance' => true],
        ]);

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
        Queue::assertPushed(RefreshGuildRosterAfterEditing::class);
    }

    #[Test]
    public function it_deletes_left_out_ranks_and_clears_them_from_characters(): void
    {
        $kept = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0]);
        $removed = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 1]);
        $character = Character::factory()->create(['rank_id' => $removed->id]);

        SyncGuildRanks::run($this->gameVersion, [
            ['id' => $kept->id, 'name' => $kept->name, 'count_attendance' => true],
        ]);

        $this->assertModelMissing($removed);
        $this->assertNull($character->fresh()->rank_id);
        Queue::assertPushed(RefreshGuildRosterAfterEditing::class, fn (RefreshGuildRosterAfterEditing $job): bool => $job->gameVersion->is($this->gameVersion));
    }

    #[Test]
    public function it_leaves_other_game_versions_ranks_alone(): void
    {
        $otherRank = GuildRank::factory()->for(GameVersion::factory())->create(['sort_order' => 0]);

        SyncGuildRanks::run($this->gameVersion, []);

        $this->assertModelExists($otherRank);
    }

    // ==================== roster refresh and attendance cache ====================

    #[Test]
    public function it_does_not_schedule_a_refresh_for_a_rename_or_attendance_change(): void
    {
        $rank = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0, 'name' => 'Raider']);

        SyncGuildRanks::run($this->gameVersion, [
            ['id' => $rank->id, 'name' => 'Core Raider', 'count_attendance' => false],
        ]);

        Queue::assertNotPushed(RefreshGuildRosterAfterEditing::class);
    }

    #[Test]
    public function it_flushes_the_attendance_cache_when_a_rank_changes(): void
    {
        $rank = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0]);
        Cache::tags(['attendance'])->put('guild_ranks:where_count_attendance', ['stale'], 60);

        SyncGuildRanks::run($this->gameVersion, [
            ['id' => $rank->id, 'name' => $rank->name, 'count_attendance' => false],
        ]);

        $this->assertNull(Cache::tags(['attendance'])->get('guild_ranks:where_count_attendance'));
    }

    #[Test]
    public function it_changes_nothing_when_given_the_ranks_as_they_are(): void
    {
        $rank = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0, 'name' => 'Raider', 'count_attendance' => true]);
        Cache::tags(['attendance'])->put('guild_ranks:where_count_attendance', ['cached'], 60);

        SyncGuildRanks::run($this->gameVersion, [
            ['id' => $rank->id, 'name' => 'Raider', 'count_attendance' => true],
        ]);

        Queue::assertNotPushed(RefreshGuildRosterAfterEditing::class);
        $this->assertSame(['cached'], Cache::tags(['attendance'])->get('guild_ranks:where_count_attendance'));
    }

    // ==================== transactions ====================

    #[Group('error-handling')]
    #[Test]
    public function it_rolls_back_every_change_when_one_rank_cannot_be_found(): void
    {
        $rank = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0]);
        $otherVersionsRank = GuildRank::factory()->for(GameVersion::factory())->create(['sort_order' => 0]);

        try {
            SyncGuildRanks::run($this->gameVersion, [
                ['id' => $otherVersionsRank->id, 'name' => 'Stolen', 'count_attendance' => true],
            ]);
            $this->fail('Expected the unknown rank to be rejected.');
        } catch (ModelNotFoundException) {
            // Expected.
        }

        $this->assertModelExists($rank);
    }

    #[Test]
    public function it_keeps_the_attendance_cache_until_the_surrounding_transaction_commits(): void
    {
        $rank = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0]);
        Cache::tags(['attendance'])->put('guild_ranks:where_count_attendance', ['stale'], 60);

        DB::transaction(function () use ($rank): void {
            SyncGuildRanks::run($this->gameVersion, [
                ['id' => $rank->id, 'name' => $rank->name, 'count_attendance' => false],
            ]);

            $this->assertSame(['stale'], Cache::tags(['attendance'])->get('guild_ranks:where_count_attendance'));
        });

        $this->assertNull(Cache::tags(['attendance'])->get('guild_ranks:where_count_attendance'));
    }

    #[Test]
    public function it_neither_flushes_the_cache_nor_queues_a_refresh_when_the_surrounding_transaction_rolls_back(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake()->except(RefreshGuildRosterAfterEditing::class);
        $rank = GuildRank::factory()->for($this->gameVersion)->create(['sort_order' => 0]);
        Cache::tags(['attendance'])->put('guild_ranks:where_count_attendance', ['cached'], 60);

        try {
            DB::transaction(function () use ($rank): void {
                SyncGuildRanks::run($this->gameVersion, [
                    ['id' => null, 'name' => 'Recruit', 'count_attendance' => false],
                    ['id' => $rank->id, 'name' => $rank->name, 'count_attendance' => false],
                ]);

                throw new \RuntimeException('Roll back.');
            });
        } catch (\RuntimeException) {
            // Expected.
        }

        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame(['cached'], Cache::tags(['attendance'])->get('guild_ranks:where_count_attendance'));
    }
}
