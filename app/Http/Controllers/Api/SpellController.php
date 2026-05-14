<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSpellRequest;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use Illuminate\Http\JsonResponse;

class SpellController extends Controller
{
    /**
     * Store a newly created spell.
     */
    public function store(CreateSpellRequest $request): JsonResponse
    {
        $spell = Spell::create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
        ]);

        return response()->json((new SpellResource($spell->load('media')))->resolve($request), 201);
    }
}
