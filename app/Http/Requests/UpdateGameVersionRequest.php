<?php

namespace App\Http\Requests;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Http\Requests\Concerns\ChecksEditLock;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class UpdateGameVersionRequest extends StoreGameVersionRequest
{
    use ChecksEditLock;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255', $this->uniqueTitleRule()],
            'slug' => ['prohibited'],
            'realm' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guild_name' => ['sometimes', 'required', 'string', 'max:24'],
            'faction' => ['sometimes', 'nullable', Rule::enum(Faction::class)],
            'release_date' => ['sometimes', 'required', 'date'],
            'theme' => ['sometimes', 'nullable', Rule::enum(Theme::class)],
            'blizzard_namespace' => ['sometimes', 'nullable', Rule::enum(BlizzardNamespace::class)],
            'warcraftlogs_guild' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:2147483647'],
            'warcraftlogs_namespace' => ['sometimes', 'nullable', Rule::enum(WarcraftLogsNamespace::class)],
            'playable_race_ids' => ['sometimes', 'array'],
            'playable_race_ids.*' => ['integer', 'distinct', Rule::exists(PlayableRace::class, 'id')],
            'playable_class_ids' => ['sometimes', 'array'],
            'playable_class_ids.*' => ['integer', 'distinct', Rule::exists(PlayableClass::class, 'id')],
            'phase_ids' => ['sometimes', 'array'],
            'phase_ids.*' => ['integer', 'distinct', Rule::exists(Phase::class, 'id')],
            'new_phase' => ['sometimes', 'required', 'array:number,description,start_date'],
            'new_phase.number' => [
                'required_with:new_phase',
                'numeric',
                'decimal:0,1',
                'between:0,9.9',
                Rule::unique(Phase::class, 'number')->where('game_version_id', $this->route('gameVersion')->getKey()),
            ],
            'new_phase.description' => ['required_with:new_phase', 'string', 'max:255'],
            'new_phase.start_date' => ['nullable', 'date'],
            'new_raid' => ['sometimes', 'required', 'array:name,difficulty,phase_id,max_players'],
            'new_raid.name' => ['required_with:new_raid', 'string', 'max:255'],
            'new_raid.difficulty' => ['required_with:new_raid', 'string', 'max:50'],
            'new_raid.phase_id' => [
                'required_with:new_raid',
                'integer',
                Rule::exists(Phase::class, 'id')->where('game_version_id', $this->route('gameVersion')->getKey()),
            ],
            'new_raid.max_players' => ['nullable', 'integer', 'between:1,40'],
            'guild_ranks' => ['sometimes', 'array', 'max:10'],
            'guild_ranks.*' => ['required', 'array:id,name,count_attendance'],
            'guild_ranks.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists(GuildRank::class, 'id')->where('game_version_id', $this->route('gameVersion')->getKey()),
            ],
            'guild_ranks.*.name' => ['required', 'string', 'max:255'],
            'guild_ranks.*.count_attendance' => ['required', 'boolean'],
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
            'slug.prohibited' => "The slug can't be changed once the game version is created.",
            'playable_race_ids.*.exists' => 'One of the selected races no longer exists. Reload the page and try again.',
            'playable_class_ids.*.exists' => 'One of the selected classes no longer exists. Reload the page and try again.',
            'phase_ids.*.exists' => 'One of the selected phases no longer exists. Reload the page and try again.',
            'new_phase.number.required_with' => 'Enter the phase number.',
            'new_phase.number.unique' => 'This game version already has a phase with that number.',
            'new_phase.description.required_with' => 'Enter a description for the phase.',
            'new_raid.name.required_with' => 'Enter the raid name.',
            'new_raid.difficulty.required_with' => 'Enter the raid difficulty, for example Normal.',
            'new_raid.phase_id.required_with' => 'Choose which phase the raid belongs to.',
            'new_raid.phase_id.exists' => "Choose one of this game version's phases.",
            'guild_ranks.max' => 'A guild can have up to 10 ranks.',
            'guild_ranks.*.id.exists' => 'One of these ranks no longer exists. Reload the page and try again.',
            'guild_ranks.*.name.required' => 'Enter a name for every rank.',
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
            ...parent::attributes(),
            'new_phase.number' => 'phase number',
            'new_phase.start_date' => 'start date',
            'new_raid.max_players' => 'maximum players',
            'guild_ranks.*.name' => 'rank name',
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
            $this->editLockCheck('gameVersion', 'game version'),
        ];
    }

    /**
     * The uniqueness rule applied to the title, ignoring the game version being updated.
     */
    protected function uniqueTitleRule(): Unique
    {
        return Rule::unique('game_versions', 'title')->ignore($this->route('gameVersion'));
    }
}
