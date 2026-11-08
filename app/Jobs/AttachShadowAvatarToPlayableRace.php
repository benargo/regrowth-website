<?php

namespace App\Jobs;

use App\Contracts\HasShadowAvatars;
use App\Enums\Gender;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchShadowAvatarRequest;
use App\Models\PlayableRace;
use finfo;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;
use UnexpectedValueException;

/**
 * Mirrors the Blizzard shadow avatar for a playable race and gender into the
 * race's `shadow_avatars` collection. Does nothing when that gender is already
 * stored. Logs a warning and finishes without storing or retrying when the render
 * CDN responds with an HTTP error status, remembering the miss for seven days only
 * for a 403 or 404, and fails without retrying when the response body is empty or
 * is not an image. Unique per race and gender while queued.
 */
#[Tries(3)]
#[Backoff(300, 300)]
#[UniqueFor(3600)]
class AttachShadowAvatarToPlayableRace implements HasShadowAvatars, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $playableRaceId,
        public readonly Gender $gender,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->playableRaceId}-{$this->gender->id()}";
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("shadow-avatar:{$this->uniqueId()}"))
                ->releaseAfter(60),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard', "playable-race:{$this->playableRaceId}"];
    }

    public function handle(RenderConnector $renderConnector): void
    {
        $playableRace = PlayableRace::findOrFail($this->playableRaceId);

        if ($playableRace->hasShadowAvatar($this->gender)) {
            return;
        }

        $request = new FetchShadowAvatarRequest($this->playableRaceId, $this->gender);

        try {
            $response = $renderConnector->send($request);
        } catch (RequestException $exception) {
            $status = $exception->getResponse()->status();
            $this->logUnavailable($status);
            $this->rememberMissWhenAbsent($playableRace, $status);

            return;
        }

        if ($response->failed()) {
            $this->logUnavailable($response->status());
            $this->rememberMissWhenAbsent($playableRace, $response->status());

            return;
        }

        $body = $response->body();

        if ($body === '' || ! str_starts_with((new finfo(FILEINFO_MIME_TYPE))->buffer($body) ?: '', 'image/')) {
            $this->fail(new UnexpectedValueException(
                "Blizzard returned an empty or non-image shadow avatar for playable race {$this->playableRaceId}.",
            ));

            return;
        }

        $playableRace->addMediaFromString($body)
            ->usingFileName($request->fileName())
            ->withCustomProperties(['gender' => $this->gender->id()])
            ->toMediaCollection(self::MEDIA_COLLECTION);
    }

    private function rememberMissWhenAbsent(PlayableRace $playableRace, int $status): void
    {
        if (in_array($status, [403, 404], true)) {
            $playableRace->rememberShadowAvatarMissing($this->gender);
        }
    }

    private function logUnavailable(int $status): void
    {
        Log::warning('Blizzard shadow avatar unavailable.', [
            'playable_race_id' => $this->playableRaceId,
            'gender' => $this->gender->id(),
            'status' => $status,
        ]);
    }
}
