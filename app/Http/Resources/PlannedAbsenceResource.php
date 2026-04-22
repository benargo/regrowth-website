<?php

namespace App\Http\Resources;

use App\Models\PlannedAbsence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlannedAbsenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = [
            'id' => $this->id,
            'character' => $this->whenLoaded('character', fn () => $this->character->only(['id', 'name', 'playable_class'])),
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user)->toArray($request) : null),
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'reason' => $this->reason,
            'discord_message_id' => $this->discord_message_id,
            'created_by' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)->toArray($request)),
            'created_at' => $this->created_at->toDateTimeString(),
        ];

        if ($request->user()?->can('viewAny', PlannedAbsence::class)) {
            $resource['updated_at'] = $this->updated_at->toDateTimeString();
            $resource['deleted_at'] = $this->deleted_at?->toDateTimeString();
        }

        return $resource;
    }
}
