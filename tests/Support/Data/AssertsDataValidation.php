<?php

namespace Tests\Support\Data;

use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

trait AssertsDataValidation
{
    /**
     * Assert that validating the payload against the data class fails with an error on the given key.
     *
     * @param  class-string<Data>  $dataClass
     * @param  array<string, mixed>  $payload
     */
    protected function assertDataRejects(string $dataClass, array $payload, string $key): void
    {
        try {
            $dataClass::validate($payload);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors(), "Expected an error on [{$key}], got: ".implode(', ', array_keys($exception->errors())));

            return;
        }

        $this->fail("Expected {$dataClass} to reject [{$key}].");
    }
}
