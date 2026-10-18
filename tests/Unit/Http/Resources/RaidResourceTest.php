<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\RaidResource;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\Item;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class RaidResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_id(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame($raid->id, $array['id']);
    }

    // ==================== name and slug ====================

    #[Test]
    public function it_returns_name(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame('Karazhan', $array['name']);
    }

    // ==================== difficulty and player count ====================

    #[Test]
    public function it_returns_difficulty(): void
    {
        $raid = Raid::factory()->heroic()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame('Heroic', $array['difficulty']);
    }

    #[Test]
    public function it_returns_max_players(): void
    {
        $raid = Raid::factory()->twentyFivePlayer()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame(25, $array['max_players']);
    }

    // ==================== phase relation ====================

    #[Test]
    public function it_includes_phase_number_when_phase_loaded(): void
    {
        $raid = Raid::factory()->create();
        $raid->load('phase');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('phase_number', $array);
        $this->assertSame($raid->phase->number, $array['phase_number']);
    }

    #[Test]
    public function it_excludes_phase_number_when_phase_not_loaded(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('phase_number', $array);
    }

    // ==================== bosses relation ====================

    #[Test]
    public function it_includes_bosses_when_loaded(): void
    {
        $raid = Raid::factory()->withBosses(3)->create();
        $raid->load('bosses');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('bosses', $array);
        $this->assertCount(3, $array['bosses']);
    }

    #[Test]
    public function it_excludes_bosses_when_not_loaded(): void
    {
        $raid = Raid::factory()->withBosses(3)->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('bosses', $array);
    }

    // ==================== slug field ====================

    #[Test]
    public function it_returns_slug(): void
    {
        $raid = Raid::factory()->create(['name' => 'Karazhan']);

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('slug', $array);
        $this->assertSame($raid->slug, $array['slug']);
    }

    // ==================== max loot councillors ====================

    #[Test]
    public function it_returns_max_loot_councillors_when_set(): void
    {
        $raid = Raid::factory()->withLootCouncillors(3)->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame(3, $array['max_loot_councillors']);
    }

    #[Test]
    public function it_returns_null_for_max_loot_councillors_when_not_set(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertNull($array['max_loot_councillors']);
    }

    // ==================== color and banner ====================

    #[Test]
    public function it_returns_color(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('color', $array);
        $this->assertSame($raid->color, $array['color']);
    }

    #[Test]
    public function it_returns_the_banner_url_and_position(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner('center top')->create()->load('media');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertSame($raid->getFirstMediaUrl(Raid::MEDIA_BANNER, Raid::MEDIA_CONVERSION_HEADER), $array['banner_url']);
        $this->assertSame('center top', $array['banner_position']);
    }

    #[Test]
    public function it_returns_null_banner_fields_without_a_banner(): void
    {
        $raid = Raid::factory()->create()->load('media');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('banner_url', $array);
        $this->assertArrayHasKey('banner_position', $array);
        $this->assertNull($array['banner_url']);
        $this->assertNull($array['banner_position']);
        $this->assertArrayNotHasKey('background', $array);
    }

    #[Test]
    public function it_excludes_banner_fields_when_media_is_not_loaded(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner('center top')->create()->fresh();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('banner_url', $array);
        $this->assertArrayNotHasKey('banner_position', $array);
    }

    // ==================== has trash items ====================

    #[Test]
    public function it_returns_true_for_has_trash_items_when_trash_items_exist(): void
    {
        $raid = Raid::factory()->withItems(2)->create();
        $raid->loadExists('trashItems');

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayHasKey('has_trash_items', $array);
        $this->assertTrue($array['has_trash_items']);
    }

    #[Test]
    public function it_returns_false_for_has_trash_items_when_no_trash_items_exist(): void
    {
        $raid = Raid::factory()->create();
        $raid->loadExists('trashItems');

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayHasKey('has_trash_items', $array);
        $this->assertFalse($array['has_trash_items']);
    }

    #[Test]
    public function it_excludes_has_trash_items_when_exists_not_loaded(): void
    {
        $raid = Raid::factory()->withItems(2)->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('has_trash_items', $array);
    }

    // ==================== trash comments count ====================

    #[Test]
    public function it_returns_trash_comments_count_when_counted(): void
    {
        $raid = Raid::factory()->create();
        $raid->trash_comments_count = Comment::query()
            ->where('commentable_type', Item::class)
            ->whereIn('commentable_id', $raid->trashItems()->select('items.id'))
            ->count();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayHasKey('trash_comments_count', $array);
        $this->assertSame((int) $raid->trash_comments_count, $array['trash_comments_count']);
    }

    #[Test]
    public function it_excludes_trash_comments_count_when_not_counted(): void
    {
        $raid = Raid::factory()->create();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertArrayNotHasKey('trash_comments_count', $array);
    }

    #[Test]
    public function it_counts_only_trash_item_comments_excluding_boss_item_comments(): void
    {
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $trashItem = Item::factory()->trashDrop()->withRaid($raid)->create();
        $bossItem = Item::factory()->fromBoss($boss)->create();

        Comment::factory()->count(3)->create(['commentable_id' => (string) $trashItem->id, 'commentable_type' => Item::class]);
        Comment::factory()->count(5)->create(['commentable_id' => (string) $bossItem->id, 'commentable_type' => Item::class]);

        $raid->trash_comments_count = Comment::query()
            ->where('commentable_type', Item::class)
            ->whereIn('commentable_id', $raid->trashItems()->select('items.id'))
            ->count();

        $array = (new RaidResource($raid))->resolve(new Request);

        $this->assertSame(3, $array['trash_comments_count']);
    }

    // ==================== full resource shape ====================

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $raid = Raid::factory()->create()->load('media');

        $array = (new RaidResource($raid))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('color', $array);
        $this->assertArrayHasKey('banner_url', $array);
        $this->assertArrayHasKey('banner_position', $array);
        $this->assertArrayHasKey('max_players', $array);
        $this->assertArrayHasKey('max_loot_councillors', $array);
    }

    // ==================== scopes ====================

    #[Test]
    public function the_summary_scope_emits_only_the_id_and_name(): void
    {
        Storage::fake('public');
        $raid = Raid::factory()->withBanner()->create()->load('media', 'phase');

        $array = (new RaidResource($raid))->scope('summary')->resolve(new Request);

        $this->assertSame(['id', 'name'], array_keys($array));
    }
}
