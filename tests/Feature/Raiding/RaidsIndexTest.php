<?php

namespace Tests\Feature\Raiding;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('loot')]
class RaidsIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    #[DataProvider('sections')]
    public function it_lists_the_session_versions_raids_grouped_by_phase(string $routeName, string $section): void
    {
        $sessionVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
        $phase = Phase::factory()->forGameVersion($sessionVersion)->create();
        $raid = Raid::factory()->for($phase)->create(['name' => 'Karazhan']);
        Raid::factory()->for(Phase::factory())->create(['name' => 'Molten Core']);

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $sessionVersion->slug])->get(route($routeName));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Index')
            ->where('section', $section)
            ->has('phases.data', 1)
            ->where('phases.data.0.id', $phase->id)
            ->has('phases.data.0.raids', 1)
            ->where('phases.data.0.raids.0.id', $raid->id)
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sections(): array
    {
        return [
            'raiding' => ['raiding.raids.index', 'raiding'],
            'loot' => ['loot.raids.index', 'loot'],
        ];
    }

    #[Test]
    public function it_shows_nothing_for_a_version_without_raids(): void
    {
        GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
        Raid::factory()->for(Phase::factory()->forGameVersion(GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')])))->create();

        $response = $this->get(route('raiding.raids.index'));

        $response->assertInertia(fn (Assert $page) => $page->has('phases.data', 0));
    }

    #[Test]
    public function it_shows_nothing_without_any_game_version(): void
    {
        Raid::factory()->for(Phase::factory())->create();

        $response = $this->get(route('loot.raids.index'));

        $response->assertInertia(fn (Assert $page) => $page->has('phases.data', 0));
    }

    #[Test]
    public function it_lists_raids_without_lazy_loading_media(): void
    {
        Storage::fake('s3');
        $gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
        Raid::factory()->count(2)->withBanner()->for(Phase::factory()->forGameVersion($gameVersion))->create();

        config(['media-library.force_lazy_loading' => false]);
        Model::preventLazyLoading();

        try {
            $response = $this->withoutExceptionHandling()->get(route('raiding.raids.index'));

            $response->assertInertia(fn (Assert $page) => $page->whereNot('phases.data.0.raids.0.banner_url', null));
        } finally {
            Model::preventLazyLoading(false);
        }
    }
}
