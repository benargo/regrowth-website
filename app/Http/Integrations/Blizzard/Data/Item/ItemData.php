<?php

namespace App\Http\Integrations\Blizzard\Data\Item;

use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class ItemData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ItemQualityData $quality,
        public readonly int $level,
        public readonly int $requiredLevel,
        public readonly LinkData $media,
        public readonly LinkData $itemClass,
        public readonly LinkData $itemSubclass,
        public readonly InventoryTypeData $inventoryType,
        public readonly int $purchasePrice,
        public readonly int $sellPrice,
        public readonly Optional|int $maxCount,
        public readonly Optional|bool $isEquippable,
        public readonly Optional|bool $isStackable,
    ) {}
}
