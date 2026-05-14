<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateSpellRequest;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use App\Services\Blizzard\BlizzardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SpellController extends Controller
{
    /**
     * Return a paginated list of Blizzard spell media icons.
     *
     * Used by the spell icon picker in the event assignment editor.
     */
    public function media(BlizzardService $blizzard, Request $request): JsonResponse
    {
        $params = ['tags' => ['spell'], 'page' => (int) $request->input('page', 1)];

        if ($request->filled('name')) {
            $params['name'] = $request->input('name');
        }

        $response = $blizzard->searchMedia($params);

        $results = collect(Arr::get($response, 'results', []))->map(function (array $result) {
            $assets = Arr::get($result, 'data.assets', []);
            $url = Arr::get(collect($assets)->first(), 'value');

            return [
                'id' => Arr::get($result, 'data.id'),
                'name' => (string) str($url)->afterLast('/')->beforeLast('.jpg'),
                'url' => $url,
            ];
        })->filter(fn ($item) => $item['url'] !== null)->unique('url')->values();

        return response()->json([
            'data' => $results,
            'total_pages' => (int) ceil(Arr::get($response, 'pageCount', 1)),
            'current_page' => (int) Arr::get($response, 'page', 1),
        ]);
    }

    /**
     * Store a newly created spell.
     */
    public function store(CreateSpellRequest $request): JsonResponse
    {
        $spell = Spell::create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
        ]);

        $spell->addMediaFromUrl($request->validated('icon_url'))
            ->toMediaCollection('icons');

        return response()->json((new SpellResource($spell->load('media')))->resolve($request), 201);
    }
}
