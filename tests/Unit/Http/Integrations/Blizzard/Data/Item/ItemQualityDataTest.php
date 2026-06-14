<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Item;

use App\Http\Integrations\Blizzard\Data\Item\ItemQualityData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('blizzard-integration')]
class ItemQualityDataTest extends TestCase
{
    #[Test]
    public function it_casts_api_response(): void
    {
        $dto = ItemQualityData::from([
            'type' => 'LEGENDARY',
            'name' => 'Legendary',
        ]);

        $this->assertSame('LEGENDARY', $dto->type);
        $this->assertSame('Legendary', $dto->name);
    }
}
