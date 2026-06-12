<?php

namespace App\Http\Requests\RaidHelper;

use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EventWebhookRequest extends RaidHelperWebhookRequest
{
    private readonly Collection $eventRules;

    public function __construct()
    {
        parent::__construct();
        $this->eventRules = collect(EventData::getValidationRules([]))
            ->mapWithKeys(fn ($rules, $key) => [Str::camel($key) => $rules]);
    }

    protected function webhookRules(): Collection
    {
        return $this->eventRules;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return $this->eventRules->all();
    }
}
