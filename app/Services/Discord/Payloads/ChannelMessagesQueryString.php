<?php

namespace App\Services\Discord\Payloads;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;

class ChannelMessagesQueryString extends Data
{
    public function __construct(
        public readonly Message|string|null $around = null,
        public readonly Message|string|null $before = null,
        public readonly Message|string|null $after = null,
        public readonly int $limit = 50,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();
            $provided = array_filter(['around', 'before', 'after'], fn (string $key) => ! empty($data[$key]));

            if (count($provided) > 1) {
                $validator->errors()->add('around', 'Only one of around, before, or after may be specified at a time.');
            }
        });
    }
}
