<?php

namespace App\Http\Integrations\Blizzard\Data\Media;

use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapInputName(SnakeCaseMapper::class)]
class AssetData extends Data
{
    private ?string $mirroredPath = null;

    public function __construct(
        public readonly string $value,
        public readonly Optional|string $key,
        public readonly Optional|int $fileDataId,
    ) {}

    public function setMirroredPath(string $path): void
    {
        $this->mirroredPath = $path;
    }

    public function mirroredPath(): ?string
    {
        return $this->mirroredPath;
    }

    public function mirroredUrl(): ?string
    {
        if ($this->mirroredPath === null) {
            return null;
        }

        return Storage::disk(config('services.blizzard.filesystem', 'public'))->url($this->mirroredPath);
    }
}
