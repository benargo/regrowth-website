<?php

namespace App\Http\Requests\Raid;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportLinksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->report);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentCode = $this->report?->code;

        return [
            'codes' => ['required', 'array', 'min:1'],
            'codes.*' => ['required', 'string', 'exists:wcl_reports,code', "not_in:{$currentCode}"],
        ];
    }
}
