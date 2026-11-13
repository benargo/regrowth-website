<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Data\GuildRosterManager\OpenSyncData;
use App\Rules\GuildRosterManager\MatchesGameVersionGuild;
use App\Rules\GuildRosterManager\SyncableGameVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

/**
 * Validates a request from the desktop app to open a schema 2 sync, refusing any key outside the open's body.
 */
#[FailOnUnknownFields]
class OpenSyncRequest extends FormRequest
{
    /**
     * Get the validation rules for the open's body, keyed by every allowed key.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(SyncableGameVersion $syncableGameVersion, MatchesGameVersionGuild $matchesGameVersionGuild): array
    {
        return [
            'schema' => ['required', 'in:2'],
            'gameVersion' => ['required', 'string', $syncableGameVersion],
            'guildKey' => ['required', 'string', 'max:64', $matchesGameVersionGuild],
            'addonVersion' => ['required', 'string', 'max:32'],
            'openTicket' => ['present', 'nullable', 'string', 'max:2048'],
            'baseRevision' => ['required', 'integer', 'min:0'],
            'fileHash' => ['required', 'string', 'regex:/^[0-9a-f]{64}$/'],
        ];
    }

    /**
     * Log a warning with the body's addon version and game version and the failing keys, never their values,
     * then throw the validation exception.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        Log::warning('Desktop GRM sync open failed validation.', [
            'addonVersion' => $this->input('addonVersion'),
            'gameVersion' => $this->input('gameVersion'),
            'errors' => $validator->errors()->keys(),
        ]);

        parent::failedValidation($validator);
    }

    /**
     * Build the open's data object from the validated body.
     */
    public function sync(): OpenSyncData
    {
        return OpenSyncData::from($this->validated());
    }
}
