<?php

namespace App\Services\Blizzard\Data;

use App\Services\Blizzard\MediaService;
use App\Services\Blizzard\PlayableClassService;
use Illuminate\Support\Arr;

readonly class PlayableClass
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $icon_url,
    ) {}

    /**
     * Build a PlayableClass from a Blizzard API class ID.
     */
    public static function fromId(int $id): self
    {
        $service = app(PlayableClassService::class);
        $data = $service->find($id);

        return new self(
            id: $id,
            name: Arr::get($data, 'name', 'Unknown Class'),
            icon_url: $service->iconUrl($id),
        );
    }

    /**
     * Build a fallback PlayableClass when the class is unknown or unavailable.
     */
    public static function unknown(): self
    {
        return new self(
            id: null,
            name: 'Unknown Class',
            icon_url: app(MediaService::class)->getIconUrlByName('inv_misc_questionmark'),
        );
    }

    /**
     * @return array{id: int|null, name: string, icon_url: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon_url' => $this->icon_url,
        ];
    }
}
