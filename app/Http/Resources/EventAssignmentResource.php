<?php

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EventAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'left' => [
                'type' => $this->resolveType('left'),
                'data' => $this->resolveAssignment('left', $request),
            ],
            'right' => [
                'type' => $this->resolveType('right'),
                'data' => $this->resolveAssignment('right', $request),
            ],
        ];
    }

    /**
     * Resolves the left or right side of the assignment to a type string for frontend rendering logic.
     */
    private function resolveType(string $side): ?string
    {
        if (class_exists($this->{"{$side}_type"})) {
            return Str::snake(class_basename($this->{"{$side}_type"}));
        }

        return gettype($this->{"{$side}_value"});
    }

    /**
     * Resolves the left or right side of the assignment to its resource representation, if applicable.
     */
    private function resolveAssignment(string $side, Request $request): mixed
    {
        $assignment = match ($side) {
            'left' => $this->resolveLeft(),
            'right' => $this->resolveRight(),
            default => null,
        };

        if ($assignment instanceof Character) {
            return $assignment->loadMissing('playableClass')
                ->toResource(CharacterSummaryResource::class)
                ->resolve($request);
        }

        if ($assignment instanceof Model) {
            return $assignment->toResource()->resolve($request);
        }

        return $assignment;
    }
}
