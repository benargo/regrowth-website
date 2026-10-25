<?php

namespace Tests\Feature\Raiding;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Enums\SiteSection;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('loot')]
class RaidPageTest extends TestCase
{
    use RefreshDatabase;

    // ==================== rendering ====================

    #[Test]
    #[Group('happy-path')]
    #[DataProvider('sections')]
    public function it_renders_the_raid_and_its_bosses_in_order_for_each_section(string $section): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);
        $second = Boss::factory()->for($raid)->create(['name' => 'Moroes']);
        $first = Boss::factory()->for($raid)->create(['name' => 'Attumen the Huntsman']);
        $first->update(['sort_order' => 1]);
        $second->update(['sort_order' => 2]);

        $response = $this->get(route("{$section}.raids.show", ['raid' => $raid, 'slug' => 'karazhan']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Show')
            ->where('section', $section)
            ->where('features', SiteSection::from($section)->features())
            ->where('raid.data.id', $raid->id)
            ->where('bosses.data.0.id', $first->id)
            ->where('bosses.data.1.id', $second->id)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sections(): array
    {
        return ['raiding' => ['raiding'], 'loot' => ['loot']];
    }

    #[Test]
    public function the_loot_page_counts_comments_per_boss(): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        Comment::factory()->count(2)->for($item, 'commentable')->create();

        $response = $this->get(route('loot.raids.show', ['raid' => $boss->raid, 'slug' => $boss->raid->slug]));

        $response->assertInertia(fn (Assert $page) => $page->where('bosses.data.0.comments_count', 2));
    }

    #[Test]
    public function the_raiding_page_does_not_count_comments(): void
    {
        $boss = Boss::factory()->create();

        $response = $this->get(route('raiding.raids.show', ['raid' => $boss->raid, 'slug' => $boss->raid->slug]));

        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses.data.0.comments_count')
            ->missing('raid.data.trash_comments_count')
        );
    }

    #[Test]
    public function it_flags_bosses_that_have_a_strategy(): void
    {
        $raid = Raid::factory()->create();
        Boss::factory()->for($raid)->create(['notes' => 'Spread out.']);

        $response = $this->get(route('raiding.raids.show', ['raid' => $raid, 'slug' => $raid->slug]));

        $response->assertInertia(fn (Assert $page) => $page->where('bosses.data.0.meta.has_strategy', true));
    }

    #[Test]
    #[DataProvider('trashCases')]
    public function it_flags_whether_the_raid_has_trash_items(bool $hasTrash): void
    {
        $raid = Raid::factory()->create();
        Item::factory()->fromBoss(Boss::factory()->for($raid)->create())->create();

        if ($hasTrash) {
            Item::factory()->trashDrop()->inRaids([$raid])->create();
        }

        $response = $this->get(route('loot.raids.show', ['raid' => $raid, 'slug' => $raid->slug]));

        $response->assertInertia(fn (Assert $page) => $page->where('raid.data.has_trash_items', $hasTrash));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function trashCases(): array
    {
        return ['with trash' => [true], 'without trash' => [false]];
    }

    #[Test]
    public function the_loot_page_counts_trash_comments(): void
    {
        $raid = Raid::factory()->create();
        $item = Item::factory()->trashDrop()->inRaids([$raid])->create();
        Comment::factory()->count(3)->for($item, 'commentable')->create();

        $response = $this->get(route('loot.raids.show', ['raid' => $raid, 'slug' => $raid->slug]));

        $response->assertInertia(fn (Assert $page) => $page->where('raid.data.trash_comments_count', 3));
    }

    #[Test]
    public function it_lists_bosses_without_lazy_loading_media(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner()->create();
        Boss::factory()->for($raid)->count(2)->create()
            ->each(fn (Boss $boss) => $boss->addMedia(UploadedFile::fake()->image('strategy.png'))->toMediaCollection());

        config(['media-library.force_lazy_loading' => false]);
        Model::preventLazyLoading();

        try {
            $response = $this->withoutExceptionHandling()->get(route('loot.raids.show', ['raid' => $raid, 'slug' => $raid->slug]));

            $response->assertInertia(fn (Assert $page) => $page
                ->whereNot('raid.data.banner_url', null)
                ->where('bosses.data.1.meta.has_strategy', true)
            );
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    // ==================== slugs ====================

    #[Test]
    #[DataProvider('sections')]
    public function it_redirects_a_missing_slug_to_the_canonical_url(string $section): void
    {
        $raid = Raid::factory()->create(['name' => 'Black Temple']);

        $response = $this->get("/{$section}/raids/{$raid->id}");

        $response->assertStatus(303);
        $response->assertRedirect(route("{$section}.raids.show", ['raid' => $raid, 'slug' => 'black-temple']));
    }

    #[Test]
    public function it_redirects_a_wrong_slug_to_the_canonical_url(): void
    {
        $raid = Raid::factory()->create(['name' => 'Black Temple']);

        $response = $this->get(route('raiding.raids.show', ['raid' => $raid, 'slug' => 'wrong']));

        $response->assertStatus(303);
        $response->assertRedirect(route('raiding.raids.show', ['raid' => $raid, 'slug' => 'black-temple']));
    }

    // ==================== session ====================

    #[Test]
    public function it_remembers_the_raid_for_item_pages(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->get(route('loot.raids.show', ['raid' => $raid, 'slug' => $raid->slug]));

        $response->assertSessionHas(SharesOriginRaidSession::SESSION_KEY, $raid->id);
    }

    #[Test]
    #[DataProvider('sections')]
    public function opening_another_versions_raid_switches_to_that_version(string $section): void
    {
        $raidVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $sessionVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($raidVersion))->create();

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $sessionVersion->slug])
            ->get(route("{$section}.raids.show", ['raid' => $raid, 'slug' => $raid->slug]));

        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $raidVersion->slug);
        $response->assertInertia(fn (Assert $page) => $page->where('gameVersions.meta.current', $raidVersion->id));
    }

    #[Test]
    public function switching_version_on_a_raid_page_leaves_for_the_raids_index(): void
    {
        $raidVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
        $otherVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($raidVersion))->create();
        $raidUrl = route('raiding.raids.show', ['raid' => $raid, 'slug' => $raid->slug]);
        $this->get($raidUrl);

        $this->from($raidUrl)->patch(route('session.game-version.update'), ['game_version' => $otherVersion->slug]);

        $response = $this->get($raidUrl);

        $response->assertRedirect(route('raiding.raids.index'));
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $otherVersion->slug);
    }
}
