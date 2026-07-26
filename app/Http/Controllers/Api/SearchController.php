<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;

#[Middleware('throttle:60,1')]
class SearchController extends Controller
{
    /** @var int */
    private const LIMIT = 5;

    /**
     * Return the top matching items for the search overlay.
     *
     * Cached under the shared ['db', 'lootcouncil'] tags, so the existing
     * FlushLootCouncilCache listener invalidates results whenever an item changes.
     */
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $query = $request->string('q')->toString();
        $key = 'search:'.md5($query).':'.self::LIMIT;

        $payload = Cache::tags(['db', 'lootcouncil'])->remember(
            $key,
            now()->addMinutes(5),
            function () use ($query): array {
                $matches = Item::query()->matchingName($query)->orderBy('name');

                $items = (clone $matches)
                    ->with(['raid', 'boss', 'media', 'priorities'])
                    ->withCount('comments')
                    ->take(self::LIMIT)
                    ->get();

                return [
                    'data' => ItemResource::collection($items)->resolve(),
                    'total' => $matches->count(),
                ];
            }
        );

        return response()->json($payload);
    }
}
