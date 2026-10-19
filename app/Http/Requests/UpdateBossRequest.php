<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChecksEditLock;
use App\Models\Boss;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBossRequest extends StoreBossRequest
{
    use ChecksEditLock;

    /**
     * Get the validation rules that apply to the request. Every key is
     * optional, only the keys sent are validated, and the raid can't change.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'raid_id' => ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'max:255', $this->uniqueNameRule($this->boss()->raid_id)->ignore($this->boss())],
            'notes' => ['sometimes', 'nullable', 'string'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['image', 'mimes:jpeg,png,webp', 'max:2048'],
            'removed_image_ids' => ['sometimes', 'array'],
            'removed_image_ids.*' => ['integer', 'distinct', Rule::in($this->strategyImageIds())],
            'image_ids' => ['sometimes', 'array'],
            'image_ids.*' => ['integer', 'distinct'],
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
            ...parent::messages(),
            'raid_id.prohibited' => "A boss can't move to another raid. Delete it and add it to the other raid instead.",
            'images.*.image' => 'Upload JPEG, PNG or WEBP images.',
            'images.*.mimes' => 'Upload JPEG, PNG or WEBP images.',
            'images.*.max' => 'Each image must be 2 MB or smaller.',
            'removed_image_ids.*.in' => "Remove this boss's own images.",
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
            $this->editLockCheck('boss', 'boss'),
            $this->imageOrderCheck(),
        ];
    }

    protected function raidName(): string
    {
        return $this->boss()->raid->name;
    }

    private function boss(): Boss
    {
        return $this->route('boss');
    }

    /**
     * The ids of the boss's strategy images.
     *
     * @return list<int>
     */
    private function strategyImageIds(): array
    {
        return $this->boss()->getMedia()->modelKeys();
    }

    /**
     * An after() hook that adds an image_ids error unless the ids are exactly
     * the boss's strategy images left after the removals in the same request.
     *
     * @return Closure(Validator): void
     */
    private function imageOrderCheck(): Closure
    {
        return function (Validator $validator): void {
            if (! $this->has('image_ids') || $validator->errors()->hasAny(['image_ids', 'image_ids.*', 'removed_image_ids', 'removed_image_ids.*'])) {
                return;
            }

            $expected = collect($this->strategyImageIds())
                ->diff($this->input('removed_image_ids', []))
                ->sort()
                ->values()
                ->all();

            $given = collect($this->input('image_ids'))
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($given !== $expected) {
                $validator->errors()->add('image_ids', "Reorder this boss's own images.");
            }
        };
    }
}
