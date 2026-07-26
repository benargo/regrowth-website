<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    /** @var int */
    private const PER_PAGE = 25;

    /**
     * Display the full, paginated search results.
     */
    public function __invoke(SearchRequest $request): Response
    {
        $query = $request->string('q')->toString();

        $results = Item::query()
            ->matchingName($query)
            ->orderBy('name')
            ->with(['raid', 'boss', 'media', 'priorities'])
            ->withCount('comments')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Search', [
            'results' => ItemResource::collection($results),
            'q' => $query,
        ]);
    }
}
