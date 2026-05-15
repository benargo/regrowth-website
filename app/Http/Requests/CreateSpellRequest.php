<?php

namespace App\Http\Requests;

use App\Enums\AffectType;
use App\Models\Spell;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateSpellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Spell::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(AffectType::class)],
        ];
    }
}
