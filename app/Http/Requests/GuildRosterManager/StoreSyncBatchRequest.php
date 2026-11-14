<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use App\Contracts\Http\Requests\GuildRosterManager\ValidatesGrmGuids;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Http\Requests\GuildRosterManager\Concerns\AuthorizesSyncWrites;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a batch of rows staged for an open sync, refusing any key outside the kind's row rules.
 */
#[FailOnUnknownFields]
abstract class StoreSyncBatchRequest extends FormRequest implements ValidatesGrmGuids
{
    use AuthorizesSyncWrites;

    protected const int MAX_ROWS = 250;

    /**
     * The kind of row the batch carries.
     */
    abstract public function kind(): RosterEntryKind;

    /**
     * The validation rules for one row, keyed by the row's attributes.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    abstract protected function rowRules(): array;

    /**
     * Determine whether the user may write to the sync from the requesting desktop client.
     */
    public function authorize(): bool
    {
        return $this->canWriteSync();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS]];

        foreach ($this->rowRules() as $attribute => $rule) {
            $rules["rows.*.{$attribute}"] = $rule;
        }

        return $rules;
    }

    /**
     * Get the validated rows hydrated as the kind's Data class.
     *
     * @return array<int, RosterEntryData>
     */
    public function rows(): array
    {
        $dataClass = $this->kind()->dataClass();

        return array_map(fn (array $row): RosterEntryData => $dataClass::from($row), array_values($this->validated('rows')));
    }

    /**
     * The raw rows of the request, or none when `rows` is not an array.
     *
     * @return array<array-key, mixed>
     */
    protected function rowsInput(): array
    {
        $rows = $this->input('rows');

        return is_array($rows) ? $rows : [];
    }
}
