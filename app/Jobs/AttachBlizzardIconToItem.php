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

        $fileName = (string) Str::of($this->assetUrl)->afterLast('/')->before('?');
        $body = $renderConnector->send(new FetchAssetRequest($this->assetUrl))->body();

        $item->addMediaFromString($body)
            ->usingFileName($fileName)
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');
    }
}
