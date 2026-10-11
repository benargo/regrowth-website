<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\GameVersion;
use App\Models\GuildRank;
use Database\Seeders\GuildRankSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class GuildRankSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_the_ranks_onto_the_resolved_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $this->runSeeder($gameVersion);

        $this->assertSame(10, $gameVersion->guildRanks()->count());
        $this->assertSame(0, GuildRank::whereNull('game_version_id')->count());
    }

    #[Test]
    public function it_is_idempotent_on_a_second_run(): void
    {
        $gameVersion = GameVersion::factory()->create();

        $this->runSeeder($gameVersion);
        $this->runSeeder($gameVersion);

        $this->assertSame(10, GuildRank::count());
    }

    #[Test]
    public function it_leaves_another_game_versions_ranks_alone(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $otherGameVersion = GameVersion::factory()->create();
        $otherRank = GuildRank::factory()->create([
            'game_version_id' => $otherGameVersion->id,
            'sort_order' => 0,
            'name' => 'Guild Master',
        ]);

        $this->runSeeder($gameVersion);

        $this->assertSame('Guild Master', $otherRank->fresh()->name);
        $this->assertSame($otherGameVersion->id, $otherRank->fresh()->game_version_id);
    }

    #[Test]
    public function it_skips_seeding_when_the_game_version_does_not_exist(): void
    {
        $seeder = app(GuildRankSeeder::class);
        $seeder->gameVersionSlug = 'missing-version';

        Model::unguarded(fn (): mixed => $seeder->run());

        $this->assertDatabaseEmpty(GuildRank::class);
    }

    private function runSeeder(GameVersion $gameVersion): void
    {
        $seeder = app(GuildRankSeeder::class);
        $seeder->gameVersionSlug = $gameVersion->slug;

        Model::unguarded(fn (): mixed => $seeder->run());
    }
}
