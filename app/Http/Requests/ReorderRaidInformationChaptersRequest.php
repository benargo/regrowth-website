<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRaidInformationChaptersRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'slugs' => ['required', 'array'],
            'slugs.*' => ['string', 'distinct'],
        ];
    }
}
