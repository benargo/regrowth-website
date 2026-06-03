<?php

namespace App\Jobs;

use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\Item;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class AttachBlizzardIconToItem implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $itemId,
        public readonly string $assetUrl,
    ) {}

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
        return ['blizzard', "item:{$this->itemId}"];
    }

    public function handle(RenderConnector $renderConnector): void
    {
        $item = Item::findOrFail($this->itemId);

        if ($item->hasMedia('blizzard_icons')) {
            return;
        }

        $url = $this->retailAssetUrl();
        $fileName = (string) Str::of($url)->afterLast('/')->before('?');
        $body = $renderConnector->send(new FetchAssetRequest($url))->body();

        $item->addMediaFromString($body)
            ->usingFileName($fileName)
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');
    }

    /**
     * Rewrite the asset URL to the Retail-region equivalent by stripping the
     * "classicann-" prefix from the region path segment (e.g. "classicann-eu" → "eu").
     * The seeder dispatches this job only after a classicann 403, so Retail is always correct.
     */
    public function retailAssetUrl(): string
    {
        $path = parse_url($this->assetUrl, PHP_URL_PATH);
        $retailPath = preg_replace('#^/classicann-#', '/', $path);

        return 'https://render.worldofwarcraft.com'.$retailPath;
    }
}
