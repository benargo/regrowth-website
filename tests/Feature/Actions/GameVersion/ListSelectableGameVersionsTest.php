<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class ListSelectableGameVersionsTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_lists_every_version_newest_release_first(): void
    {
        $older = GameVersion::factory()->create(['release_date' => now()->subYear()]);
        $unreleased = GameVersion::factory()->create(['release_date' => now()->addMonth()]);
        $latest = GameVersion::factory()->create(['release_date' => now()->subDay()]);

        $this->assertSame(
            [$unreleased->id, $latest->id, $older->id],
            ListSelectableGameVersions::run()->pluck('id')->all()
        );
    }

    #[Test]
    public function it_breaks_release_date_ties_by_id(): void
    {
        $date = now()->subMonth();
        $first = GameVersion::factory()->create(['release_date' => $date]);
        $second = GameVersion::factory()->create(['release_date' => $date]);

        $this->assertSame([$first->id, $second->id], ListSelectableGameVersions::run()->pluck('id')->all());
    }

    #[Test]
    public function it_eager_loads_media(): void
    {
        GameVersion::factory()->create();

        $this->assertTrue(ListSelectableGameVersions::run()->every->relationLoaded('media'));
    }

    #[Test]
    public function it_queries_once_per_request(): void
    {
        GameVersion::factory()->create();

        DB::enableQueryLog();
        ListSelectableGameVersions::run();
        ListSelectableGameVersions::run();

        $versionSelects = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_starts_with($query['query'], 'select') && preg_match('/from [`"]game_versions[`"]/', $query['query']));

        $this->assertCount(1, $versionSelects);
    }
}
