<?php

namespace Tests\Feature\Raiding;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Phase;
use App\Models\Raid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('loot')]
class BossPageTest extends TestCase
{
    use RefreshDatabase;

    // ==================== rendering ====================

    #[Test]
    #[Group('happy-path')]
    #[DataProvider('sectionTabs')]
    public function it_renders_the_boss_with_its_raid_for_each_section(string $section, string $tab): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes']);

        $response = $this->get(route("{$section}.bosses.show", ['boss' => $boss, 'slug' => 'moroes']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Bosses/Show')
            ->where('section', $section)
            ->where('tab', $tab)
            ->where('boss.data.id', $boss->id)
            ->where('boss.data.raid.id', $boss->raid_id)
            ->where('priorityWeightThreshold', 2)
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sectionTabs(): array
    {
        return ['raiding' => ['raiding', 'strategy'], 'loot' => ['loot', 'items']];
    }

    #[Test]
    public function the_strategy_tab_never_waits_on_items(): void
    {
        $boss = Boss::factory()->create();
        Item::factory()->fromBoss($boss)->create();

        $response = $this->get(route('raiding.bosses.show', ['boss' => $boss, 'slug' => $boss->slug]));

        $response->assertInertia(fn (Assert $page) => $page->missing('items'));
        $this->assertNotContains('items', Arr::flatten($response->viewData('page')['deferredProps'] ?? []));
    }

    #[Test]
    #[DataProvider('itemPages')]
    public function the_items_view_defers_items_with_comment_counts_and_priorities(string $section, array $query): void
    {
        $boss = Boss::factory()->create();
        $item = Item::factory()->fromBoss($boss)->create();
        Item::factory()->trashDrop()->inRaids([$boss->raid])->create();
        $priority = LootPriority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);
        Comment::factory()->count(2)->for($item, 'commentable')->create();

        $response = $this->get(route("{$section}.bosses.show", ['boss' => $boss, 'slug' => $boss->slug] + $query));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'items')
            ->missing('items')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('items.data', 1)
                ->where('items.data.0.id', $item->id)
                ->where('items.data.0.comments_count', 2)
                ->where('items.data.0.priorities.0.id', $priority->id)
            )
        );
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function itemPages(): array
    {
        return [
            'the raiding items tab' => ['raiding', ['tab' => 'items']],
            'the loot page' => ['loot', []],
        ];
    }

    #[Test]
    public function an_unknown_tab_falls_back_to_the_strategy(): void
    {
        $boss = Boss::factory()->create();

        $response = $this->get(route('raiding.bosses.show', ['boss' => $boss, 'slug' => $boss->slug, 'tab' => 'nope']));

        $response->assertInertia(fn (Assert $page) => $page->where('tab', 'strategy'));
    }

    #[Test]
    public function it_loads_the_strategy_and_items_without_lazy_loading(): void
    {
        Storage::fake('public');
        $boss = Boss::factory()->for(Raid::factory()->withBanner())->create();
        $boss->addMedia(UploadedFile::fake()->image('strategy.png'))->toMediaCollection();
        $versionId = GameVersion::factory()->create()->id;
        foreach (['inv_sword_04.jpg', 'inv_axe_02.jpg'] as $file) {
            $item = Item::factory()->fromBoss($boss)->create(['game_version_id' => $versionId]);
            $item->addMediaFromString('BINARY')->usingFileName($file)->toMediaCollection('blizzard_icons');
        }

        config(['media-library.force_lazy_loading' => false]);
        Model::preventLazyLoading();

        try {
            $response = $this->withoutExceptionHandling()->get(route('loot.bosses.show', ['boss' => $boss, 'slug' => $boss->slug]));

            $response->assertInertia(fn (Assert $page) => $page
                ->has('boss.data.images', 1)
                ->whereNot('boss.data.raid.banner_url', null)
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->whereNot('items.data.0.icon', null)
                    ->whereNot('items.data.1.icon', null))
            );
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    // ==================== slugs ====================

    #[Test]
    public function it_redirects_a_missing_slug_and_keeps_the_tab(): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes']);

        $response = $this->get("/raiding/bosses/{$boss->id}?tab=items");

        $response->assertStatus(303);
        $response->assertRedirect(route('raiding.bosses.show', ['boss' => $boss, 'slug' => 'moroes', 'tab' => 'items']));
    }

    #[Test]
    public function it_redirects_a_wrong_slug_to_the_canonical_url(): void
    {
        $boss = Boss::factory()->create(['name' => 'Moroes']);

        $response = $this->get(route('loot.bosses.show', ['boss' => $boss, 'slug' => 'wrong']));

        $response->assertStatus(303);
        $response->assertRedirect(route('loot.bosses.show', ['boss' => $boss, 'slug' => 'moroes']));
    }

    // ==================== session ====================

    #[Test]
    public function it_remembers_the_bosss_raid_for_item_pages(): void
    {
        $boss = Boss::factory()->create();

        $response = $this->get(route('loot.bosses.show', ['boss' => $boss, 'slug' => $boss->slug]));

        $response->assertSessionHas(SharesOriginRaidSession::SESSION_KEY, $boss->raid_id);
    }

    #[Test]
    public function opening_another_versions_boss_switches_to_that_version(): void
    {
        $bossVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $sessionVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
        $boss = Boss::factory()->for(Raid::factory()->for(Phase::factory()->forGameVersion($bossVersion)))->create();

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $sessionVersion->slug])
            ->get(route('raiding.bosses.show', ['boss' => $boss, 'slug' => $boss->slug]));

        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $bossVersion->slug);
    }
}
