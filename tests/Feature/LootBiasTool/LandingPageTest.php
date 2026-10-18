<?php

namespace Tests\Feature\LootBiasTool;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Item;
use App\Models\ItemPriority;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function loot_index_allows_unauthenticated_users(): void
    {
        $response = $this->get('/loot');

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function loot_index_allows_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function loot_index_allows_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    #[Test]
    public function loot_index_renders_inertia_page(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Loot/Index'));
    }

    #[Test]
    public function loot_index_passes_raids_with_phase_number_as_props(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->count(2)->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertInertia(fn ($page) => $page
            ->component('Loot/Index')
            ->has('raids.data', 2, fn ($r) => $r
                ->has('name')
                ->has('slug')
                ->has('color')
                ->has('banner_url')
                ->has('banner_position')
                ->has('phase_number')
                ->etc()
            )
        );
    }

    #[Test]
    public function it_lists_raids_without_lazy_loading_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->count(2)->withBanner()->create(['phase_id' => $phase->id]);

        config(['media-library.force_lazy_loading' => false]);
        Model::preventLazyLoading();

        try {
            $response = $this->withoutExceptionHandling()->actingAs($user)->get('/loot');

            $response->assertInertia(fn ($page) => $page->whereNot('raids.data.0.banner_url', null));
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    #[Test]
    public function loot_index_passes_stats_as_props(): void
    {
        $user = User::factory()->member()->create();

        $items = Item::factory()->count(2)->create();

        // Two ItemPriority rows sharing the same item_id + weight but different priority_id,
        // to prove priority_rows_count de-duplicates rather than counting raw pivot rows.
        ItemPriority::factory()->for($items[0])->weight(50)->create();
        ItemPriority::factory()->for($items[0])->weight(50)->create();
        ItemPriority::factory()->for($items[1])->weight(30)->create();

        $commenter1 = User::factory()->create();
        $commenter2 = User::factory()->create();
        $comment1 = Comment::factory()->for($items[0], 'commentable')->create(['user_id' => $commenter1->id]);
        Comment::factory()->for($items[1], 'commentable')->create(['user_id' => $commenter1->id]);
        Comment::factory()->for($items[1], 'commentable')->create(['user_id' => $commenter2->id]);

        CommentReaction::factory()->forComment($comment1)->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertInertia(fn ($page) => $page
            ->component('Loot/Index')
            ->where('stats.items_count', 2)
            ->where('stats.priority_rows_count', 2)
            ->where('stats.comments_count', 3)
            ->where('stats.commenters_count', 2)
            ->where('stats.reactions_count', 1)
        );
    }

    #[Test]
    public function loot_index_stats_are_cached_for_ten_minutes(): void
    {
        $user = User::factory()->member()->create();

        Cache::tags(['lootcouncil'])->flush();
        Item::factory()->count(1)->create();

        $this->actingAs($user)->get('/loot');

        $this->assertTrue(Cache::tags(['lootcouncil'])->has('loot:stats'));

        $cached = Cache::tags(['lootcouncil'])->get('loot:stats');
        $this->assertEquals(1, $cached['items_count']);

        Item::factory()->count(1)->createQuietly();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertInertia(fn ($page) => $page
            ->where('stats.items_count', 1)
        );
    }
}
