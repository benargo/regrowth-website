<?php

namespace App\Actions\Characters;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Queues a fresh Blizzard portrait for a character that has none, or whose
 * portrait is older than the refresh period.
 */
class RefreshCharacterPortrait
{
    use AsAction;

    /**
     * How long a stored portrait stays fresh: three days.
     */
    public const int STALE_AFTER_SECONDS = 259200;

    public function __construct(
        private BlizzardConnector $blizzard,
    ) {}

    public function handle(Character $character): void
    {
        $gameVersion = $character->gameVersion;

        if ($gameVersion?->realm_slug === null || ! self::isStale($character)) {
            return;
        }

        try {
            $dto = $this->blizzard->send(new GetCharacterMediaRequest(
                $gameVersion->realm_slug,
                $character->name,
                $gameVersion->blizzard_namespace,
            ))->dto();

            $avatarAsset = collect($dto->assets)->first(fn ($asset) => $asset->key === 'avatar');

            if ($avatarAsset !== null) {
                AttachPortraitToCharacter::dispatch($character->id, $avatarAsset->value);
            }
        } catch (\Throwable) {
            // Blizzard outage must not break the page render.
        }
    }

    /**
     * Whether the character has no portrait, or one stored at least the refresh period ago.
     */
    public static function isStale(Character $character): bool
    {
        $portrait = $character->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION);

        return $portrait === null
            || $portrait->created_at->lte(now()->subSeconds(self::STALE_AFTER_SECONDS));
    }
}
