<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Contracts\Http\Requests\GuildRosterManager\ValidatesGrmGuids;
use App\Data\GuildRosterManager\RosterRemovalsData;
use App\Http\Requests\GuildRosterManager\Concerns\AuthorizesSyncWrites;
use App\Models\GuildRosterManager\Ban;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the commit of a desktop sync: the optional ranks and the keys the file no longer lists.
 */
#[FailOnUnknownFields]
class CommitSyncRequest extends FormRequest implements ValidatesGrmGuids
{
    use AuthorizesSyncWrites;

    /**
     * Determine whether the user may write to the sync, and may update bans when the commit removes any.
     */
    public function authorize(): bool
    {
        if (! $this->canWriteSync()) {
            return false;
        }

        $bans = $this->input('removed.bans');

        return ! is_array($bans) || $bans === [] || $this->user()->can('update', Ban::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ranks' => ['sometimes', 'array', 'min:1', 'max:10'],
            'ranks.*' => ['required', 'string', 'max:50'],
            'removed' => ['sometimes', 'array'],
            'removed.members' => ['sometimes', 'array', 'max:1000'],
            'removed.members.*' => [...self::GUID_RULES, 'distinct'],
            'removed.leavers' => ['sometimes', 'array', 'max:5000'],
            'removed.leavers.*' => [...self::GUID_RULES, 'distinct'],
            'removed.bans' => ['sometimes', 'array'],
            'removed.bans.*' => [...self::GUID_RULES, 'distinct'],
            'removed.altGroups' => ['sometimes', 'array', 'max:1000'],
            'removed.altGroups.*' => ['string', 'max:32', 'distinct'],
        ];
    }

    /**
     * The ranks to store with the commit, or null to keep the previous ones.
     *
     * @return array<int, string>|null
     */
    public function ranks(): ?array
    {
        $ranks = $this->validated('ranks');

        return $ranks === null ? null : array_values($ranks);
    }

    /**
     * The keys the commit removes.
     */
    public function removed(): RosterRemovalsData
    {
        return RosterRemovalsData::from($this->validated('removed') ?? []);
    }
}
