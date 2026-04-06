<?php

namespace App\Http\Resources\LootCouncil;

use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class PriorityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $blizzard = app(BlizzardService::class);
        $media = app(MediaService::class);
        $iconUrl = $this->getIconUrl($blizzard, $media);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'media' => $iconUrl,
            'weight' => $this->whenPivotLoaded('lootcouncil_item_priorities', fn () => $this->pivot->weight),
        ];
    }

    /**
     * Get icon URL from Blizzard media API or by icon name.
     */
    protected function getIconUrl(BlizzardService $blizzard, MediaService $media): ?string
    {
        try {
            // If media_name is set, fetch icon by name directly
            if (isset($this->media['media_name'])) {
                return $media->get($this->media['media_name']);
            }

            // Otherwise use the API with media_type and media_id
            if (isset($this->media['media_type'], $this->media['media_id'])) {
                $mediaData = $blizzard->findMedia($this->media['media_type'], $this->media['media_id']);
                $assets = Arr::get($mediaData, 'assets', []);

                if (empty($assets)) {
                    return null;
                }

                $urls = $media->get($assets);

                return array_values($urls)[0] ?? null;
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }
}
