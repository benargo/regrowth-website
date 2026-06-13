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

    protected function prepareForValidation(): void
    {
        $allowed = $this->webhookRules()
            ->keys()
            ->filter(fn ($key) => ! str_contains($key, '.') && ! str_contains($key, '*'));

        $unexpected = Arr::reject($this->keys(), fn ($key) => $allowed->contains($key));

        if (count($unexpected) > 0) {
            Log::debug('Raid Helper webhook contained unexpected keys', [
                'request' => static::class,
                'unexpected_keys' => array_values($unexpected),
                'allowed_keys' => $allowed->values()->all(),
                'payload' => $this->all(),
            ]);

            abort(400);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::debug('Raid Helper webhook failed validation', [
            'request' => static::class,
            'errors' => $validator->errors()->toArray(),
            'payload' => $this->all(),
        ]);

        throw new HttpResponseException(response()->noContent(400));
    }
}
