<?php

namespace App\Observers;

use App\Jobs\Addon\BuildExportFile;
use App\Models\Character;
use Illuminate\Support\Facades\Cache;

class CharacterObserver
{
    /**
     * Handle the Character "created" event.
     */
    public function created(Character $character): void
    {
        Cache::tags(['characters'])->flush();
    }

    /**
     * Handle the Character "updated" event.
     */
    public function updated(Character $character): void
    {
        Cache::tags(['characters'])->flush();

        BuildExportFile::scheduleForGameVersionIds([$character->game_version_id, $character->getOriginal('game_version_id')]);
    }

    /**
     * Handle the Character "deleted" event.
     */
    public function deleted(Character $character): void
    {
        Cache::tags(['characters'])->flush();

        BuildExportFile::scheduleForGameVersionIds([$character->game_version_id, $character->getOriginal('game_version_id')]);
    }
}
