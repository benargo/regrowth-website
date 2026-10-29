<?php

namespace Tests\Feature\Raiding;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
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
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('loot')]
class RaidTrashPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    #[DataProvider('sections')]
    public function it_renders_the_trash_page_and_defers_the_items(string $section): void
    {
        $raid = Raid::factory()->create();

        $response = $this->get(route("{$section}.raids.trash", ['raid' => $raid, 'slug' => $raid->slug]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raids/Trash')
            ->where('section', $section)
            ->where('raid.data.id', $raid->id)
            ->where('priorityWeightThreshold', 2)
            ->missing('items')
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
    public function it_loads_trash_items_with_comment_counts_and_priorities(): void
    {
        $raid = Raid::factory()->create();
        $trash = Item::factory()->trashDrop()->inRaids([$raid])->create();
        Item::factory()->fromBoss(Boss::factory()->for($raid)->create())->create();
        $priority = LootPriority::factory()->create();
        $trash->priorities()->attach($priority->id, ['weight' => 100]);
        Comment::factory()->count(2)->for($trash, 'commentable')->create();

        $response = $this->get(route('loot.raids.trash', ['raid' => $raid, 'slug' => $raid->slug]));

        $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('items.data', 1)
            ->where('items.data.0.id', $trash->id)
            ->where('items.data.0.comments_count', 2)
            ->where('items.data.0.priorities.0.id', $priority->id)
        ));
    }

    #[Test]
    public function a_cross_raid_trash_item_is_listed_under_both_raids(): void
    {
        $hyjal = Raid::factory()->create(['name' => 'Hyjal Summit']);
        $blackTemple = Raid::factory()->create(['name' => 'Black Temple']);
        $item = Item::factory()->trashDrop()->inRaids([$hyjal, $blackTemple])->create();

        foreach ([$hyjal, $blackTemple] as $raid) {
            $this->get(route('loot.raids.trash', ['raid' => $raid, 'slug' => $raid->slug]))
                ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('items.data.0.id', $item->id)
                ));
        }
    }

    #[Test]
    public function it_loads_trash_items_without_lazy_loading(): void
    {
        Storage::fake('s3');
        $raid = Raid::factory()->create();
        $items = Item::factory()->count(2)->trashDrop()->inRaids([$raid])->create(['game_version_id' => GameVersion::factory()->create()->id]);
        $priority = LootPriority::factory()->create();
        $priority->addMediaFromString('BINARY')->usingFileName('inv_priority.jpg')->toMediaCollection('blizzard_icons');

        foreach ($items as $item) {
            $item->addMediaFromString('BINARY')->usingFileName("inv_{$item->id}.jpg")->toMediaCollection('blizzard_icons');
            $item->priorities()->attach($priority->id, ['weight' => 50]);
        }

        config(['media-library.force_lazy_loading' => false]);
        Model::preventLazyLoading();

        try {
            $response = $this->withoutExceptionHandling()->get(route('loot.raids.trash', ['raid' => $raid, 'slug' => $raid->slug]));

            $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
                ->whereNot('items.data.0.icon', null)
                ->whereNot('items.data.1.priorities.0.media', null)
            ));
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    #[Test]
    public function it_redirects_a_wrong_slug_to_the_canonical_url(): void
    {
        $raid = Raid::factory()->create(['name' => 'Black Temple']);

        $response = $this->get(route('raiding.raids.trash', ['raid' => $raid, 'slug' => 'wrong']));

        $response->assertStatus(303);
        $response->assertRedirect(route('raiding.raids.trash', ['raid' => $raid, 'slug' => 'black-temple']));
    }

    #[Test]
    public function opening_another_versions_trash_page_switches_to_that_version(): void
    {
        $raidVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $sessionVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
        $raid = Raid::factory()->for(Phase::factory()->forGameVersion($raidVersion))->create();

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $sessionVersion->slug])
            ->get(route('loot.raids.trash', ['raid' => $raid, 'slug' => $raid->slug]));

        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $raidVersion->slug);
    }
}
