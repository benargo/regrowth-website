<?php

namespace App\Http\Requests;

use App\Models\Phase;
use App\Models\Raid;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRaidRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->detailsRules();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the raid name.',
            'difficulty.required' => 'Enter the raid difficulty, for example Normal.',
            'phase_id.required' => 'Choose which phase the raid belongs to.',
            'phase_id.exists' => 'Choose one of the listed phases.',
            'color.regex' => 'The colour must be a six-digit hex code, for example #8B7ED0.',
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
            'phase_id' => 'phase',
            'color' => 'colour',
            'max_players' => 'maximum players',
            'max_loot_councillors' => 'maximum loot councillors',
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
            $this->uniqueInGameVersionCheck(),
            $this->lootCouncillorsWithinPlayersCheck(),
        ];
    }

    /**
     * The rules for the raid's own columns.
     *
     * @return array<string, list<mixed>>
     */
    protected function detailsRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'difficulty' => ['required', 'string', 'max:50'],
            'phase_id' => ['required', 'integer', Rule::exists(Phase::class, 'id')],
            'color' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'max_players' => ['nullable', 'integer', 'between:1,40'],
            'max_loot_councillors' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * A field's value once saved: the one sent, else the stored raid's.
     */
    protected function intended(string $key): mixed
    {
        return $this->has($key) ? $this->input($key) : $this->route('raid')?->getAttribute($key);
    }

    /**
     * An after() hook that rejects a name and difficulty another raid in the
     * same game version already has (or another raid with no game version,
     * when the phase has none). Fields not sent come from the stored raid.
     * The error goes on the first of name, difficulty and phase that was sent.
     *
     * @return Closure(Validator): void
     */
    protected function uniqueInGameVersionCheck(): Closure
    {
        return function (Validator $validator): void {
            $fields = ['name', 'difficulty', 'phase_id'];

            if (! $this->hasAny($fields) || $validator->errors()->hasAny($fields)) {
                return;
            }

            $gameVersion = Phase::with('gameVersion')->find($this->intended('phase_id'))?->gameVersion;
            $name = $this->intended('name');
            $difficulty = Str::title($this->intended('difficulty'));
            $raid = $this->route('raid');

            $isTaken = Raid::inGameVersion($gameVersion)
                ->where('name', $name)
                ->where('difficulty', $difficulty)
                ->when($raid !== null, fn (Builder $query) => $query->whereKeyNot($raid->getKey()))
                ->exists();

            if (! $isTaken) {
                return;
            }

            $scope = $gameVersion === null ? 'among raids with no game version' : "in {$gameVersion->title}";
            $field = collect($fields)->first(fn (string $field): bool => $this->has($field));

            $validator->errors()->add($field, "{$name} ({$difficulty}) already exists {$scope}.");
        };
    }

    /**
     * An after() hook that rejects more loot councillors than players. A
     * field not sent comes from the stored raid; the error goes on the field
     * that was sent.
     *
     * @return Closure(Validator): void
     */
    protected function lootCouncillorsWithinPlayersCheck(): Closure
    {
        return function (Validator $validator): void {
            $fields = ['max_players', 'max_loot_councillors'];

            if (! $this->hasAny($fields) || $validator->errors()->hasAny($fields)) {
                return;
            }

            $maxPlayers = $this->intended('max_players');
            $maxLootCouncillors = $this->intended('max_loot_councillors');

            if ($maxPlayers === null || $maxLootCouncillors === null || (int) $maxLootCouncillors <= (int) $maxPlayers) {
                return;
            }

            $field = $this->has('max_loot_councillors') ? 'max_loot_councillors' : 'max_players';

            $validator->errors()->add($field, "The maximum loot councillors can't be more than the maximum players.");
        };
    }
}
