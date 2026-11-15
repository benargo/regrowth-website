<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Enums\GuildRosterManager\RowReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the GRM sync dashboard's selected upload and row reason filter.
 */
class SyncDashboardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'upload' => ['nullable', 'uuid'],
            'reason' => ['nullable', Rule::enum(RowReason::class)],
        ];
    }

    /**
     * Get the selected row reason, if any.
     */
    public function reason(): ?RowReason
    {
        return $this->enum('reason', RowReason::class);
    }
}
