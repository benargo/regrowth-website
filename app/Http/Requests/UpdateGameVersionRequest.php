<?php

namespace App\Http\Requests;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Http\Requests\Concerns\ChecksEditLock;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
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
            'slug' => ['missing'],
            'realm' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guild_name' => ['sometimes', 'required', 'string', 'max:24'],
            'faction' => ['sometimes', 'nullable', Rule::enum(Faction::class)],
            'release_date' => ['sometimes', 'required', 'date'],
            'color' => ['sometimes', $this->route('gameVersion')->color === null ? 'nullable' : 'required', 'string', 'regex:/^#?[0-9a-fA-F]{6}$/'],
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
            GameVersion::MEDIA_BANNER => $this->imageRules(GameVersion::MEDIA_BANNER),
            GameVersion::MEDIA_ICON => $this->imageRules(GameVersion::MEDIA_ICON),
            GameVersion::MEDIA_LOGO => $this->imageRules(GameVersion::MEDIA_LOGO),
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
            'slug.missing' => "The slug can't be changed once the game version is created.",
            'color.required' => "The colour can't be removed once it has been set.",
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
            ...$this->imageMessages(GameVersion::MEDIA_BANNER),
            ...$this->imageMessages(GameVersion::MEDIA_ICON),
            ...$this->imageMessages(GameVersion::MEDIA_LOGO),
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
     * The rules for an image replacing one of the game version's media
     * collections, accepting the types the collection accepts. Null removes
     * the image.
     *
     * @return list<string>
     */
    protected function imageRules(string $collection): array
    {
        $mimeTypes = (new GameVersion)->acceptedMimeTypes($collection);

        return ['sometimes', 'nullable', 'file', 'mimetypes:'.implode(',', $mimeTypes), 'max:2048'];
    }

    /**
     * The messages for an image field, naming the image it replaces and the
     * types it accepts.
     *
     * @return array<string, string>
     */
    protected function imageMessages(string $field): array
    {
        $types = collect((new GameVersion)->acceptedMimeTypes($field))
            ->map(fn (string $mimeType): string => Str::of($mimeType)->after('/')->before('+')->upper()->toString())
            ->join(', ', ' or ');

        return [
            "{$field}.file" => "The {$field} must be a {$types} image.",
            "{$field}.mimetypes" => "The {$field} must be a {$types} image.",
            "{$field}.max" => "The {$field} must be 2 MB or smaller.",
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
