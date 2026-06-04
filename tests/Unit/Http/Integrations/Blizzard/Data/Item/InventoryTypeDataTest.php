<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Item;

use App\Http\Integrations\Blizzard\Data\Item\InventoryTypeData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryTypeDataTest extends TestCase
{
    #[Test]
    public function it_casts_api_response(): void
    {
        $dto = InventoryTypeData::from([
            'type' => 'WEAPON',
            'name' => 'One-Hand',
        ]);

        $this->assertSame('WEAPON', $dto->type);
        $this->assertSame('One-Hand', $dto->name);
    }
}
