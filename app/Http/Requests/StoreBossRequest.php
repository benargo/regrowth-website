<?php

namespace App\Http\Requests;

use App\Contracts\Actions\Boss\SharesRaidPageOrigins;
use App\Models\Boss;
use App\Models\Raid;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class StoreBossRequest extends FormRequest implements SharesRaidPageOrigins
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'raid_id' => ['required', 'integer', Rule::exists(Raid::class, 'id')],
            'name' => ['required', 'string', 'max:255', $this->uniqueNameRule($this->input('raid_id'))],
            'from' => ['nullable', 'string', Rule::in(self::FROM_RAID_PAGES)],
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
            'raid_id.required' => 'Choose the raid this boss is in.',
            'raid_id.exists' => 'Choose one of the listed raids.',
            'name.required' => 'Enter the boss name.',
            'name.max' => 'The boss name must be 255 characters or fewer.',
            'from.in' => "Open this form from the bosses list or a raid's Bosses step.",
        ];
    }

    /**
     * Get the human-readable field names used in default messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'raid_id' => 'raid',
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
            $this->uniqueNameMessageCheck(),
        ];
    }

    /**
     * The rule that keeps a boss name unique within its raid.
     */
    protected function uniqueNameRule(mixed $raidId): Unique
    {
        return Rule::unique(Boss::class, 'name')
            ->where('raid_id', is_numeric($raidId) ? (int) $raidId : null);
    }

    /**
     * An after() hook that replaces a failed unique-name error with one that
     * names the raid.
     *
     * @return Closure(Validator): void
     */
    protected function uniqueNameMessageCheck(): Closure
    {
        return function (Validator $validator): void {
            if (! isset($validator->failed()['name']['Unique'])) {
                return;
            }

            $validator->errors()->forget('name');
            $validator->errors()->add('name', "{$this->raidName()} already has a boss called {$this->input('name')}.");
        };
    }

    /**
     * The name of the raid the boss is in, as the unique-name message reads.
     */
    protected function raidName(): string
    {
        return Raid::find($this->input('raid_id'))?->name ?? 'This raid';
    }
}
