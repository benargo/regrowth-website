<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesUnscheduledPhaseOrder;
use App\Models\GameVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderPhasesRequest extends FormRequest
{
    use ValidatesUnscheduledPhaseOrder;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'game_version_id' => ['present', 'nullable', 'integer', Rule::exists(GameVersion::class, 'id')],
            'phase_ids' => ['present', 'array'],
            'phase_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'game_version_id.exists' => 'That game version no longer exists. Reload the page and try again.',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            $this->unscheduledPhaseOrderCheck(
                'phase_ids',
                $this->filled('game_version_id') ? $this->integer('game_version_id') : null,
            ),
        ];
    }
}
