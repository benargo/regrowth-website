<?php

namespace App\Http\Requests\GuildRosterManager\Concerns;

use App\Contracts\Http\Integrations\Blizzard\RequiresMinimumCharacterLevel;

/**
 * For batch requests whose rows are characters with a join and leave history.
 * The using class implements `ValidatesGrmGuids`.
 */
trait ValidatesCharacterRows
{
    /**
     * The validation rules shared by member and leaver rows, including each history event's type and strict `Y-m-d` date.
     *
     * @return array<string, array<int, string>>
     */
    protected function characterRules(): array
    {
        return [
            'guid' => ['required', ...self::GUID_RULES, 'distinct'],
            'name' => ['required', 'string', 'max:64'],
            'rankIndex' => ['required', 'integer', 'min:0', 'max:9'],
            'level' => ['required', 'integer', 'min:'.RequiresMinimumCharacterLevel::MIN_LEVEL, 'max:80'],
            'class' => ['required', 'string', 'max:32'],
            'race' => ['required', 'string', 'max:32'],
            'history' => ['present', 'array', 'max:20'],
            'history.*' => ['array'],
            'history.*.type' => ['required', 'in:joined,left'],
            'history.*.date' => ['required', 'date_format:Y-m-d'],
            'epoch' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get the error messages for the history event rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.*.history.*.array' => 'The history entry must be an object.',
            'rows.*.history.*.type.required' => 'The type must be joined or left.',
            'rows.*.history.*.type.in' => 'The type must be joined or left.',
            'rows.*.history.*.date.required' => 'The date must be a Y-m-d date.',
            'rows.*.history.*.date.date_format' => 'The date must be a Y-m-d date.',
        ];
    }
}
