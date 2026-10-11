<?php

namespace App\Http\Resources;

use App\Models\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

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
            'slug' => $this->slug,
            'color' => $this->color,
            'banner_url' => $this->getFirstMediaUrl(GameVersion::MEDIA_BANNER) ?: null,
            'icon_url' => $this->getFirstMediaUrl(GameVersion::MEDIA_ICON) ?: null,
            'logo_url' => $this->getFirstMediaUrl(GameVersion::MEDIA_LOGO) ?: null,
        ];

        if ($this->scope !== self::SCOPE_MANAGEMENT) {
            return $data;
        }

        return [
            ...$data,
            'realm' => $this->realm,
            'guild_name' => $this->guild_name,
            'faction' => $this->faction,
            'release_date' => $this->release_date?->toDateString(),
            'blizzard' => ['namespace' => $this->blizzard_namespace],
            'warcraftlogs' => [
                'guild' => $this->warcraftlogs_guild,
                'namespace' => [
                    'value' => $this->warcraftlogs_namespace,
                    'label' => $this->warcraftlogs_namespace?->label(),
                ],
            ],
            'phases_count' => $this->whenCounted('phases'),
            'items_count' => $this->whenCounted('items'),
            'characters_count' => $this->whenCounted('characters'),
            'guild_ranks_count' => $this->whenCounted('guildRanks'),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array:
     * details of each media collection, such as the most files it holds
     * (or null when it is unlimited) and the MIME types it accepts.
     *
     * @return array{media: array<string, array{limit: int|null, accepts: list<string>}>}
     */
    public function with(Request $request): array
    {
        return [
            'media' => $this->getRegisteredMediaCollections()
                ->mapWithKeys(fn (MediaCollection $collection): array => [
                    $collection->name => [
                        'limit' => $collection->collectionSizeLimit ?: null,
                        'accepts' => $collection->acceptsMimeTypes,
                    ],
                ])
                ->all(),
        ];
    }
}
