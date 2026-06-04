<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ServeIconRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'size' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'regex:/^[a-z0-9_]+\.(jpg|png)$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'size' => $this->route('size'),
            'name' => Str::lower((string) ($this->route('name') ?? '')),
        ]);
    }
}
