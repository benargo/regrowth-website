<?php

namespace App\Http\Resources;

use App\Models\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A scoped view of a game version.
 *
 * The default scope is the lean shape used wherever a version is only being
 * labelled (e.g. the GRM upload page, which selects just these columns), so
 * it must not grow. forManagement() adds every editable field plus any usage
 * counts loaded with withCount(GameVersion::USAGE_RELATIONS).
 *
 * @mixin GameVersion
 */
class GameVersionResource extends JsonResource
{
    protected const SCOPE_DEFAULT = 'default';

    protected const SCOPE_MANAGEMENT = 'management';

    protected string $scope = self::SCOPE_DEFAULT;

    /**
     * The full shape for the Officers' game version management pages.
     */
    public static function forManagement(mixed $resource): static
    {
        return static::withScope($resource, self::SCOPE_MANAGEMENT);
    }

    /**
     * @param  iterable<int, GameVersion>  $resources
     * @return Collection<int, static>
     */
    public static function collectionForManagement(iterable $resources): Collection
    {
        return collect($resources)->map(fn (GameVersion $gameVersion) => static::forManagement($gameVersion));
    }

    protected static function withScope(mixed $resource, string $scope): static
    {
        $instance = new static($resource);
        $instance->scope = $scope;

        return $instance;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'theme' => $this->theme,
            'banner_class' => $this->theme->bannerCssClass(),
        ];

        if ($this->scope !== self::SCOPE_MANAGEMENT) {
            return $data;
        }

        return [
            ...$data,
            'realm' => $this->realm,
            'faction' => $this->faction,
            'release_date' => $this->release_date?->toDateString(),
            'blizzard_namespace' => $this->blizzard_namespace,
            'warcraftlogs_guild' => $this->warcraftlogs_guild,
            'warcraftlogs_expansion' => $this->warcraftlogs_expansion,
            'phases_count' => $this->whenCounted('phases'),
            'items_count' => $this->whenCounted('items'),
            'characters_count' => $this->whenCounted('characters'),
        ];
    }
}
