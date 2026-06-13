<?php

namespace App\Http\Requests\RaidHelper;

use Illuminate\Support\Collection;

class UpdateCompositionRequest extends RaidHelperWebhookRequest
{
    protected function webhookRules(): Collection
    {
        return collect($this->rules())
            ->keys()
            ->filter(fn ($key) => ! str_contains($key, '.') && ! str_contains($key, '*'))
            ->mapWithKeys(fn ($key) => [$key => []]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'exists:events,raid_helper_event_id'],
            'title' => ['required', 'string'],
            'editPermissions' => ['required', 'string', 'in:managers,everyone'],
            'showRoles' => ['required', 'boolean'],
            'showClasses' => ['required', 'boolean'],
            'groupCount' => ['required', 'integer'],
            'slotCount' => ['required', 'integer'],

            'groups' => ['present', 'array'],
            'groups.*.name' => ['required', 'string'],
            'groups.*.position' => ['required', 'integer'],

            'dividers' => ['present', 'array'],
            'dividers.*.name' => ['required', 'string'],
            'dividers.*.position' => ['required', 'integer'],

            'classes' => ['present', 'array'],
            'classes.*.name' => ['required', 'string'],
            'classes.*.emoteId' => ['required', 'string'],
            'classes.*.specs' => ['present', 'array'],
            'classes.*.specs.*.name' => ['required', 'string'],
            'classes.*.specs.*.emoteId' => ['required', 'string'],
            'classes.*.specs.*.roleEmoteId' => ['required', 'string'],
            'classes.*.specs.*.color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'slots' => ['present', 'array'],
            'slots.*.id' => ['nullable', 'string'],
            'slots.*.name' => ['required', 'string'],
            'slots.*.groupNumber' => ['required', 'integer'],
            'slots.*.slotNumber' => ['required', 'integer'],
            'slots.*.className' => ['required', 'string'],
            'slots.*.classEmoteId' => ['required', 'string'],
            'slots.*.specName' => ['required', 'string'],
            'slots.*.specEmoteId' => ['required', 'string'],
            'slots.*.isConfirmed' => ['required', 'string', 'in:confirmed,unconfirmed'],
            'slots.*.color' => ['required', 'string'],
        ];
    }
}
