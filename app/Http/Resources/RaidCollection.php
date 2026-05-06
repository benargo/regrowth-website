<?php

namespace App\Http\Resources;

use App\Enums\RaidBackground;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RaidCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'background' => $this->getBackground(),
        ];
    }

    /**
     * Determine the appropriate background based on the raids in the collection.
     */
    private function getBackground(): string
    {
        $raidId = $this->collection->pluck('id')->first();

        return RaidBackground::fromRaidId($raidId)->value;
    }
}
