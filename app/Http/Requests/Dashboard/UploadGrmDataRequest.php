<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UploadGrmDataRequest extends FormRequest
{
    protected const REQUIRED_HEADERS = [
        'Name',
        'Rank',
        'Level',
        'Last Online (Days)',
        'Main/Alt',
        'Player Alts',
    ];

    protected ?string $detectedDelimiter = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isOfficer();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grm_data' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grm_data.required' => 'GRM data is required.',
            'grm_data.string' => 'GRM data must be a string.',
        ];
    }

    /**
     * Configure the validator instance with custom CSV validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $grmData = $this->input('grm_data');
            $lines = preg_split('/\r\n|\r|\n/', $grmData);

            if (count($lines) < 2) {
                $validator->errors()->add('grm_data', 'CSV must contain a header row and at least one data row.');

                return;
            }

            $headerLine = $lines[0];

            // Detect delimiter (comma or semicolon)
            $commaCount = substr_count($headerLine, ',');
            $semicolonCount = substr_count($headerLine, ';');

            if ($commaCount === 0 && $semicolonCount === 0) {
                $validator->errors()->add('grm_data', 'CSV must use comma or semicolon as delimiter.');

                return;
            }

            $this->detectedDelimiter = $semicolonCount > $commaCount ? ';' : ',';
            $headers = str_getcsv($headerLine, $this->detectedDelimiter);

            // Validate required headers exist
            $missingHeaders = array_diff(self::REQUIRED_HEADERS, $headers);
            if (! empty($missingHeaders)) {
                $validator->errors()->add(
                    'grm_data',
                    'Missing required headers: '.implode(', ', $missingHeaders)
                );
            }
        });
    }

    /**
     * Get the detected CSV delimiter.
     */
    public function getDelimiter(): string
    {
        return $this->detectedDelimiter ?? ',';
    }

    /**
     * Get the parsed CSV data as an array.
     *
     * @return array{delimiter: string, headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public function getParsedCsvData(): array
    {
        $grmData = $this->input('grm_data');
        $lines = preg_split('/\r\n|\r|\n/', $grmData);
        $delimiter = $this->getDelimiter();

        $headers = str_getcsv($lines[0], $delimiter);
        $rows = [];

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $values[$index] ?? '';
            }
            $rows[] = $row;
        }

        return [
            'delimiter' => $delimiter,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}
