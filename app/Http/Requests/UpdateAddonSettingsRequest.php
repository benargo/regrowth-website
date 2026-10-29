<?php

namespace App\Http\Requests;

use App\Models\GameVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAddonSettingsRequest extends FormRequest
{
    /**
     * Determine if the user may view the officer dashboard and change every settings section present in the request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('view-officer-dashboard')) {
            return false;
        }

        $sectionPermissions = [
            'councillor_ids' => 'update-characters',
            'counted_rank_ids' => 'edit-datasets',
            'counted_tag_ids' => 'update-warcraft-logs-tags',
        ];

        foreach ($sectionPermissions as $key => $permission) {
            if ($this->has($key) && ! $user->isAuthorizedTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $gameVersion = $this->findGameVersion();

        return [
            'game_version' => ['required', 'string', Rule::exists('game_versions', 'slug')],
            'councillor_ids' => ['sometimes', 'array'],
            'councillor_ids.*' => [
                'integer',
                Rule::exists('characters', 'id')->where('game_version_id', $gameVersion?->id),
            ],
            'counted_rank_ids' => ['sometimes', 'array'],
            'counted_rank_ids.*' => [
                'integer',
                Rule::exists('guild_ranks', 'id')->where('game_version_id', $gameVersion?->id),
            ],
            'counted_tag_ids' => ['sometimes', 'array'],
            'counted_tag_ids.*' => [
                'integer',
                $gameVersion?->warcraft_logs_guild_id === null
                    ? Rule::in([])
                    : Rule::exists('warcraft_logs_guild_tags', 'id')->where('warcraft_logs_guild_id', $gameVersion->warcraft_logs_guild_id),
            ],
        ];
    }

    /**
     * Get the game version the settings belong to.
     */
    public function gameVersion(): GameVersion
    {
        return $this->findGameVersion() ?? abort(404);
    }

    /**
     * Find the game version named by the request's slug, queried once per request.
     */
    protected function findGameVersion(): ?GameVersion
    {
        return once(fn (): ?GameVersion => GameVersion::firstWhere('slug', $this->string('game_version')->toString()));
    }
}
