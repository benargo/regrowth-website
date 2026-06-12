<?php

namespace App\Jobs\RaidHelper;

use App\Events\Broadcasts\CompositionChanged;
use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Resources\EventCompositionResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly EventData $data) {}

    public function handle(): void
    {
        $data = $this->data;
        $timezone = config('app.timezone', 'UTC');

        // Decode raids from the event description.
        $raidsString = str($data->description)
            ->after("-# Do not edit below this line...\n")
            ->trim();

        $raids = collect();

        if ($raidsString->isJson()) {
            $decoded = json_decode(stripslashes($raidsString), true);

            foreach ($decoded as $row) {
                if (! Arr::hasAll($row, ['id', 'name'])) {
                    Log::error('SyncEvent: skipping raid row with missing keys.', ['row' => $row]);

                    continue;
                }

                $query = Raid::where('id', Arr::get($row, 'id'))->where('name', Arr::get($row, 'name'));

                if ($query->exists()) {
                    $raids->push($query->first());
                }
            }
        }

        // Upsert the event.
        $event = Event::updateOrCreate(
            ['raid_helper_event_id' => $data->id],
            [
                'title' => $data->title,
                'start_time' => $data->startTime->setTimezone($timezone),
                'end_time' => $data->endTime->setTimezone($timezone),
                'background_css_class' => $raids->firstWhere('background_css_class')?->background_css_class ?? null,
                'color' => $data->color,
                'channel_id' => $data->channelId,
            ]
        );

        // Sync associated raids.
        $event->raids()->sync($raids->pluck('id')->all());

        // Sync benched characters from sign-ups (all signed-up, non-absent players not in comp are benched).
        $characterSync = [];

        $signUps = collect($data->signUps ?? [])
            ->whereNotIn('className', ['Absence', 'Late', 'Tentative']);

        Character::whereIn('name', $signUps->pluck('name'))->get()
            ->each(function (Character $character) use (&$characterSync): void {
                $characterSync[$character->id] = [
                    'slot_number' => null,
                    'group_number' => null,
                    'is_confirmed' => false,
                    'is_benched' => true,
                ];
            });

        $event->characters()->sync($characterSync);

        // Broadcast and flush cache.
        $event->load(['characters.playableClass', 'characters.rank', 'raids']);
        $composition = (new EventCompositionResource($event))->resolve();
        broadcast(new CompositionChanged($event->id, $composition));

        Cache::tags(['events'])->flush();
    }
}
