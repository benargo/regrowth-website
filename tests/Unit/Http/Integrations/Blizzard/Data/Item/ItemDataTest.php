<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Data\Item;

use App\Http\Integrations\Blizzard\Data\Item\InventoryTypeData;
use App\Http\Integrations\Blizzard\Data\Item\ItemData;
use App\Http\Integrations\Blizzard\Data\Item\ItemQualityData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

class ItemDataTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function sampleApiResponse(): array
    {
        return [
            'id' => 19019,
            'name' => 'Thunderfury, Blessed Blade of the Windseeker',
            'quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary'],
            'level' => 80,
            'required_level' => 60,
            'media' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/media/item/19019'], 'id' => 19019],
            'item_class' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item-class/2'], 'name' => 'Weapon', 'id' => 2],
            'item_subclass' => ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item-class/2/item-subclass/7'], 'name' => 'One-Handed Sword', 'id' => 7],
            'inventory_type' => ['type' => 'WEAPON', 'name' => 'One-Hand'],
            'purchase_price' => 0,
            'sell_price' => 12345,
            'max_count' => 1,
            'is_equippable' => true,
            'is_stackable' => false,
        ];
    }

    #[Test]
    public function it_casts_full_api_response(): void
    {
        $dto = ItemData::from($this->sampleApiResponse());

        $this->assertSame(19019, $dto->id);
        $this->assertSame('Thunderfury, Blessed Blade of the Windseeker', $dto->name);
        $this->assertInstanceOf(ItemQualityData::class, $dto->quality);
        $this->assertSame('LEGENDARY', $dto->quality->type);
        $this->assertSame('Legendary', $dto->quality->name);
        $this->assertSame(80, $dto->level);
        $this->assertSame(60, $dto->requiredLevel);
        $this->assertInstanceOf(LinkData::class, $dto->media);
        $this->assertSame(19019, $dto->media->id);
        $this->assertInstanceOf(LinkData::class, $dto->itemClass);
        $this->assertSame('Weapon', $dto->itemClass->name);
        $this->assertSame(2, $dto->itemClass->id);
        $this->assertInstanceOf(LinkData::class, $dto->itemSubclass);
        $this->assertSame('One-Handed Sword', $dto->itemSubclass->name);
        $this->assertSame(7, $dto->itemSubclass->id);
        $this->assertInstanceOf(InventoryTypeData::class, $dto->inventoryType);
        $this->assertSame('WEAPON', $dto->inventoryType->type);
        $this->assertSame('One-Hand', $dto->inventoryType->name);
        $this->assertSame(0, $dto->purchasePrice);
        $this->assertSame(12345, $dto->sellPrice);
        $this->assertSame(1, $dto->maxCount);
        $this->assertTrue($dto->isEquippable);
        $this->assertFalse($dto->isStackable);
    }

    #[Test]
    public function it_treats_optional_fields_as_optional_when_absent(): void
    {
        $data = $this->sampleApiResponse();
        unset($data['max_count'], $data['is_equippable'], $data['is_stackable']);

        $dto = ItemData::from($data);

        $this->assertInstanceOf(Optional::class, $dto->maxCount);
        $this->assertInstanceOf(Optional::class, $dto->isEquippable);
        $this->assertInstanceOf(Optional::class, $dto->isStackable);
    }

    #[Test]
    public function it_maps_snake_case_fields_correctly(): void
    {
        $dto = ItemData::from($this->sampleApiResponse());

        // These properties require snake_case → camelCase mapping
        $this->assertSame(60, $dto->requiredLevel);
        $this->assertSame(0, $dto->purchasePrice);
        $this->assertSame(12345, $dto->sellPrice);
    }
}
