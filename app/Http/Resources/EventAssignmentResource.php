<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $left = $this->resolveLeft();
        $right = $this->resolveRight();

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'boss_id' => $this->boss_id,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
            'left' => $left instanceof Model ? $left->toResource() : $left,
            'right' => $right instanceof Model ? $right->toResource() : $right,
        ];
    }
}
