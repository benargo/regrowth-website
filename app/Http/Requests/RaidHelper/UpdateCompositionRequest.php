<?php

namespace App\Http\Requests\RaidHelper;

use App\Http\Integrations\RaidHelper\Data\Compositions\CompositionData;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UpdateCompositionRequest extends FormRequest
{
    private readonly Collection $compositionRules;

    public function __construct()
    {
        parent::__construct();
        $this->compositionRules = collect(CompositionData::getValidationRules([]))
            ->mapWithKeys(fn ($rules, $key) => [Str::camel($key) => $rules]);
    }

    public function rules(): array
    {
        return $this->compositionRules
            ->merge(['id' => ['required', 'string', 'exists:events,raid_helper_event_id']])
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $allowed = $this->compositionRules
            ->keys()
            ->filter(fn ($key) => ! str_contains($key, '.') && ! str_contains($key, '*'));

        if (count(Arr::reject($this->keys(), fn ($key) => $allowed->contains($key))) > 0) {
            abort(400);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->noContent(400));
    }
}
