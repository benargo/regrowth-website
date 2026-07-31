<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCharacterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $character = $this->route('character');

        return [
            'specialization_ids' => ['sometimes', 'array'],
            // Class-scoped exists rule prevents assigning a specialization
            // that does not belong to this character's class.
            'specialization_ids.*' => [
                'integer',
                Rule::exists('playable_specializations', 'id')->where('playable_class_id', $character->playable_class_id),
            ],
            'raid_specialization_id' => ['sometimes', 'nullable', 'integer'],
            'is_loot_councillor' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $raid = $this->input('raid_specialization_id');

            if ($raid === null) {
                return;
            }

            // Only cross-check when the caller also supplied the spec list;
            // a partial payload has nothing to check the raid spec against.
            if (! $this->has('specialization_ids')) {
                return;
            }

            $ids = collect($this->input('specialization_ids', []))->map(fn ($id) => (int) $id);

            if (! $ids->contains((int) $raid)) {
                $validator->errors()->add(
                    'raid_specialization_id',
                    'The selected raid spec must be among the chosen specializations.',
                );
            }
        });
    }
}
