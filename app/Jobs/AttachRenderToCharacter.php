<?php

namespace App\Jobs;

use App\Contracts\HasCharacterMedia;
use App\Events\Broadcasts\CharacterRenderAttached;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterMediaRequest;
use App\Models\Character;
use App\Support\MediaLibrary\OpaqueBoundsCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Uri;
use ValueError;

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
        $body = $renderConnector->send(new FetchCharacterMediaRequest($this->assetUrl))->body();

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
