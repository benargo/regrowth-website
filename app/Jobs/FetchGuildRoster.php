<?php

namespace App\Jobs;

use App\Enums\Gender;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Guild\GetGuildRosterRequest;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
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
     * The minimum character level the Blizzard profile API returns data for.
     */
    private const MIN_LEVEL = 10;

    public function __construct(
        public int $gameVersionId,
        public bool $bypassRateLimit = false,
    ) {}

    /**
     * Get the middleware the job should pass through. Deliberate refreshes
     * bypass the hourly limit so they are never silently dropped.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        if ($this->bypassRateLimit) {
            return [];
        }

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
        return ['blizzard', "game-version:{$this->gameVersionId}"];
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardConnector $blizzard): void
    {
        $gameVersion = GameVersion::findOrFail($this->gameVersionId);

        $roster = $blizzard->send(new GetGuildRosterRequest(
            $gameVersion->realm_slug,
            $gameVersion->guild_slug,
            $gameVersion->blizzard_namespace,
        ))->dto();

        foreach ($roster->members as $member) {
            try {
                $this->syncCharacter($blizzard, $gameVersion, $member);
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
    private function syncCharacter(BlizzardConnector $blizzard, GameVersion $gameVersion, GuildRosterMemberData $member): void
    {
        if ($member->character->level < self::MIN_LEVEL) {
            return;
        }

        $characterDto = $blizzard->send(new GetCharacterProfileRequest(
            $gameVersion->realm_slug,
            $member->character->name,
            $gameVersion->blizzard_namespace,
        ))->dto();

        $guildRank = GuildRank::where('sort_order', $member->rank)->firstOrFail();

        $character = Character::firstOrNew(['id' => $member->character->id]);
        $character->fill([
            'name' => $member->character->name,
            'level' => $member->character->level,
            'playable_class_id' => PlayableClass::find(data_get($characterDto, 'characterClass.id'))?->getKey(),
            'playable_race_id' => PlayableRace::find(data_get($characterDto, 'race.id'))?->getKey(),
            'gender' => Gender::tryFrom(data_get($characterDto, 'gender.name')),
            'game_version_id' => $gameVersion->id,
        ]);

        Character::withoutEvents(function () use ($character, $guildRank) {
            $character->rank()->associate($guildRank);
            $character->save();
        });
    }
}
