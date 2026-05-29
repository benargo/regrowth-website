<?php

namespace App\Http\Integrations\Blizzard\Data\Media;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class MediaData extends Data
{
    /**
     * @param  array<int, MediaAssetData>  $assets
     */
    public function __construct(
        public readonly int $id,
        #[DataCollectionOf(MediaAssetData::class)]
        public readonly array $assets,
    ) {}
}
