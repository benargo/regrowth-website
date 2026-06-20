<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\ItemCollection;
use App\Models\Comment;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class ItemCollectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_count_key(): void
    {
        $items = collect([
            Item::factory()->create(),
            Item::factory()->create(),
        ]);

        $array = (new ItemCollection($items))->toArray(new Request);

        $this->assertSame(2, $array['count']);
    }

    #[Test]
    public function it_returns_data_key(): void
    {
        $items = collect([
            Item::factory()->create(),
            Item::factory()->create(),
        ]);

        $array = (new ItemCollection($items))->toArray(new Request);

        $this->assertArrayHasKey('data', $array);
        $this->assertCount(2, $array['data']);
    }

    #[Test]
    public function it_returns_empty_collection_with_zero_count(): void
    {
        $array = (new ItemCollection(collect()))->toArray(new Request);

        $this->assertSame(0, $array['count']);
        $this->assertCount(0, $array['data']);
    }

    #[Test]
    public function it_returns_comments_count_as_null_when_comments_not_counted(): void
    {
        $items = collect([
            Item::factory()->create(),
            Item::factory()->create(),
        ]);

        $array = (new ItemCollection($items))->toArray(new Request);

        $this->assertNull($array['comments']['count']);
    }

    #[Test]
    public function it_returns_comments_count_summed_across_items_when_counted(): void
    {
        $item1 = Item::factory()->create();
        $item2 = Item::factory()->create();

        Comment::factory()->count(3)->for($item1, 'commentable')->create();
        Comment::factory()->count(3)->for($item2, 'commentable')->create();

        $item1->loadCount('comments');
        $item2->loadCount('comments');

        $items = collect([$item1, $item2]);

        $array = (new ItemCollection($items))->toArray(new Request);

        $this->assertSame(6, $array['comments']['count']);
    }

    #[Test]
    public function it_returns_zero_comments_count_when_counted_but_no_comments_exist(): void
    {
        $item = Item::factory()->create();
        $item->loadCount('comments');

        $items = collect([$item]);

        $array = (new ItemCollection($items))->toArray(new Request);

        $this->assertSame(0, $array['comments']['count']);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $item = Item::factory()->create();
        $items = collect([$item]);

        $array = (new ItemCollection($items))->toArray(new Request);

        $this->assertArrayHasKey('count', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('comments', $array);
        $this->assertArrayHasKey('count', $array['comments']);
        $this->assertCount(1, array_keys($array['comments']));
    }
}
