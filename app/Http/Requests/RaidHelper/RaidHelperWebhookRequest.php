<?php

namespace App\Http\Requests\RaidHelper;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

abstract class RaidHelperWebhookRequest extends FormRequest
{
    abstract protected function webhookRules(): Collection;

    protected function prepareForValidation(): void
    {
        $allowed = $this->webhookRules()
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
