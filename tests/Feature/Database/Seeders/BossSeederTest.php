<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\Boss;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Database\Seeders\BossSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BossSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $phase = Phase::factory()->for(GameVersion::factory())->create();

        foreach (range(1, 9) as $raidId) {
            Raid::factory()->for($phase)->create(['id' => $raidId]);
        }
    }

    // ==================== run ====================

    #[Test]
    public function it_seeds_every_boss_into_an_empty_table_in_encounter_order(): void
    {
        $this->seed(BossSeeder::class);

        $this->assertDatabaseCount('bosses', 51);
        $this->assertSame(
            ["Servants' Quarters", 'Attumen the Huntsman', 'Moroes'],
            Boss::where('raid_id', 1)->ordered()->take(3)->pluck('name')->all(),
        );
    }

    #[Test]
    public function it_leaves_an_edited_boss_alone_when_run_again(): void
    {
        $this->seed(BossSeeder::class);
        Boss::find(3)->update(['name' => 'Moroes the Steward', 'notes' => 'Vanish targets.', 'sort_order' => 9]);

        $this->seed(BossSeeder::class);

        $boss = Boss::find(3);
        $this->assertSame('Moroes the Steward', $boss->name);
        $this->assertSame('Vanish targets.', $boss->notes);
        $this->assertSame(9, $boss->sort_order);
    }

    #[Test]
    public function it_does_not_bring_back_a_deleted_boss(): void
    {
        $this->seed(BossSeeder::class);
        Boss::find(51)->delete();

        $this->seed(BossSeeder::class);

        $this->assertNull(Boss::find(51));
        $this->assertDatabaseCount('bosses', 50);
    }
}
