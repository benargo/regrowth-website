<?php

namespace App\Actions\GuildRosterManager;

use App\Data\GuildRosterManager\MembershipEventData;
use App\Models\Character;
use App\Models\CharacterGuildMembership;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Upserts a character's guild membership periods from its GRM join and leave history. Events are
 * processed oldest first: a join opens a period, and a leave closes the open period or creates a
 * period with no join date. Periods are never deleted.
 */
class SyncGuildMemberships
{
    use AsAction;

    /**
     * @param  list<MembershipEventData>  $events
     */
    public function handle(Character $character, array $events): void
    {
        $events = $this->order($events);
        $open = null;

        foreach ($events as $index => $event) {
            if ($event->type === 'joined') {
                $open = $this->openPeriod($character, $event, $events[$index + 1] ?? null);

                continue;
            }

            if ($open !== null) {
                $open->update(['left_on' => $event->date]);
                $open = null;

                continue;
            }

            CharacterGuildMembership::updateOrCreate(
                ['character_id' => $character->id, 'joined_on' => null, 'left_on' => $event->date],
                ['verified' => true],
            );
        }
    }

    /**
     * Opens the period for a join, adopting an orphan period whose leave matches the next event.
     */
    private function openPeriod(Character $character, MembershipEventData $join, ?MembershipEventData $next): CharacterGuildMembership
    {
        $exists = CharacterGuildMembership::where('character_id', $character->id)
            ->where('joined_on', $join->date)
            ->exists();

        if (! $exists && $next?->type === 'left') {
            $orphan = CharacterGuildMembership::where('character_id', $character->id)
                ->whereNull('joined_on')
                ->where('left_on', $next->date)
                ->first();

            if ($orphan !== null) {
                $orphan->update(['joined_on' => $join->date, 'verified' => true]);

                return $orphan;
            }
        }

        return CharacterGuildMembership::updateOrCreate(
            ['character_id' => $character->id, 'joined_on' => $join->date],
            ['verified' => true],
        );
    }

    /**
     * Sorts events oldest first. Among events on one date, a leave goes first while a period is
     * open and a join goes first otherwise.
     *
     * @param  list<MembershipEventData>  $events
     * @return list<MembershipEventData>
     */
    private function order(array $events): array
    {
        $byDate = [];
        foreach ($events as $event) {
            $byDate[$event->date][$event->type][] = $event;
        }
        ksort($byDate);

        $ordered = [];
        $isOpen = false;

        foreach ($byDate as $group) {
            while (($group['joined'] ?? []) !== [] || ($group['left'] ?? []) !== []) {
                $hasLeft = ($group['left'] ?? []) !== [];
                $hasJoined = ($group['joined'] ?? []) !== [];
                $type = match (true) {
                    $isOpen && $hasLeft => 'left',
                    $hasJoined => 'joined',
                    default => 'left',
                };
                $ordered[] = array_shift($group[$type]);
                $isOpen = $type === 'joined';
            }
        }

        return $ordered;
    }
}
