<?php

namespace App\Jobs;

use App\Models\Character;
use App\Models\GuildRank;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\ValueObjects\PlayableClass;
use App\Services\Blizzard\ValueObjects\PlayableRace;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class UpdateCharacterFromRoster implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * The character being updated.
     */
    protected ?Character $character = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $characterData
    ) {}

    /**
     * Define the middleware for the job.
     */
    public function middleware(): array
    {
        return [
            Skip::when(Arr::get($this->characterData, 'character.level') < 60),
            new WithoutOverlapping(Arr::get($this->characterData, 'character.id')),
        ];
    }

    /**
     * Execute the job.
     *
     * @throws ModelNotFoundException
     */
    public function handle(): void
    {
        $this->character = Character::firstOrNew(['id' => Arr::get($this->characterData, 'character.id')]);

        $blizzard = app(BlizzardService::class);
        $classId = Arr::get($this->characterData, 'character.playable_class.id');
        $raceId = Arr::get($this->characterData, 'character.playable_race.id');

        $this->character->fill([
            'name' => Arr::get($this->characterData, 'character.name'),
            'playable_class' => $classId !== null
                ? PlayableClass::fromApiResponse($blizzard->findPlayableClass($classId))
                : null,
            'playable_race' => $raceId !== null
                ? PlayableRace::fromApiResponse($blizzard->findPlayableRace($raceId))
                : null,
        ]);

        $guildRank = GuildRank::where('position', Arr::get($this->characterData, 'rank'))->firstOrFail();
        $this->character->rank()->associate($guildRank);

        $this->character->save();
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        if ($exception instanceof ModelNotFoundException) {
            Log::error('Guild rank not found for character update.', [
                'character_id' => $this->character?->id,
                'rank_position' => Arr::get($this->characterData, 'rank'),
                'error' => $exception->getMessage(),
            ]);
        } else {
            Log::error('UpdateCharacterFromRoster job failed.', [
                'character_id' => $this->character?->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return ['blizzard', 'guild', 'roster'];
    }
}
