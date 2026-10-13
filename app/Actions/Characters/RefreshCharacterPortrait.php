<?php

namespace App\Actions\Characters;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Jobs\AttachPortraitToCharacter;
use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Queues a fresh Blizzard portrait for a character that has none, or whose
 * portrait is older than the refresh period. Unique per character while
 * queued, so repeated page loads don't stack refreshes for the same character.
 */
class RefreshCharacterPortrait implements ShouldBeUnique
{
    use AsAction;

    /**
     * How long a stored portrait stays fresh: three days.
     */
    public const int STALE_AFTER_SECONDS = 259200;

    /**
     * Release the uniqueness lock after this many seconds even if the job never runs.
     */
    public int $jobUniqueFor = 3600;

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
     * One queued refresh per character.
     */
    public function getJobUniqueId(Character $character): string
    {
        return (string) $character->id;
    }

    /**
     * Queue a refresh only when the character's portrait is missing or stale,
     * so page loads don't queue a job per character that would do nothing.
     * Load the character's media first to avoid a query per character.
     */
    public static function queueIfStale(Character $character): void
    {
        if (self::isStale($character)) {
            self::dispatch($character);
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
