<?php

namespace App\Jobs;

use App\Contracts\HasCharacterMedia;
use App\Events\Broadcasts\CharacterRenderAttached;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterPortraitRequest;
use App\Models\Character;
use App\Support\MediaLibrary\OpaqueBoundsCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Uri;
use ValueError;

/**
 * Fetches a character's full-body `main-raw` render from the Blizzard render
 * CDN and attaches it to the character's render collection.
 *
 * Sibling of AttachPortraitToCharacter, with three deliberate differences:
 *
 *  - It writes to MEDIA_COLLECTION_RENDER, not MEDIA_COLLECTION. Both are
 *    singleFile(), so sharing one collection would make each job evict the
 *    other's asset.
 *  - It performs no gender sync and builds no `alt` fallback URL. Those exist
 *    on the avatar job so a missing avatar degrades to Blizzard's generic
 *    race/gender silhouette; a missing render degrades to our own silhouette
 *    placeholder instead, so neither is needed here.
 *  - Its overlap lock is keyed on "character-render:{id}", so a render fetch
 *    and an avatar fetch for the same character do not block each other.
 */
class AttachRenderToCharacter implements HasCharacterMedia, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public readonly Uri $assetUrl;

    public function __construct(
        public readonly int $characterId,
        Uri|string $assetUrl,
    ) {
        $this->assetUrl = is_a($assetUrl, Uri::class) ? $assetUrl : Uri::of($assetUrl);
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("character-render:{$this->characterId}"))
                ->releaseAfter(60),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [300, 300];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard', 'character:'.$this->characterId];
    }

    public function handle(RenderConnector $renderConnector): void
    {
        $character = Character::findOrFail($this->characterId);

        if ($character->hasMedia(self::MEDIA_COLLECTION_RENDER)) {
            return;
        }

        $fileName = $this->assetUrl->pathSegments()->last()
            ?? throw new ValueError("Cannot extract filename from asset URL: {$this->assetUrl}");

        // Passing an absolute Uri routes through RenderRequest::handleUri(),
        // which validates the Blizzard render host and uses the path verbatim.
        // No size is applied, so the render arrives at full resolution.
        $body = $renderConnector->send(new FetchCharacterPortraitRequest($this->assetUrl))->body();

        // The character's visible height varies per race/pose within Blizzard's
        // fixed-size transparent canvas, so the homepage needs these bounds to
        // scale renders by apparent character height rather than canvas height.
        $bounds = (new OpaqueBoundsCalculator)->calculate($body);

        $character->addMediaFromString($body)
            ->usingFileName($fileName)
            ->withCustomProperties($bounds !== null ? [
                'visible_top' => $bounds['top'],
                'visible_bottom' => $bounds['bottom'],
            ] : [])
            ->toMediaCollection(self::MEDIA_COLLECTION_RENDER);

        CharacterRenderAttached::dispatch($character->id);
    }
}
