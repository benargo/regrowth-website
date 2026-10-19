<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use App\Models\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

/**
 * Transform a game version, optionally limited to the `management` or `switcher` scope.
 * The media URLs, warcraftlogs (with the guild page URL) and the usage counts
 * are omitted unless loaded.
 */
class GameVersionResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $links = null;

    /**
     * Attach the management action links for this game version.
     *
     * @param  array<string, mixed>  $links
     */
    public function withLinks(array $links): static
    {
        $this->links = $links;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'color' => $this->color,
            'banner_url' => $this->whenLoaded('media', fn (): ?string => $this->getFirstMediaUrl(GameVersion::MEDIA_BANNER) ?: null),
            'icon_url' => $this->whenLoaded('media', fn (): ?string => $this->getFirstMediaUrl(GameVersion::MEDIA_ICON) ?: null),
            'logo_url' => $this->whenLoaded('media', fn (): ?string => $this->getFirstMediaUrl(GameVersion::MEDIA_LOGO) ?: null),
            'realm' => $this->realm,
            'guild_name' => $this->guild_name,
            'uses_surnames' => $this->uses_surnames,
            'faction' => $this->faction,
            'release_date' => $this->release_date?->toDateString(),
            'blizzard' => ['namespace' => $this->blizzard_namespace],
            'warcraftlogs' => $this->when($this->relationLoaded('warcraftLogsGuild'), fn (): array => [
                'guild' => $this->warcraft_logs_guild_id,
                'namespace' => [
                    'value' => $this->warcraftLogsGuild?->namespace,
                    'label' => $this->warcraftLogsGuild?->namespace->label(),
                ],
                'url' => $this->warcraftLogsGuild?->namespace->guildUrl($this->warcraft_logs_guild_id, $this->guild_slug),
            ]),
            'phases_count' => $this->whenCounted('phases'),
            'items_count' => $this->whenCounted('items'),
            'characters_count' => $this->whenCounted('characters'),
            'guild_ranks_count' => $this->whenCounted('guildRanks'),
            'links' => $this->when($this->links !== null, fn (): ?array => $this->links),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return [
            'management' => [
                'id', 'title', 'slug', 'color', 'banner_url', 'icon_url', 'logo_url',
                'realm', 'guild_name', 'uses_surnames', 'faction', 'release_date', 'blizzard', 'warcraftlogs',
                'phases_count', 'items_count', 'characters_count', 'guild_ranks_count', 'links',
            ],
            'switcher' => ['id', 'title', 'slug', 'color', 'icon_url', 'release_date', 'warcraftlogs'],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array:
     * the MIME types each media collection accepts. Every collection holds a
     * single file, so no limit is sent.
     *
     * @return array{media: array<string, array{accepts: list<string>}>}
     */
    public function with(Request $request): array
    {
        return [
            'media' => $this->getRegisteredMediaCollections()
                ->mapWithKeys(fn (MediaCollection $collection): array => [
                    $collection->name => ['accepts' => $collection->acceptsMimeTypes],
                ])
                ->all(),
        ];
    }
}
