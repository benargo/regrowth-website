<?php

namespace App\Http\Controllers\Api;

use App\Events\Broadcasts\ItemUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Items\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;

#[Authorize('update', 'item')]
class ItemController extends Controller
{
    public function update(UpdateItemRequest $request, Item $item): JsonResponse
    {
        DB::transaction(function () use ($request, $item): void {
            if ($request->has('notes')) {
                $item->notes = $request->validated('notes');
                $item->save();
            }

            if ($request->has('priorities')) {
                $priorities = collect($request->validated('priorities'))
                    ->mapWithKeys(fn ($p) => [$p['priority_id'] => ['weight' => $p['weight']]])
                    ->all();

                $item->priorities()->sync($priorities);
            }
        });

        $item->load(['priorities' => fn ($q) => $q->orderByPivot('weight', 'desc')]);

        broadcast(new ItemUpdated($item))->toOthers();

        return response()->json(new ItemResource($item));
    }
}
