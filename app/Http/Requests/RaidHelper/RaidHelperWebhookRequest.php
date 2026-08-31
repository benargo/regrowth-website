<?php

namespace App\Http\Requests\RaidHelper;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

abstract class RaidHelperWebhookRequest extends FormRequest
{
    abstract protected function webhookRules(): Collection;

    /**
     * Raid Helper occasionally adds new top-level keys to its webhook payloads.
     * These are forward-compatible additions, not errors, so unrecognised keys
     * are logged for visibility and then quietly ignored — validation only ever
     * considers the keys declared in rules().
     */
    protected function prepareForValidation(): void
    {
        $allowed = $this->webhookRules()
            ->keys()
            ->filter(fn ($key) => ! str_contains($key, '.') && ! str_contains($key, '*'));

        $unexpected = Arr::reject($this->keys(), fn ($key) => $allowed->contains($key));

        if (count($unexpected) > 0) {
            Log::warning('Raid Helper webhook contained unexpected keys', [
                'request' => static::class,
                'unexpected_keys' => array_values($unexpected),
                'allowed_keys' => $allowed->values()->all(),
                'payload' => $this->all(),
            ]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('Raid Helper webhook failed validation', [
            'request' => static::class,
            'errors' => $validator->errors()->toArray(),
            'payload' => $this->all(),
        ]);

        throw new HttpResponseException(response()->noContent(400));
    }
}
