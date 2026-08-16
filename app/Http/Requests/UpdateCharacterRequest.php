<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCharacterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $character = $this->route('character');

        return [
            'is_loot_councillor' => ['sometimes', 'boolean'],
            'specializations' => ['sometimes', 'array'],
            // `present` (not `required_with`) so an empty selection — `specialization_ids: []` —
            // is accepted; `required_with` treats an empty array as missing.
            'specializations.specialization_ids' => [Rule::when($this->has('specializations'), ['present']), 'array'],
            // Class-scoped exists rule prevents assigning a specialization
            // that does not belong to this character's class.
            'specializations.specialization_ids.*' => [
                'integer',
                Rule::exists('playable_specializations', 'id')->where('playable_class_id', $character->playable_class_id),
            ],
            'specializations.raid_specialization_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        session()->flash('error', 'Failed to update character. Please check your input and try again.');

        parent::failedValidation($validator);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $raid = $this->input('specializations.raid_specialization_id');

            if ($raid === null) {
                return;
            }

            $ids = collect($this->input('specializations.specialization_ids', []))
                ->map(fn ($id) => (int) $id);

            if (! $ids->contains((int) $raid)) {
                $validator->errors()->add(
                    'specializations.raid_specialization_id',
                    'The selected raid spec must be among the chosen specializations.',
                );
            }
        });
    }
}
