<?php

namespace App\Jobs;

use App\Models\Character;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Link characters to the Discord user who owns them, replacing any existing
 * link unless only unlinked characters are requested.
 */
#[Tries(1)]
class AttachCharactersToUser implements ShouldQueue
{
    use Queueable;

    /** @var array<int, int> */
    public readonly array $characterIds;

    /**
     * @param  iterable<int, Character>  $characters
     */
    public function __construct(
        public readonly string $userId,
        iterable $characters,
        public readonly bool $onlyUnlinked = false,
    ) {
        $this->characterIds = collect($characters)
            ->map(fn (Character $character): int => $character->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Throws ModelNotFoundException when the user is missing from the database.
     */
    public function handle(): void
    {
        $user = User::findOrFail($this->userId);

        $characters = Character::whereKey($this->characterIds)
            ->when($this->onlyUnlinked, fn (Builder $query) => $query->whereNull('user_id'))
            ->get();

        $user->characters()->saveMany($characters);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['characters', "user:{$this->userId}"];
    }
}
