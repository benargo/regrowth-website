<?php

namespace App\Http\Requests;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreGameVersionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', $this->uniqueTitleRule()],
            'realm' => ['nullable', 'string', 'max:255'],
            'faction' => ['nullable', Rule::enum(Faction::class)],
            'release_date' => ['required', 'date'],
            'theme' => ['required', Rule::enum(Theme::class)],
            'blizzard_namespace' => ['nullable', Rule::enum(BlizzardNamespace::class)],
            'warcraftlogs_guild' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'warcraftlogs_namespace' => ['nullable', Rule::enum(WarcraftLogsNamespace::class)],
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
            'title.required' => 'The game version title is required.',
            'title.unique' => 'A game version with this title already exists.',
            'release_date.required' => 'The release date is required.',
            'theme.required' => 'Please choose a theme.',
        ];
    }

    /**
     * Get the human-readable field names used in default messages. They
     * match the form's visible labels so errors read naturally.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'release_date' => 'release date',
            'blizzard_namespace' => 'Blizzard API namespace',
            'warcraftlogs_guild' => 'Warcraft Logs guild ID',
            'warcraftlogs_namespace' => 'Warcraft Logs namespace',
        ];
    }

    /**
     * The uniqueness rule applied to the title.
     */
    protected function uniqueTitleRule(): Unique
    {
        return Rule::unique('game_versions', 'title');
    }
}
