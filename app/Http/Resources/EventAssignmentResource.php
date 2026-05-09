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
            'sort_order' => $this->sort_order,
            'left' => [
                'type' => $this->left_model_key,
                'data' => $left instanceof Model ? $left->toResource() : $left,
            ],
            'right' => [
                'type' => $this->right_model_key,
                'data' => $right instanceof Model ? $right->toResource() : $right,
            ],
        ];
    }
}
