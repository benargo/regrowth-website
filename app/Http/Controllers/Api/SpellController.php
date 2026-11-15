<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTransientToken;
use App\Http\Requests\CreateSpellRequest;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

class SpellController extends Controller
{
    /**
     * Store a newly created spell.
     */
    #[Middleware('auth:api')]
    #[Middleware(EnsureTransientToken::class)]
    #[Authorize('create', Spell::class)]
    public function store(CreateSpellRequest $request): SpellResource
    {
        $spell = Spell::create([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
        ]);

        return new SpellResource($spell);
    }
}
