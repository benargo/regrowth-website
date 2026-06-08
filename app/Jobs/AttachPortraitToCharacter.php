<?php

namespace App\Jobs;

use App\Contracts\HasCharacterMedia;
use App\Enums\Gender;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Middleware\MergeUriQuery;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterPortraitRequest;
use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

class AttachPortraitToCharacter implements HasCharacterMedia, ShouldQueue
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

    public function handle(RenderConnector $renderConnector, BlizzardConnector $blizzardConnector): void
    {
        $character = Character::findOrFail($this->characterId);

        $this->syncGender($character, $blizzardConnector);

        if ($character->hasMedia(self::MEDIA_COLLECTION)) {
            return;
        }

        $assetUrl = $this->withFallback($character);
        $fileName = $this->assetUrl->pathSegments()->last();
        $request = new FetchCharacterPortraitRequest($assetUrl);

        if ($assetUrl->query()->all() !== []) {
            $request->middleware()->onRequest(new MergeUriQuery($assetUrl), 'mergeUriQuery');
        }

        $body = $renderConnector->send($request)->body();

        $character->addMediaFromString($body)
            ->usingFileName($fileName)
            ->withCustomProperties(['size' => self::DEFAULT_MEDIA_SIZE])
            ->toMediaCollection(self::MEDIA_COLLECTION);
    }

    /**
     * Fetches the character's gender from the Blizzard API and persists it if not already set.
     * Failures are swallowed so portrait attachment can still proceed.
     */
    private function syncGender(Character $character, BlizzardConnector $blizzardConnector): void
    {
        if ($character->gender !== null) {
            return;
        }

        try {
            $profile = $blizzardConnector->send(new GetCharacterProfileRequest(
                $blizzardConnector->defaultRealmSlug(),
                Str::lower($character->name),
            ))->dto();

            $character->gender = Gender::from(data_get($profile, 'gender.name'));
            $character->saveQuietly();
        } catch (BlizzardRequestException|\ValueError) {
            // Gender sync is best-effort; portrait attachment can still proceed.
        }
    }

    private function withFallback(Character $character): Uri
    {
        if ($character->playable_race_id === null || $character->gender === null) {
            return $this->assetUrl;
        }

        return $this->assetUrl->withQuery([
            'alt' => "/shadow/avatar/{$character->playable_race_id}-{$character->gender->id()}.jpg",
        ]);
    }
}
