<?php

namespace App\Services\WarcraftLogs\Exceptions;

use Exception;

class GraphQLException extends Exception
{
    /**
     * @param  array<int, array{message: string, path?: array<string>, extensions?: array<string, mixed>}>  $errors
     */
    public function __construct(
        protected array $errors,
        string $message = 'GraphQL query failed',
    ) {
        parent::__construct($message.': '.($this->errors[0]['message'] ?? 'Unknown error'));
    }

    /**
     * Get all GraphQL errors from the response.
     *
     * @return array<int, array{message: string, path?: array<string>, extensions?: array<string, mixed>}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error message.
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0]['message'] ?? null;
    }

    /**
     * Check if any error matches a specific message pattern.
     */
    public function hasErrorMatching(string $pattern): bool
    {
        foreach ($this->errors as $error) {
            if (preg_match($pattern, $error['message'] ?? '')) {
                return true;
            }
        }

        return false;
    }
}
