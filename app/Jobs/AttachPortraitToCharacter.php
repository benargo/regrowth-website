<?php

namespace App\Jobs;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterPortraitRequest;
use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;

class AttachPortraitToCharacter implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $characterId,
        public readonly string $assetUrl,
    ) {}

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("character-portrait:{$this->characterId}"))
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

        if ($character->hasMedia(HasCharacterMedia::MEDIA_COLLECTION)) {
            return;
        }

        $fileName = $this->getPortraitFileName($this->assetUrl);
        $body = $renderConnector->send(new FetchCharacterPortraitRequest($this->assetUrl))->body();

        $character->addMediaFromString($body)
            ->usingFileName($fileName)
            ->withCustomProperties(['size' => HasCharacterMedia::DEFAULT_MEDIA_SIZE])
            ->toMediaCollection(HasCharacterMedia::MEDIA_COLLECTION);
    }

    private function getPortraitFileName(string $url): string
    {
        return (string) Str::of($url)->afterLast('/')->before('?');
    }
}
