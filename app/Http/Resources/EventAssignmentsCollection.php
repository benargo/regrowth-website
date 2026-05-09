<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

#[Collects(EventAssignmentResource::class)]
class EventAssignmentsCollection extends ResourceCollection
{
    /**
     * Disable the data wrapper since we are performing this manually.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $grouped = $this->collection->whereNotNull('group_id');
        $ungrouped = $this->collection->whereNull('group_id');

        $groups = $grouped
            ->groupBy('group_id')
            ->map(function (Collection $groupAssignments) use ($request): array {
                $group = $groupAssignments->first()->group;

                return [
                    'id' => $group->id,
                    'name' => $group->title,
                    'sort_order' => $group->sort_order,
                    'assignments' => EventAssignmentResource::collection(
                        $groupAssignments->sortBy('sort_order')->values()
                    )->resolve($request),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();

        return [
            'groups' => $groups,
            'ungrouped' => EventAssignmentResource::collection(
                $ungrouped->sortBy('sort_order')->values()
            )->resolve($request),
            'count' => $this->collection->count(),
        ];
    }
}
