<?php

namespace App\Jobs;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchGuildRoster implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new RateLimitedWithRedis('fetch-guild-roster-job'))->dontRelease(),
        ];
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['blizzard'];
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardConnector $blizzard): void
    {
        $roster = $blizzard->send(new GetGuildRosterRequest(
            $blizzard->defaultRealmSlug(),
            $blizzard->defaultGuildSlug(),
        ))->dto();

        foreach ($roster->members as $member) {
            try {
                $this->syncCharacter($blizzard, $member);
            } catch (Throwable $e) {
                Log::warning('Failed to sync character from guild roster.', [
                    'character_id' => $member->character->id,
                    'character_name' => $member->character->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Sync a single character from the guild roster data.
     */
    private function syncCharacter(BlizzardConnector $blizzard, GuildRosterMemberData $member): void
    {
        if ($member->character->level < 60) {
            return;
        }

        $guildRank = GuildRank::where('position', $member->rank)->firstOrFail();
        $playableClass = PlayableClass::find($member->character->playableClass?->id);

        $character = Character::firstOrNew(['id' => $member->character->id]);
        $character->fill([
            'name' => $member->character->name,
            'level' => $member->character->level,
            'playable_race' => [
                'id' => $member->character->playableRace->id,
                'name' => $member->character->playableRace->name,
            ],
        ]);

        Character::withoutEvents(function () use ($character, $playableClass, $guildRank) {
            if (is_a($playableClass, PlayableClass::class)) {
                $character->playableClass()->associate($playableClass);
            }
            $character->rank()->associate($guildRank);
            $character->save();
        });
    }
}
