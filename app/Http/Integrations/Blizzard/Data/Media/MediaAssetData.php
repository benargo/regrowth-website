<?php

namespace App\Http\Integrations\Blizzard\Data\Media;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class MediaAssetData extends Data
{
    public function __construct(
        public readonly string $value,
        public readonly Optional|string $key,
        public readonly Optional|int $fileDataId,
    ) {}
}
