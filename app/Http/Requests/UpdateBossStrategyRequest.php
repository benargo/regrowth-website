<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBossStrategyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('boss'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'deleted_images' => ['nullable', 'array'],
            'deleted_images.*' => ['nullable', 'string', 'url'],
            'image_order' => ['nullable', 'array'],
            'image_order.*' => ['nullable', 'string'],
        ];
    }
}
