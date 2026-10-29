<?php

namespace Tests\Feature\Models;

use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_a_game_version(): void
    {
        $version = GameVersion::factory()->create();
        $item = Item::factory()->create(['game_version_id' => $version->id]);

        $this->assertTrue($item->gameVersion->is($version));
    }

    #[Test]
    public function wowhead_url_uses_blizzard_id_not_the_uuid_primary_key(): void
    {
        $item = Item::factory()->create(['blizzard_id' => 19019, 'name' => 'Thunderfury, Blessed Blade of the Windseeker']);

        $this->assertStringContainsString('item=19019', $item->wowhead_url);
        $this->assertStringNotContainsString("item={$item->id}", $item->wowhead_url);
    }

    #[Test]
    public function wowhead_url_falls_back_to_tbc_when_no_game_version_is_set(): void
    {
        $item = Item::factory()->create(['blizzard_id' => 19019, 'name' => 'Thunderfury', 'game_version_id' => null]);

        $this->assertStringContainsString('/tbc/item=19019', $item->wowhead_url);
    }

    #[Test]
    public function phases_returns_the_distinct_phases_of_the_items_raids(): void
    {
        $item = Item::factory()->create();
        $raid = Raid::factory()->create();
        $item->raids()->attach($raid);

        $this->assertTrue($item->phases->contains('id', $raid->phase_id));
    }
}
