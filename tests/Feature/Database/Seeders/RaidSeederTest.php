<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Database\Seeders\RaidSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('raiding')]
class RaidSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();

        foreach (range(1, 5) as $phaseId) {
            Phase::factory()->for($gameVersion)->create(['id' => $phaseId]);
        }
    }

    #[Test]
    public function it_seeds_the_raids_with_their_banners_into_an_empty_table(): void
    {
        $this->seed(RaidSeeder::class);

        $this->assertSame(9, Raid::count());
        $this->assertTrue(Raid::find(1)->hasMedia(Raid::MEDIA_BANNER));
        $this->assertSame('center bottom', Raid::find(4)->banner_position);
    }

    #[Test]
    public function it_seeds_no_raids_when_one_fails_to_save(): void
    {
        Raid::created(function (Raid $raid): void {
            if ($raid->id === 5) {
                throw new RuntimeException('Seeding failed.');
            }
        });

        $this->assertThrows(fn () => $this->seed(RaidSeeder::class), RuntimeException::class);

        $this->assertSame(0, Raid::count());
    }

    // ==================== run — existing raids ====================

    #[Test]
    public function it_leaves_an_edited_raid_alone_when_run_again(): void
    {
        $this->seed(RaidSeeder::class);
        Raid::find(1)->update(['name' => 'Karazhan (renamed)', 'max_loot_councillors' => 4]);

        $this->seed(RaidSeeder::class);

        $raid = Raid::find(1);
        $this->assertSame('Karazhan (renamed)', $raid->name);
        $this->assertSame(4, $raid->max_loot_councillors);
    }

    #[Test]
    public function it_does_not_bring_back_a_deleted_raid_when_run_again(): void
    {
        $this->seed(RaidSeeder::class);
        Raid::find(9)->delete();

        $this->seed(RaidSeeder::class);

        $this->assertNull(Raid::find(9));
        $this->assertSame(8, Raid::count());
    }
}
