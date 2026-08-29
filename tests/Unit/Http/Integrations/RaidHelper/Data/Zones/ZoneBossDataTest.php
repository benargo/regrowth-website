<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Data\Zones;

use App\Http\Integrations\RaidHelper\Data\Zones\ZoneBossData;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class ZoneBossDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_a_boss_from_a_minimal_payload(): void
    {
        $boss = ZoneBossData::validateAndCreate([
            'id' => 10,
            'name' => 'Lucifron',
        ]);

        $this->assertSame(10, $boss->id);
        $this->assertSame('Lucifron', $boss->name);
        $this->assertNull($boss->order);
    }

    #[Test]
    public function it_retains_order_when_provided(): void
    {
        $boss = ZoneBossData::validateAndCreate([
            'id' => 10,
            'name' => 'Lucifron',
            'order' => 5,
        ]);

        $this->assertSame(5, $boss->order);
    }

    #[Test]
    public function it_accepts_a_null_order_explicitly(): void
    {
        $boss = ZoneBossData::validateAndCreate([
            'id' => 10,
            'name' => 'Lucifron',
            'order' => null,
        ]);

        $this->assertNull($boss->order);
    }

    #[Test]
    public function it_rejects_a_missing_id(): void
    {
        $this->expectException(ValidationException::class);

        ZoneBossData::validateAndCreate(['name' => 'Lucifron']);
    }

    #[Test]
    public function it_rejects_a_missing_name(): void
    {
        $this->expectException(ValidationException::class);

        ZoneBossData::validateAndCreate(['id' => 10]);
    }

    #[Test]
    public function it_rejects_a_non_integer_id(): void
    {
        $this->expectException(ValidationException::class);

        ZoneBossData::validateAndCreate(['id' => 'ten', 'name' => 'Lucifron']);
    }

    #[Test]
    public function it_rejects_a_non_integer_order(): void
    {
        $this->expectException(ValidationException::class);

        ZoneBossData::validateAndCreate([
            'id' => 10,
            'name' => 'Lucifron',
            'order' => 'first',
        ]);
    }

    #[Test]
    public function it_allows_every_declared_property_together(): void
    {
        $boss = ZoneBossData::validateAndCreate([
            'id' => 10,
            'name' => 'Lucifron',
            'order' => 1,
        ]);

        $this->assertSame(10, $boss->id);
        $this->assertSame('Lucifron', $boss->name);
        $this->assertSame(1, $boss->order);
    }
}
