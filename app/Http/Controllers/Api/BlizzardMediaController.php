<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BlizzardMediaRequest;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BlizzardMediaController extends Controller
{
    private const MEDIA_PER_PAGE = 1000;

    /**
     * Return a paginated list of Blizzard media icons.
     *
     * All pages are eagerly fetched from Blizzard, deduplicated by URL, and
     * cached for 30 days. Pagination and name-filtering are applied locally.
     */
    public function __invoke(BlizzardMediaRequest $request, BlizzardService $blizzard): JsonResponse
    {
        $allIcons = collect(Cache::tags(['blizzard', 'blizzard-api-response'])->remember(
            $blizzard->cacheKey('blizzard:icons:all'),
            2592000, // 30 days
            fn () => $this->fetchAllMediaPages($blizzard),
        ));

        $filtered = $request->filled('name')
            ? $allIcons->filter(fn (array $icon) => str_contains(
                $icon['name'],
                (string) $request->input('name'),
            ))->values()
            : $allIcons;

        $page = (int) $request->input('page', 1);
        $perPage = self::MEDIA_PER_PAGE;
        $items = $filtered->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator($items, $filtered->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return response()->json($paginator->toArray());
    }

    /**
     * Fetch every page of media from Blizzard and return a deduplicated collection.
     *
     * @return array<int, array{id: int, name: string, url: string}>
     */
    private function fetchAllMediaPages(BlizzardService $blizzard): array
    {
        $icons = collect();

        foreach (BlizzardService::VALID_MEDIA_TAGS as $tag) {
            $page = 1;
            $totalPages = 1;

            do {
                $response = $blizzard->searchMedia([
                    'tags' => $tag,
                    'orderby' => 'id',
                    '_page' => $page,
                ]);

                foreach (Arr::get($response, 'results', []) as $result) {
                    $assets = Arr::get($result, 'data.assets', []);
                    $firstAsset = Arr::first($assets);
                    $url = Arr::get($firstAsset, 'value');
                    $name = str($url)->afterLast('/')->beforeLast('.jpg');

                    if ($url === null) {
                        continue;
                    }

                    $icons->put((string) $name, [
                        'id' => (string) $name->slug(), // Use a slug for reliability, since Blizzard media can have duplicate IDs across types
                        'name' => (string) $name,
                        'url' => $url,
                    ]);
                }

                $totalPages = (int) Arr::get($response, 'pageCount', 1);
                $page++;
            } while ($page <= $totalPages);
        }

        return $icons->sortBy('name')->all();
    }
}
