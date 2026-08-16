<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SearchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'raid_id' => ['sometimes', 'nullable', 'integer', 'exists:raids,id'],
        ];
    }

    /**
     * The raid to scope results to, or null when searching unscoped.
     */
    public function raidId(): ?int
    {
        return $this->filled('raid_id') ? (int) $this->input('raid_id') : null;
    }

    /**
     * Normalise the query so cache keys collapse across casing and stray whitespace,
     * and strip full-text boolean-mode operators so user input is always treated as
     * literal text tokens rather than search syntax (e.g. a stray "+" or "*").
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('q')) {
            $sanitised = preg_replace('/[+\-*"()<>~@]/', ' ', (string) $this->input('q'));

            $this->merge([
                'q' => Str::of($sanitised)->lower()->squish()->toString(),
            ]);
        }
    }

    /**
     * Test seam for the protected prepareForValidation() hook.
     */
    public function prepareForValidationForTesting(): void
    {
        $this->prepareForValidation();
    }
}
