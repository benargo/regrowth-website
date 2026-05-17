<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssignmentType;
use App\Events\Broadcasts\AssignmentChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEventAssignmentRequest;
use App\Http\Requests\ReorderEventAssignmentsRequest;
use App\Http\Requests\UpdateEventAssignmentRequest;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class EventAssignmentController extends Controller
{
    /**
     * Create a new assignment for the given event.
     */
    public function store(Event $event, CreateEventAssignmentRequest $request): JsonResponse
    {
        $this->abortIfGroupNotInEvent($event, $request->input('group_id'));

        $assignment = DB::transaction(function () use ($event, $request): EventAssignment {
            $maxSortOrder = EventAssignment::where('event_id', $event->id)
                ->where('boss_id', $request->input('boss_id'))
                ->where('group_id', $request->input('group_id'))
                ->lockForUpdate()
                ->max('sort_order') ?? 0;

            return EventAssignment::create([
                'event_id' => $event->id,
                'boss_id' => $request->input('boss_id'),
                'group_id' => $request->input('group_id'),
                'sort_order' => $maxSortOrder + 1,
                'left_type' => null,
                'left_value' => null,
                'right_type' => null,
                'right_value' => null,
            ]);
        });

        return response()->json([
            'id' => $assignment->id,
            'sort_order' => $assignment->sort_order,
            'boss_id' => $assignment->boss_id,
            'group_id' => $assignment->group_id,
            'left_type' => $assignment->getRawOriginal('left_type'),
            'left_value' => $assignment->left_value,
            'right_type' => $assignment->getRawOriginal('right_type'),
            'right_value' => $assignment->right_value,
        ], 201);
    }

    /**
     * Update an assignment's fields.
     */
    public function update(Event $event, EventAssignment $assignment, UpdateEventAssignmentRequest $request): Response
    {
        abort_if($assignment->event_id !== $event->id, 404);

        $this->abortIfGroupNotInEvent($event, $request->input('group_id'));

        $data = [];

        if ($request->has('boss_id')) {
            $data['boss_id'] = $request->input('boss_id');
        }
        if ($request->has('group_id')) {
            $data['group_id'] = $request->input('group_id');
        }
        if ($request->has('sort_order')) {
            $data['sort_order'] = $request->input('sort_order');
        }
        if ($request->has('left_type')) {
            $data['left_type'] = $this->resolveTypeName($request->input('left_type'));
        }
        if ($request->has('left_value')) {
            $data['left_value'] = $request->input('left_value');
        }
        if ($request->has('right_type')) {
            $data['right_type'] = $this->resolveTypeName($request->input('right_type'));
        }
        if ($request->has('right_value')) {
            $data['right_value'] = $request->input('right_value');
        }

        $assignment->update($data);

        return response()->noContent();
    }

    /**
     * Reorder assignments for the given event.
     */
    public function reorder(Event $event, ReorderEventAssignmentsRequest $request): Response
    {
        $order = $request->input('order');

        DB::transaction(function () use ($event, $order) {
            $ids = EventAssignment::whereIn('id', $order)
                ->where('event_id', $event->id)
                ->pluck('id')
                ->all();

            abort_if(count($ids) !== count($order), 422);

            foreach ($order as $position => $id) {
                EventAssignment::where('id', $id)->update(['sort_order' => $position]);
            }
        });

        broadcast(AssignmentChanged::forReorder($event, $order))->toOthers();

        return response()->noContent();
    }

    private function resolveTypeName(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $resolved = AssignmentType::tryFrom($type);
        abort_if($resolved === null, 422, "Unknown assignment type: {$type}");

        return $resolved->modelClass();
    }

    private function abortIfGroupNotInEvent(Event $event, ?int $groupId): void
    {
        if ($groupId === null) {
            return;
        }

        abort_if(
            ! EventAssignmentGroup::where('id', $groupId)->where('event_id', $event->id)->exists(),
            422,
        );
    }

    /**
     * Delete an assignment.
     */
    public function destroy(Event $event, EventAssignment $assignment): Response
    {
        abort_if($assignment->event_id !== $event->id, 404);

        $assignment->delete();

        return response()->noContent();
    }
}
