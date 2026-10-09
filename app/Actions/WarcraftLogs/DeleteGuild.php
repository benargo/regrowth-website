<?php

namespace App\Actions\WarcraftLogs;

use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use App\Observers\ReportObserver;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Deletes a Warcraft Logs guild and its tags. Its game versions and reports
 * are detached rather than deleted, and the reports lose their deleted tag;
 * the detached reports then re-derive a null game version and phase.
 * Attendance rows and report links are never touched.
 *
 * Tags are deleted through Eloquent so GuildTagObserver flushes its caches;
 * the foreign key's cascade only backs this up.
 *
 * The bulk updates skip GameVersionObserver on purpose: the only reports a
 * detach affects are this guild's, and they are re-saved here.
 */
class DeleteGuild
{
    use AsAction;

    public function handle(Guild $guild): void
    {
        DB::transaction(function () use ($guild): void {
            $reportIds = $guild->reports()->pluck('id');

            $guild->gameVersions()->update(['warcraft_logs_guild_id' => null]);
            $guild->guildTags->each->delete();
            $guild->reports()->update(['warcraft_logs_guild_id' => null]);

            $guild->delete();

            ReportObserver::deferFlushing(fn () => Report::whereKey($reportIds)->lazyById()->each->save());
        });
    }
}
