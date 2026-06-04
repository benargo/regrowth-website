<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\DailyQuestType;
use App\Enums\Instance;
use App\Http\Resources\DailyQuestResource;
use App\Models\DailyQuest;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyQuestResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function it_returns_the_core_attributes(): void
    {
        $quest = DailyQuest::factory()->cooking()->create(['name' => 'Soup for the Soul']);

        $array = (new DailyQuestResource($quest))->toArray(new Request);

        $this->assertSame($quest->id, $array['id']);
        $this->assertSame('Soup for the Soul', $array['name']);
        $this->assertSame(DailyQuestType::Cooking->value, $array['type']);
        $this->assertNull($array['instance']);
    }

    #[Test]
    public function it_suffixes_the_label_with_the_instance_for_dungeon_quests(): void
    {
        $quest = DailyQuest::factory()->create([
            'name' => 'Clear the Way',
            'type' => DailyQuestType::Dungeon,
            'instance' => Instance::Arcatraz,
        ]);

        $array = (new DailyQuestResource($quest))->toArray(new Request);

        $this->assertSame('Clear the Way (The Arcatraz)', $array['label']);
        $this->assertSame(Instance::Arcatraz->value, $array['instance']);
    }

    #[Test]
    public function it_suffixes_the_label_with_the_instance_for_heroic_quests(): void
    {
        $quest = DailyQuest::factory()->create([
            'name' => 'Heroic Deeds',
            'type' => DailyQuestType::Heroic,
            'instance' => Instance::Steamvault,
        ]);

        $array = (new DailyQuestResource($quest))->toArray(new Request);

        $this->assertSame('Heroic Deeds (The Steamvault)', $array['label']);
    }

    #[Test]
    public function it_uses_the_plain_name_as_label_for_non_dungeon_quests(): void
    {
        $quest = DailyQuest::factory()->cooking()->create(['name' => 'Soup for the Soul']);

        $array = (new DailyQuestResource($quest))->toArray(new Request);

        $this->assertSame('Soup for the Soul', $array['label']);
    }

    #[Test]
    public function it_returns_a_signed_icon_url_when_media_is_attached(): void
    {
        $quest = DailyQuest::factory()->cooking()->create();
        $quest->addMediaFromString('BINARY')
            ->usingFileName('inv_misc_food_15.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $array = (new DailyQuestResource($quest))->toArray(new Request);

        $this->assertNotNull($array['icon']);
        $this->assertStringContainsString('inv_misc_food_15.jpg', $array['icon']);
        $this->assertTrue(URL::hasValidSignature(request()->create($array['icon'])));
    }

    #[Test]
    public function it_returns_null_icon_when_no_media_attached(): void
    {
        $quest = DailyQuest::factory()->cooking()->create();

        $array = (new DailyQuestResource($quest))->toArray(new Request);

        $this->assertNull($array['icon']);
    }

    #[Test]
    public function it_includes_rewards_when_loaded(): void
    {
        $quest = DailyQuest::factory()->cooking()->create();
        $item = Item::factory()->create();
        $quest->rewards()->attach($item->id, ['quantity' => 2]);
        $quest->load('rewards');

        $array = (new DailyQuestResource($quest))->toArray(new Request);

        $this->assertCount(1, $array['rewards']);
    }

    #[Test]
    public function it_excludes_rewards_when_not_loaded(): void
    {
        $quest = DailyQuest::factory()->cooking()->create();
        $item = Item::factory()->create();
        $quest->rewards()->attach($item->id, ['quantity' => 2]);

        $array = (new DailyQuestResource($quest))->resolve(new Request);

        $this->assertArrayNotHasKey('rewards', $array);
    }
}
