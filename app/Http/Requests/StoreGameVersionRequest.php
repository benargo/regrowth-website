<?php

namespace App\Http\Requests;

use App\Enums\Faction;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreGameVersionRequest extends FormRequest
{
    /**
     * Normalise the slug before validating.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('slug'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', $this->uniqueTitleRule()],
            'slug' => ['required', 'string', 'max:255', Rule::notIn($this->reservedSlugs()), Rule::unique('game_versions', 'slug')],
            'realm' => ['nullable', 'string', 'max:255'],
            'guild_name' => ['required', 'string', 'max:24'],
            'uses_surnames' => ['required', 'boolean'],
            'faction' => ['nullable', Rule::enum(Faction::class)],
            'release_date' => ['required', 'date'],
            'theme' => ['required', Rule::enum(Theme::class)],
            'blizzard_namespace' => ['nullable', Rule::enum(BlizzardNamespace::class)],
            'warcraft_logs_guild_id' => ['nullable', 'integer', Rule::exists(Guild::class, 'id')],
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
            'title.required' => 'The game version title is required.',
            'title.unique' => 'A game version with this title already exists.',
            'slug.required' => 'The slug is required.',
            'slug.not_in' => 'This slug is already used by another part of the site.',
            'slug.unique' => 'A game version with this slug already exists.',
            'guild_name.required' => 'The guild name is required.',
            'release_date.required' => 'The release date is required.',
            'theme.required' => 'Please choose a theme.',
            'warcraft_logs_guild_id.exists' => 'Choose one of the listed Warcraft Logs guilds.',
        ];
    }

    /**
     * Get the human-readable field names used in default messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'guild_name' => 'guild name',
            'uses_surnames' => 'surnames',
            'release_date' => 'release date',
            'blizzard_namespace' => 'Blizzard API namespace',
            'warcraft_logs_guild_id' => 'Warcraft Logs guild',
        ];
    }

    /**
     * Get the first segment of every registered URL, such as "manage" or "loot".
     *
     * @return list<string>
     */
    protected function reservedSlugs(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->map(fn (RouteDefinition $route): string => Str::before($route->uri(), '/'))
            ->reject(fn (string $segment): bool => $segment === '' || Str::startsWith($segment, '{'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The uniqueness rule applied to the title.
     */
    protected function uniqueTitleRule(): Unique
    {
        return Rule::unique('game_versions', 'title');
    }
}
