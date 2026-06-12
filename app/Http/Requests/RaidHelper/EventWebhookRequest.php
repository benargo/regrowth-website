<?php

namespace App\Http\Requests\RaidHelper;

use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EventWebhookRequest extends FormRequest
{
    private readonly Collection $eventRules;

    public function __construct()
    {
        parent::__construct();
        $this->eventRules = collect(EventData::getValidationRules([]))
            ->mapWithKeys(fn ($rules, $key) => [Str::camel($key) => $rules]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return $this->eventRules->all();
    }

    protected function prepareForValidation(): void
    {
        $allowed = $this->eventRules
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
