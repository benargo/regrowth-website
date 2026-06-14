<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\ItemQuality;
use App\Http\Resources\DailyQuestRewardResource;
use App\Models\DailyQuest;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('daily-quests')]
class DailyQuestRewardResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function rewardFor(Item $item): Item
    {
        $quest = DailyQuest::factory()->cooking()->create();
        $quest->rewards()->attach($item->id, ['quantity' => 4]);

        return $quest->fresh('rewards')->rewards->first();
    }

    #[Test]
    public function it_returns_the_item_id_as_id(): void
    {
        $item = Item::factory()->create(['id' => 33844]);

        $array = (new DailyQuestRewardResource($this->rewardFor($item)))->toArray(new Request);

        $this->assertSame(33844, $array['id']);
    }

    #[Test]
    public function it_returns_the_quantity_from_the_pivot(): void
    {
        $item = Item::factory()->create();

        $array = (new DailyQuestRewardResource($this->rewardFor($item)))->toArray(new Request);

        $this->assertSame(4, $array['quantity']);
    }

    #[Test]
    public function it_returns_the_name(): void
    {
        $item = Item::factory()->withName('Barrel of Fish')->create();

        $array = (new DailyQuestRewardResource($this->rewardFor($item)))->toArray(new Request);

        $this->assertSame('Barrel of Fish', $array['name']);
    }

    #[Test]
    public function it_returns_the_quality_name_lowercased(): void
    {
        $item = Item::factory()->withQuality(ItemQuality::EPIC)->create();

        $array = (new DailyQuestRewardResource($this->rewardFor($item)))->toArray(new Request);

        $this->assertSame('epic', $array['quality']);
    }

    #[Test]
    public function it_falls_back_to_common_when_quality_is_null(): void
    {
        $item = Item::factory()->create();
        $reward = $this->rewardFor($item);
        $reward->quality = null;

        $array = (new DailyQuestRewardResource($reward))->toArray(new Request);

        $this->assertSame('common', $array['quality']);
    }

    #[Test]
    public function it_returns_a_signed_icon_url_when_media_is_attached(): void
    {
        $item = Item::factory()->create();
        $item->addMediaFromString('BINARY')
            ->usingFileName('inv_misc_food_15.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $array = (new DailyQuestRewardResource($this->rewardFor($item)))->toArray(new Request);

        $this->assertNotNull($array['icon']);
        $this->assertStringContainsString('inv_misc_food_15.jpg', $array['icon']);
        $this->assertTrue(URL::hasValidSignature(request()->create($array['icon'])));
    }

    #[Test]
    public function it_returns_null_icon_when_no_media_attached(): void
    {
        $item = Item::factory()->create();

        $array = (new DailyQuestRewardResource($this->rewardFor($item)))->toArray(new Request);

        $this->assertNull($array['icon']);
    }

    #[Test]
    public function it_returns_the_wowhead_url(): void
    {
        $item = Item::factory()->create(['id' => 33844]);

        $array = (new DailyQuestRewardResource($this->rewardFor($item)))->toArray(new Request);

        $this->assertStringContainsString('item=33844', $array['wowhead_url']);
    }
}
