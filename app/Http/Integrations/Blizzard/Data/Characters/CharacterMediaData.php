<?php

namespace App\Http\Integrations\Blizzard\Data\Characters;

use App\Http\Integrations\Blizzard\Data\Media\AssetData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class CharacterMediaData extends Data
{
    /**
     * @param  array<int, AssetData>  $assets
     */
    public function __construct(
        public readonly LinkData $character,
        #[DataCollectionOf(AssetData::class)]
        public readonly array $assets,
    ) {}
}
