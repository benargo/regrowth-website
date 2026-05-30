<?php

namespace App\Http\Integrations\Blizzard\Data\Media;

use App\Contracts\Http\Integrations\Blizzard\MirrorsAssets;
use App\Facades\BlizzardAsset;
use App\Facades\BlizzardRenderPath;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;
use Throwable;

#[MapInputName(SnakeCaseMapper::class)]
class AssetData extends Data implements MirrorsAssets
{
    private ?string $resolvedPath = null;

    private bool $resolved = false;

    public function __construct(
        public readonly string $value,
        public readonly Optional|string $key,
        public readonly Optional|int $fileDataId,
    ) {}

    public function mirroredPath(): ?string
    {
        $this->resolve();

        return $this->resolvedPath;
    }

    public function mirroredUrl(): ?string
    {
        $path = $this->mirroredPath();

        if ($path === null) {
            return null;
        }

        return Storage::disk(config('services.blizzard.filesystem', 'public'))->url($path);
    }

    /**
     * Trigger the mirror pipeline once and memoise the resulting disk path.
     * On any failure the path is left null and the exception is reported.
     */
    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;

        try {
            BlizzardAsset::send(new FetchAssetRequest($this->value));
            $this->resolvedPath = BlizzardRenderPath::fromUrl($this->value);
        } catch (Throwable $e) {
            report($e);
            $this->resolvedPath = null;
        }
    }
}
