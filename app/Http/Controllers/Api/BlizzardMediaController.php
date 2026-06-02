<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Media\GetMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Media\SearchMediaRequest;
use App\Http\Requests\BlizzardMediaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Collection;

#[Middleware('auth:sanctum')]
class BlizzardMediaController extends Controller
{
    /**
     * The number of media items to return per page in the paginated response.
     */
    private const MEDIA_PER_PAGE = 1000;

    public function __invoke(BlizzardMediaRequest $request, BlizzardConnector $blizzard): JsonResponse
    {
        $allIcons = $this->fetchAllMediaPages($blizzard);

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
     * @return Collection<string, array{id: string, name: string, url: string}>
     */
    private function fetchAllMediaPages(BlizzardConnector $blizzard): Collection
    {
        $icons = collect();

        foreach (GetMediaRequest::VALID_MEDIA_TAGS as $tag) {
            $paginator = (new SearchMediaRequest(tags: [$tag], orderby: 'id'))->paginate($blizzard);

            foreach ($paginator->items() as $result) {
                $assets = $result['data']['assets'] ?? [];
                $firstAsset = $assets[0] ?? null;
                $url = $firstAsset['value'] ?? null;

                if ($url === null) {
                    continue;
                }

                $name = str($url)->afterLast('/')->beforeLast('.jpg');

                $icons->put((string) $name, [
                    'id' => (string) $name->slug(),
                    'name' => (string) $name,
                    'url' => $url,
                ]);
            }
        }

        return $icons->sortBy('name');
    }
}
