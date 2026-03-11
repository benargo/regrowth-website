<?php

namespace App\Exceptions;

use App\Models\Character;
use Exception;
use Illuminate\Http\JsonResponse;

class CharacterNotMainException extends Exception
{
    public function __construct(private readonly Character $character)
    {
        parent::__construct('The specified character is not a main character.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'The specified character is not a main character.',
            'suggestion' => $this->character->mainCharacter?->name,
        ], 400);
    }
}
