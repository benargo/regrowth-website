<?php

namespace App\Http\Requests\WarcraftLogs;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class UpdateGuildRequest extends StoreGuildRequest
{
    /**
     * The guild's ID is fixed once added, so only the remaining fields are validated.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::except(parent::rules(), 'id');
    }
}
