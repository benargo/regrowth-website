<?php

namespace App\Http\Requests;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Requests\Concerns\ChecksEditLock;
use App\Http\Requests\Concerns\ValidatesImageUploads;
use App\Http\Requests\Concerns\ValidatesUnscheduledPhaseOrder;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\WarcraftLogs\Guild;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class UpdateGameVersionRequest extends StoreGameVersionRequest
{
    use ChecksEditLock;
    use ValidatesImageUploads;
    use ValidatesUnscheduledPhaseOrder;

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
            'uses_surnames' => ['sometimes', 'required', 'boolean'],
            'faction' => ['sometimes', 'nullable', Rule::enum(Faction::class)],
            'release_date' => ['sometimes', 'required', 'date'],
            'color' => ['sometimes', $this->route('gameVersion')->color === null ? 'nullable' : 'required', ...$this->colorFormatRules()],
            'blizzard_namespace' => ['sometimes', 'nullable', Rule::enum(BlizzardNamespace::class)],
            'warcraft_logs_guild_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Guild::class, 'id')],
            'playable_race_ids' => ['sometimes', 'array'],
            'playable_race_ids.*' => ['integer', 'distinct', Rule::exists(PlayableRace::class, 'id')],
            'playable_class_ids' => ['sometimes', 'array'],
            'playable_class_ids.*' => ['integer', 'distinct', Rule::exists(PlayableClass::class, 'id')],
            'phase_ids' => ['sometimes', 'array'],
            'phase_ids.*' => ['integer', 'distinct', Rule::exists(Phase::class, 'id')],
            'unscheduled_phase_ids' => ['sometimes', 'array'],
            'unscheduled_phase_ids.*' => ['integer', 'distinct'],
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
            ...collect(GameVersion::MEDIA_COLLECTIONS)
                ->mapWithKeys(fn (string $collection): array => [$collection => $this->imageRules($this->route('gameVersion'), $collection)]),
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
            'guild_ranks.max' => 'A guild can have up to 10 ranks.',
            'guild_ranks.*.id.exists' => 'One of these ranks no longer exists. Reload the page and try again.',
            'guild_ranks.*.name.required' => 'Enter a name for every rank.',
            ...collect(GameVersion::MEDIA_COLLECTIONS)->flatMap(fn (string $collection): array => $this->imageMessages($this->route('gameVersion'), $collection)),
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
            $this->unscheduledPhaseOrderCheck('unscheduled_phase_ids', $this->route('gameVersion')->getKey()),
            $this->phaseNumbersStayUniqueCheck(),
        ];
    }

    /**
     * An after() hook that rejects a phase list that would give two phases in
     * the game version the same number, or that unticks a phase whose number
     * a phase without a game version already has.
     *
     * @return Closure(Validator): void
     */
    private function phaseNumbersStayUniqueCheck(): Closure
    {
        return function (Validator $validator): void {
            if (! $this->has('phase_ids') || $validator->errors()->hasAny(['phase_ids', 'phase_ids.*'])) {
                return;
            }

            $phaseIds = $this->input('phase_ids', []);
            $tickedNumbers = Phase::whereKey($phaseIds)->toBase()->pluck('number');

            if ($tickedNumbers->duplicates()->isNotEmpty()) {
                $validator->errors()->add('phase_ids', 'Two of the ticked phases have the same number. Untick one, or change its number first.');

                return;
            }

            $unownedNumbers = Phase::whereNull('game_version_id')->whereKeyNot($phaseIds)->toBase()->pluck('number');
            $clash = $this->route('gameVersion')->phases()
                ->whereKeyNot($phaseIds)
                ->get()
                ->first(fn (Phase $phase): bool => $unownedNumbers->contains($phase->getRawOriginal('number')));

            if ($clash !== null) {
                $validator->errors()->add('phase_ids', "Unticking {$clash->name} would leave two phases without a game version numbered {$clash->number}. Change its number first.");
            }
        };
    }

    /**
     * The uniqueness rule applied to the title, ignoring the game version being updated.
     */
    protected function uniqueTitleRule(): Unique
    {
        return Rule::unique('game_versions', 'title')->ignore($this->route('gameVersion'));
    }
}
