<?php

namespace App\Http\Requests\Raid;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AttendanceMatrixRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('view-attendance-dashboard');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer'],
            'guild_tag_ids' => ['nullable', 'array'],
            'guild_tag_ids.*' => ['integer'],
            'since_date' => ['nullable', 'date'],
            'before_date' => ['nullable', 'date'],
        ];
    }
}
