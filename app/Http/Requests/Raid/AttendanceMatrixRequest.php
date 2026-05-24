<?php

namespace App\Http\Requests\Raid;

use App\Services\Attendance\FiltersData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceMatrixRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return FiltersData::rules($this->all());
    }

    /**
     * Build a validated FiltersData DTO from the request input.
     */
    public function filters(): FiltersData
    {
        return FiltersData::fromArray($this->validated());
    }

    /**
     * The minimum date allowed for date filters, exposed so the view can render the date picker bounds.
     */
    public function resolveMinDate(): ?string
    {
        return FiltersData::resolveMinDate();
    }
}
