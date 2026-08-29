<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Data\Zones;

use App\Http\Integrations\RaidHelper\Data\Zones\ZoneBossData;
use App\Http\Integrations\RaidHelper\Data\Zones\ZoneData;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class ZoneDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_a_zone_with_an_explicit_bosses_array(): void
    {
        $zone = ZoneData::validateAndCreate([
            'id' => 1,
            'name' => 'Molten Core',
            'bosses' => [
                ['id' => 10, 'name' => 'Lucifron'],
                ['id' => 11, 'name' => 'Magmadar'],
            ],
        ]);

        $this->assertSame(1, $zone->id);
        $this->assertSame('Molten Core', $zone->name);
        $this->assertCount(2, $zone->bosses);
        $this->assertInstanceOf(ZoneBossData::class, $zone->bosses[0]);
        $this->assertSame(10, $zone->bosses[0]->id);
        $this->assertSame('Lucifron', $zone->bosses[0]->name);
    }

    #[Test]
    public function a_zone_without_a_bosses_key_leaves_bosses_null(): void
    {
        $zone = ZoneData::validateAndCreate([
            'id' => 1,
            'name' => 'Molten Core',
        ]);

        $this->assertNull($zone->bosses);
    }

    #[Test]
    public function a_zone_with_an_explicit_empty_bosses_array_stays_an_empty_array(): void
    {
        $zone = ZoneData::validateAndCreate([
            'id' => 1,
            'name' => 'Molten Core',
            'bosses' => [],
        ]);

        $this->assertIsArray($zone->bosses);
        $this->assertSame([], $zone->bosses);
    }

    #[Test]
    public function it_rejects_a_zone_with_a_missing_id(): void
    {
        $this->expectException(ValidationException::class);

        ZoneData::validateAndCreate(['name' => 'Molten Core']);
    }

    #[Test]
    public function it_rejects_a_zone_with_a_missing_name(): void
    {
        $this->expectException(ValidationException::class);

        ZoneData::validateAndCreate(['id' => 1]);
    }

    #[Test]
    public function it_rejects_a_boss_with_a_missing_id(): void
    {
        $this->expectException(ValidationException::class);

        ZoneData::validateAndCreate([
            'id' => 1,
            'name' => 'Molten Core',
            'bosses' => [['name' => 'Lucifron']],
        ]);
    }

    #[Test]
    public function order_is_optional_and_defaults_to_null_at_both_levels(): void
    {
        $zone = ZoneData::validateAndCreate([
            'id' => 1,
            'name' => 'Molten Core',
            'bosses' => [['id' => 10, 'name' => 'Lucifron']],
        ]);

        $this->assertNull($zone->order);
        $this->assertNull($zone->bosses[0]->order);
    }

    #[Test]
    public function it_retains_order_at_both_levels_when_provided(): void
    {
        $zone = ZoneData::validateAndCreate([
            'id' => 1,
            'name' => 'Molten Core',
            'order' => 2,
            'bosses' => [['id' => 10, 'name' => 'Lucifron', 'order' => 5]],
        ]);

        $this->assertSame(2, $zone->order);
        $this->assertSame(5, $zone->bosses[0]->order);
    }

    // ==================== collectFromDescription ====================

    #[Test]
    public function it_collects_zones_from_a_description(): void
    {
        $zones = ZoneData::collectFromDescription($this->description([
            ['id' => 1, 'name' => 'Molten Core'],
            ['id' => 2, 'name' => 'Onyxia\'s Lair'],
        ]));

        $this->assertCount(2, $zones);
        $this->assertSame(1, $zones->first()->id);
        $this->assertSame('Onyxia\'s Lair', $zones->last()->name);
    }

    #[Test]
    public function it_returns_an_empty_collection_for_a_null_description(): void
    {
        $this->assertTrue(ZoneData::collectFromDescription(null)->isEmpty());
    }

    #[Test]
    public function it_returns_an_empty_collection_when_the_marker_is_missing(): void
    {
        $json = json_encode([['id' => 1, 'name' => 'Molten Core']]);

        $this->assertTrue(ZoneData::collectFromDescription("Some prose without the marker.\n".$json)->isEmpty());
    }

    #[Test]
    public function it_returns_an_empty_collection_when_the_payload_is_not_json(): void
    {
        $this->assertTrue(ZoneData::collectFromDescription("-# Do not edit below this line...\nnot json at all")->isEmpty());
    }

    #[Test]
    public function it_skips_a_malformed_row_and_keeps_the_rest(): void
    {
        Log::shouldReceive('error')->once();

        $zones = ZoneData::collectFromDescription($this->description([
            ['id' => 1, 'name' => 'Molten Core'],
            ['name' => 'Missing an id'],
            ['id' => 3, 'name' => 'Blackwing Lair'],
        ]));

        $this->assertCount(2, $zones);
        $this->assertSame([1, 3], $zones->pluck('id')->all());
    }

    #[Test]
    public function it_decodes_an_escaped_json_payload(): void
    {
        $json = addslashes(json_encode([['id' => 1, 'name' => 'Molten Core']]));

        $zones = ZoneData::collectFromDescription("-# Do not edit below this line...\n".$json);

        $this->assertCount(1, $zones);
        $this->assertSame('Molten Core', $zones->first()->name);
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, array<string, mixed>>  $zones
     */
    private function description(array $zones): string
    {
        return "-# Do not edit below this line...\n".json_encode($zones);
    }
}
