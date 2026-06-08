<?php

namespace App\Jobs;

use App\Contracts\HasBlizzardIcons;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use InvalidArgumentException;

class AttachBlizzardIconToModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public readonly Uri $assetUrl;

    public function __construct(
        public readonly string $modelClass,
        public readonly int|string $modelKey,
        Uri|string $assetUrl,
    ) {
        if (! is_a($this->modelClass, HasBlizzardIcons::class, true)) {
            throw new InvalidArgumentException("{$this->modelClass} must implement HasBlizzardIcons.");
        }

        $this->assetUrl = $assetUrl instanceof Uri ? $assetUrl : Uri::of($assetUrl);
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('blizzard-icon:'.$this->getIconName($this->assetUrl)))
                ->releaseAfter(60),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [300, 300];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard', 'model:'.class_basename($this->modelClass).':'.$this->modelKey];
    }

    public function handle(RenderConnector $renderConnector): void
    {
        $model = ($this->modelClass)::findOrFail($this->modelKey);

        if ($model->hasMedia('blizzard_icons')) {
            return;
        }

        $url = $this->retailAssetUrl();
        $fileName = $this->getIconName($url);
        $body = $renderConnector->send(new FetchIconRequest($url))->body();

        $model->addMediaFromString($body)
            ->usingFileName($fileName)
            ->withCustomProperties(['size' => HasBlizzardIcons::DEFAULT_MEDIA_SIZE])
            ->toMediaCollection('blizzard_icons');
    }

    /**
     * Rewrite the asset URL to the Retail-region equivalent by stripping the
     * "classicann-" prefix from the region path segment (e.g. "classicann-eu" → "eu").
     * The seeder dispatches this job only after a classicann 403, so Retail is always correct.
     */
    public function retailAssetUrl(): Uri
    {
        $retailPath = preg_replace('#^/classicann-#', '/', Str::start((string) $this->assetUrl->path(), '/'));

        return Uri::of('https://render.worldofwarcraft.com')->withPath($retailPath);
    }

    /**
     * Extract the icon file name from the URL for use as the media's file_name.
     * This assumes the URL is well-formed and that the file name is the last segment of the path, which is consistent with Blizzard CDN URLs.
     */
    private function getIconName(Uri $url): string
    {
        return (string) Str::of((string) $url->path())->afterLast('/');
    }
}
