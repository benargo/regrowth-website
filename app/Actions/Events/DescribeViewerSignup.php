<?php

namespace App\Actions\Events;

use App\Data\Navigation\SignupLineData;
use App\Enums\SignupStatus;
use App\Models\Event;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Describes the member's place in the event: away, cancelled, in the team,
 * on the bench, signed up, or not signed up.
 */
class DescribeViewerSignup
{
    use AsAction;

    public function handle(Event $event, User $user): SignupLineData
    {
        $characterIds = $user->characters()->pluck('characters.id');

        $absence = PlannedAbsence::where(fn (Builder $query) => $query->where('user_id', $user->id)->orWhereIn('character_id', $characterIds))
            ->whereDate('start_date', '<=', $event->start_time)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $event->start_time))
            ->first();

        if ($absence !== null) {
            return new SignupLineData($absence->reason->key->navigationLabel(), $absence->reason->icon);
        }

        $signups = $event->characters()->whereKey($characterIds)->get()->pluck('pivot');

        if ($signups->isEmpty()) {
            return new SignupLineData("You haven't signed up yet.");
        }

        if ($signups->every(fn ($pivot): bool => $pivot->signup_status === SignupStatus::Cancelled)) {
            return new SignupLineData("You said you can't make it.");
        }

        if (! $event->assignments()->exists() && ! $event->assignmentGroups()->exists()) {
            return new SignupLineData("You're signed up.");
        }

        $isInTeam = $signups->contains(fn ($pivot): bool => $pivot->slot_number !== null && ! $pivot->is_benched);

        return new SignupLineData($isInTeam ? "You're in the team." : "You're on the bench.");
    }
}
