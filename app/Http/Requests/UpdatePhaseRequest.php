<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChecksEditLock;
use App\Models\GameVersion;
use App\Models\Phase;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePhaseRequest extends StorePhaseRequest
{
    use ChecksEditLock;

    /**
     * Get the validation rules that apply to the request. Every key is
     * optional, and only the keys sent are validated.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => [
                'sometimes',
                'required',
                ...$this->numberFormatRules(),
                $this->uniqueNumberRule($this->targetGameVersionId())->ignore($this->route('phase')),
            ],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'game_version_id' => ['sometimes', 'nullable', 'integer', Rule::exists(GameVersion::class, 'id')],
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
            $this->editLockCheck('phase', 'phase'),
            $this->numberFreeInNewGameVersionCheck(),
        ];
    }

    /**
     * The game version the phase will belong to: the one sent, or its stored one.
     */
    private function targetGameVersionId(): mixed
    {
        return $this->has('game_version_id')
            ? $this->input('game_version_id')
            : $this->route('phase')->game_version_id;
    }

    /**
     * An after() hook that rejects moving the phase into a game version where
     * another phase already has its stored number, when the request doesn't
     * also change the number.
     *
     * @return Closure(Validator): void
     */
    private function numberFreeInNewGameVersionCheck(): Closure
    {
        return function (Validator $validator): void {
            if (! $this->has('game_version_id') || $this->has('number') || $validator->errors()->has('game_version_id')) {
                return;
            }

            $phase = $this->route('phase');
            $isTaken = Phase::whereKeyNot($phase->getKey())
                ->where('game_version_id', $this->input('game_version_id'))
                ->where('number', $phase->getRawOriginal('number'))
                ->exists();

            if ($isTaken) {
                $validator->errors()->add('game_version_id', 'That game version already has a phase with this number.');
            }
        };
    }
}
