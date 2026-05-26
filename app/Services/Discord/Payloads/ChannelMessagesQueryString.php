<?php

namespace App\Services\Discord\Payloads;

use App\Services\Discord\Resources\Message;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\WithoutValidation;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ChannelMessagesQueryString extends Data
{
    public function __construct(
        #[WithoutValidation]
        public readonly Message|string|Optional $around = new Optional,
        #[WithoutValidation]
        public readonly Message|string|Optional $before = new Optional,
        #[WithoutValidation]
        public readonly Message|string|Optional $after = new Optional,
        public readonly int $limit = 50,
    ) {}

    public function toArray(): array
    {
        // Spatie Data omits Optional fields automatically. This override only exists
        // to flatten Message instances to their snowflake ID instead of serialising
        // them as a nested array.
        $cursors = array_filter([
            'around' => $this->around instanceof Message ? $this->around->id : $this->around,
            'before' => $this->before instanceof Message ? $this->before->id : $this->before,
            'after' => $this->after instanceof Message ? $this->after->id : $this->after,
        ], fn ($v) => ! $v instanceof Optional);

        return $cursors + ['limit' => $this->limit];
    }

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
