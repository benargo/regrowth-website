<?php

namespace App\Http\Controllers\Api;

use App\Events\EventGroupCreated;
use App\Events\EventGroupDeleted;
use App\Events\EventGroupsReordered;
use App\Events\EventGroupUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEventGroupRequest;
use App\Http\Requests\ReorderEventGroupsRequest;
use App\Http\Requests\UpdateEventGroupRequest;
use App\Models\Event;
use App\Models\EventAssignmentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class EventGroupController extends Controller
{
    /**
     * Create a new group for the given event.
     */
    public function store(Event $event, CreateEventGroupRequest $request): JsonResponse
    {
        $group = DB::transaction(function () use ($event, $request): EventAssignmentGroup {
            $maxSortOrder = EventAssignmentGroup::where('event_id', $event->id)
                ->lockForUpdate()
                ->max('sort_order') ?? 0;

            return EventAssignmentGroup::create([
                'event_id' => $event->id,
                'name' => $request->input('name', 'New group'),
                'sort_order' => $maxSortOrder + 1,
            ]);
        });

        EventGroupCreated::dispatch($group);

        return response()->json([
            'id' => $group->id,
            'name' => $group->name,
            'sort_order' => $group->sort_order,
        ], 201);
    }

    /**
     * Update a group's name or sort order.
     */
    public function update(Event $event, EventAssignmentGroup $group, UpdateEventGroupRequest $request): Response
    {
        abort_if($group->event_id !== $event->id, 404);

        $group->update($request->only(['name', 'sort_order']));

        EventGroupUpdated::dispatch($group);

        return response()->noContent();
    }

    /**
     * Reorder groups for the given event.
     */
    public function reorder(Event $event, ReorderEventGroupsRequest $request): Response
    {
        $order = $request->input('order');

        DB::transaction(function () use ($event, $order) {
            $ids = EventAssignmentGroup::whereIn('id', $order)
                ->where('event_id', $event->id)
                ->pluck('id')
                ->all();

            abort_if(count($ids) !== count($order), 422);

            foreach ($order as $position => $id) {
                EventAssignmentGroup::where('id', $id)->update(['sort_order' => $position]);
            }
        });

        EventGroupsReordered::dispatch($event);

        return response()->noContent();
    }

    /**
     * Delete a group and cascade its assignments.
     */
    public function destroy(Event $event, EventAssignmentGroup $group): Response
    {
        abort_if($group->event_id !== $event->id, 404);

        $groupId = $group->id;
        $group->delete();

        EventGroupDeleted::dispatch($groupId, $event->id);

        return response()->noContent();
    }
}
