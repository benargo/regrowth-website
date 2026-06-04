<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSpellRequest;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

class SpellController extends Controller
{
    /**
     * Store a newly created spell.
     */
    #[Middleware('auth:sanctum')]
    #[Authorize('create', Spell::class)]
    public function store(CreateSpellRequest $request): JsonResponse
    {
        $spell = Spell::create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
        ]);

        return response()->json((new SpellResource($spell))->resolve($request), 201);
    }
}
