<?php

namespace App\Jobs;

use App\Models\Character;
use App\Models\GuildRank;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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
            Skip::when($this->characterData['character']['level'] < 60),
            new WithoutOverlapping($this->characterData['character']['id']),
        ];
    }

    /**
     * Execute the job.
     *
     * @throws ModelNotFoundException
     */
    public function handle(): void
    {
        $this->character = Character::firstOrNew(['id' => $this->characterData['character']['id']]);

        $this->character->fill(['name' => $this->characterData['character']['name']]);

        $guildRank = GuildRank::where('position', $this->characterData['rank'])->firstOrFail();
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
                'rank_position' => $this->characterData['rank'],
                'error' => $exception->getMessage(),
            ]);
        } else {
            Log::error('UpdateCharacterFromRoster job failed.', [
                'character_id' => $this->character?->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
