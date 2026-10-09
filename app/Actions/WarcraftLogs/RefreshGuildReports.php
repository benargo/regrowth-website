<?php

namespace App\Actions\WarcraftLogs;

use App\Models\WarcraftLogs\Guild;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Re-saves every report of a Warcraft Logs guild, so each one re-derives its
 * game version and phase after the versions or phases behind it changed.
 * It runs inline: officers expect their edit to show straight away.
 */
class RefreshGuildReports
{
    use AsAction;

    public function handle(Guild $guild): void
    {
        $guild->reports()->lazyById()->each->save();
    }
}
