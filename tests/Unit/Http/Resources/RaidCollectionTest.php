<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\RaidBackground;
use App\Http\Resources\RaidCollection;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class RaidCollectionTest extends TestCase
{
    use RefreshDatabase;

    // ==================== toArray ====================

    #[Test]
    public function it_returns_data_key_with_collection(): void
    {
        $raids = collect([
            Raid::factory()->create(),
            Raid::factory()->create(),
        ]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertArrayHasKey('data', $array);
        $this->assertCount(2, $array['data']);
    }

    #[Test]
    public function it_returns_background_key(): void
    {
        $raids = collect([
            Raid::factory()->withBackground(RaidBackground::Karazhan)->create(),
        ]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertArrayHasKey('background', $array);
        $this->assertIsString($array['background']);
    }

    #[Test]
    public function it_returns_empty_data_for_empty_collection(): void
    {
        $array = (new RaidCollection(collect()))->toArray(new Request);

        $this->assertArrayHasKey('data', $array);
        $this->assertCount(0, $array['data']);
    }

    #[Test]
    public function it_returns_raids_with_expected_attributes(): void
    {
        $raid1 = Raid::factory()->create(['name' => 'Raid 1']);
        $raid2 = Raid::factory()->create(['name' => 'Raid 2']);
        $raids = collect([$raid1, $raid2]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertCount(2, $array['data']);
        $this->assertSame('Raid 1', $array['data'][0]['name']);
        $this->assertSame('Raid 2', $array['data'][1]['name']);
    }

    // ==================== Background Determination ====================

    #[Test]
    public function it_returns_karazhan_background_when_first_raid_has_karazhan_background(): void
    {
        $raid = Raid::factory()->withBackground(RaidBackground::Karazhan)->create();
        $raids = collect([$raid]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertSame(RaidBackground::Karazhan->value, $array['background']);
    }

    #[Test]
    public function it_returns_gruul_magtheridon_background_when_first_raid_has_gruul_magtheridon_background(): void
    {
        $raid = Raid::factory()->withBackground(RaidBackground::GruulAndMagtheridon)->create();
        $raids = collect([$raid]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertSame(RaidBackground::GruulAndMagtheridon->value, $array['background']);
    }

    #[Test]
    public function it_returns_serpentshrine_cavern_background_when_first_raid_has_ssc_background(): void
    {
        $raid = Raid::factory()->withBackground(RaidBackground::SerpentshrineCavern)->create();
        $raids = collect([$raid]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertSame(RaidBackground::SerpentshrineCavern->value, $array['background']);
    }

    #[Test]
    public function it_returns_tempest_keep_background_when_first_raid_has_tempest_keep_background(): void
    {
        $raid = Raid::factory()->withBackground(RaidBackground::TempestKeep)->create();
        $raids = collect([$raid]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertSame(RaidBackground::TempestKeep->value, $array['background']);
    }

    #[Test]
    public function it_uses_background_from_first_raid_when_multiple_raids_in_collection(): void
    {
        $raid1 = Raid::factory()->withBackground(RaidBackground::GruulAndMagtheridon)->create();
        $raid2 = Raid::factory()->withBackground(RaidBackground::SerpentshrineCavern)->create();
        $raids = collect([$raid1, $raid2]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertSame(RaidBackground::GruulAndMagtheridon->value, $array['background']);
    }

    #[Test]
    public function it_returns_null_background_when_first_raid_has_no_background(): void
    {
        $raid = Raid::factory()->create(['background_css_class' => null]);
        $raids = collect([$raid]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertNull($array['background']);
    }

    #[Test]
    public function it_returns_null_background_for_empty_collection(): void
    {
        $array = (new RaidCollection(collect()))->toArray(new Request);

        $this->assertNull($array['background']);
    }

    // ==================== Data Structure ====================

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $raid = Raid::factory()->create();
        $raids = collect([$raid]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('background', $array);
        $this->assertCount(2, array_keys($array));
    }

    #[Test]
    public function it_preserves_raid_order(): void
    {
        $raid1 = Raid::factory()->create(['name' => 'First']);
        $raid2 = Raid::factory()->create(['name' => 'Second']);
        $raid3 = Raid::factory()->create(['name' => 'Third']);
        $raids = collect([$raid1, $raid2, $raid3]);

        $array = (new RaidCollection($raids))->toArray(new Request);

        $this->assertSame('First', $array['data'][0]['name']);
        $this->assertSame('Second', $array['data'][1]['name']);
        $this->assertSame('Third', $array['data'][2]['name']);
    }
}
