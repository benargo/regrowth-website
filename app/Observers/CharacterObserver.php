<?php

namespace App\Observers;

use App\Jobs\BuildAddonExportFile;
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

        BuildAddonExportFile::schedule();
    }

    /**
     * Handle the Character "deleted" event.
     */
    public function deleted(Character $character): void
    {
        Cache::tags(['characters'])->flush();

        BuildAddonExportFile::schedule();
    }
}
