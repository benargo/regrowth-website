<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\GameVersion;
use App\Models\GuildRank;
use Database\Seeders\GuildRankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class GuildRankSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_the_ranks_onto_the_tbc_game_version(): void
    {
        $gameVersion = GameVersion::factory()->tbc()->create();

        $this->seed(GuildRankSeeder::class);

        $this->assertSame(10, $gameVersion->guildRanks()->count());
        $this->assertSame(0, GuildRank::whereNull('game_version_id')->count());
    }

    #[Test]
    public function it_is_idempotent_on_a_second_run(): void
    {
        GameVersion::factory()->tbc()->create();

        $this->seed(GuildRankSeeder::class);
        $this->seed(GuildRankSeeder::class);

        $this->assertSame(10, GuildRank::count());
    }

    #[Test]
    public function it_leaves_another_game_versions_ranks_alone(): void
    {
        GameVersion::factory()->tbc()->create();
        $otherGameVersion = GameVersion::factory()->create();
        $otherRank = GuildRank::factory()->create([
            'game_version_id' => $otherGameVersion->id,
            'sort_order' => 0,
            'name' => 'Guild Master',
        ]);

        $this->seed(GuildRankSeeder::class);

        $this->assertSame('Guild Master', $otherRank->fresh()->name);
        $this->assertSame($otherGameVersion->id, $otherRank->fresh()->game_version_id);
    }
}
