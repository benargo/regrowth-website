<?php

namespace Tests\Feature\Actions\Raid;

use App\Actions\Raid\ListPhasesWithRaids;
use App\Actions\Raid\UpdateRaid;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ListPhasesWithRaidsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_lists_the_versions_phases_that_have_raids_in_release_order(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $later = Phase::factory()->forGameVersion($gameVersion)->create(['number' => 2, 'start_date' => '2026-05-01 00:00:00']);
        $earlier = Phase::factory()->forGameVersion($gameVersion)->create(['number' => 1, 'start_date' => '2026-01-01 00:00:00']);
        Phase::factory()->forGameVersion($gameVersion)->create(['number' => 3, 'start_date' => '2026-09-01 00:00:00']);
        Raid::factory()->for($later)->create();
        Raid::factory()->for($earlier)->create();
        Raid::factory()->for(Phase::factory()->forGameVersion(GameVersion::factory()->create()))->create();

        $phases = ListPhasesWithRaids::run($gameVersion);

        $this->assertSame([$earlier->id, $later->id], $phases->pluck('id')->all());
    }

    #[Test]
    public function it_orders_each_phases_raids_by_name(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->forGameVersion($gameVersion)->create();
        Raid::factory()->for($phase)->create(['name' => 'Tempest Keep']);
        Raid::factory()->for($phase)->create(['name' => 'Serpentshrine Cavern']);

        $phases = ListPhasesWithRaids::run($gameVersion);

        $this->assertSame(['Serpentshrine Cavern', 'Tempest Keep'], $phases->first()->raids->pluck('name')->all());
    }

    #[Test]
    public function it_loads_each_raids_media(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create();

        $phases = ListPhasesWithRaids::run($gameVersion);

        $this->assertTrue($phases->first()->raids->every->relationLoaded('media'));
    }

    #[Test]
    public function it_serves_later_runs_from_the_cache(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create(['color' => 'ff8800']);
        $raid->addMedia(UploadedFile::fake()->image('banner.png'))
            ->withCustomProperties(['position' => 'top'])
            ->toMediaCollection(Raid::MEDIA_BANNER);
        ListPhasesWithRaids::run($gameVersion);

        DB::enableQueryLog();
        $listed = ListPhasesWithRaids::run($gameVersion)->sole()->raids->sole();

        $this->assertSame([], DB::getQueryLog());
        $this->assertTrue($listed->exists);
        $this->assertSame('ff8800', $listed->color);
        $this->assertSame($raid->banner_url, $listed->banner_url);
        $this->assertSame('top', $listed->banner_position);
    }

    // ==================== cache invalidation ====================

    #[Test]
    public function it_refreshes_the_cache_when_a_phase_is_updated(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $first = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);
        $second = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-05-01 00:00:00']);
        Raid::factory()->for($first)->create();
        Raid::factory()->for($second)->create();
        ListPhasesWithRaids::run($gameVersion);

        $first->update(['start_date' => '2026-09-01 00:00:00']);

        $this->assertSame([$second->id, $first->id], ListPhasesWithRaids::run($gameVersion)->pluck('id')->all());
    }

    #[Test]
    public function it_refreshes_the_cache_when_a_raid_is_saved(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create(['name' => 'Karazhan']);
        ListPhasesWithRaids::run($gameVersion);

        $raid->update(['name' => 'Gruul\'s Lair']);

        $this->assertSame('Gruul\'s Lair', ListPhasesWithRaids::run($gameVersion)->sole()->raids->sole()->name);
    }

    #[Test]
    public function it_refreshes_the_cache_when_a_raid_banner_is_added(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->create();
        ListPhasesWithRaids::run($gameVersion);

        UpdateRaid::run($raid, ['banner' => UploadedFile::fake()->image('banner.png')]);

        $this->assertNotNull(ListPhasesWithRaids::run($gameVersion)->sole()->raids->sole()->banner_url);
    }

    #[Test]
    public function it_refreshes_the_cache_when_a_raid_banner_is_removed(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->withBanner()->create();
        ListPhasesWithRaids::run($gameVersion);

        UpdateRaid::run($raid, ['banner' => null]);

        $this->assertNull(ListPhasesWithRaids::run($gameVersion)->sole()->raids->sole()->banner_url);
    }

    #[Test]
    public function it_refreshes_the_cache_when_a_raid_banner_is_repositioned(): void
    {
        Storage::fake('public');
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($gameVersion))->withBanner('top')->create();
        ListPhasesWithRaids::run($gameVersion);

        UpdateRaid::run($raid, ['banner_position' => 'bottom']);

        $this->assertSame('bottom', ListPhasesWithRaids::run($gameVersion)->sole()->raids->sole()->banner_position);
    }
}
