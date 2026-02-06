<?php

namespace App\Jobs;

use App\Models\Character;
use App\Notifications\DiscordNotifiable;
use App\Notifications\LevelCapAchieved;
use App\Services\Blizzard\GuildService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class CheckLevelCapAchievements implements ShouldQueue
{
    use Queueable;

    protected const LEVEL_CAP = 70;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('check-level-cap-achievements'),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(GuildService $guildService): void
    {
        // Ensure new guild members are synced to the database
        $guildService->shouldUpdateCharacters(true)->roster();

        // Get all level 70 members from the guild
        $levelCapMembers = $guildService->members()
            ->filter(fn ($member) => $member->character['level'] >= self::LEVEL_CAP);

        if ($levelCapMembers->isEmpty()) {
            return;
        }

        // Find characters that haven't been marked as reaching level cap yet
        $levelCapMemberIds = $levelCapMembers->pluck('character.id')->toArray();

        $newLevelCapCharacters = Character::whereIn('id', $levelCapMemberIds)
            ->whereNull('reached_level_cap_at')
            ->get();

        if ($newLevelCapCharacters->isEmpty()) {
            return;
        }

        // Update the characters with the current timestamp
        Character::whereIn('id', $newLevelCapCharacters->pluck('id'))
            ->update(['reached_level_cap_at' => now()]);

        // Send congratulations notification
        $characterNames = $newLevelCapCharacters->pluck('name')->toArray();

        DiscordNotifiable::channel('tbc_chat')->notify(
            new LevelCapAchieved($characterNames)
        );
    }
}
