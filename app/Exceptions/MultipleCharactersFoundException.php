<?php

namespace App\Exceptions;

use App\Models\Character;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class MultipleCharactersFoundException extends Exception
{
    /**
     * @param  Collection<int, Character>  $characters
     */
    public function __construct(private readonly Collection $characters)
    {
        parent::__construct('Multiple characters matched that name.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Multiple characters matched that name. Please specify one.',
            'characters' => $this->characters->map(fn (Character $c) => ['id' => $c->id, 'name' => $c->name]),
        ], 300);
    }
}
