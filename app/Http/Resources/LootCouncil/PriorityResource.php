<?php

namespace App\Http\Resources\LootCouncil;

use App\Services\Blizzard\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriorityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mediaService = app(MediaService::class);
        $iconUrl = $this->getIconUrl($mediaService);

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
    protected function getIconUrl(MediaService $mediaService): ?string
    {
        try {
            // If media_name is set, fetch icon by name directly
            if (isset($this->media['media_name'])) {
                return $mediaService->getIconUrlByName($this->media['media_name']);
            }

            // Otherwise use the API with media_type and media_id
            if (isset($this->media['media_type'], $this->media['media_id'])) {
                $media = $mediaService->find($this->media['media_type'], $this->media['media_id']);
                $assets = $media['assets'] ?? [];

                if (empty($assets)) {
                    return null;
                }

                $urls = $mediaService->getAssetUrls($assets);

                return array_values($urls)[0] ?? null;
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }
}
