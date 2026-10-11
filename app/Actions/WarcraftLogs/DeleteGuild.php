<?php

namespace App\Actions\WarcraftLogs;

use App\Models\WarcraftLogs\Guild;
use App\Observers\ReportObserver;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Deletes a Warcraft Logs guild and its tags. Its game versions and reports
 * are detached rather than deleted, and the reports lose their deleted tag;
 * a report without a guild derives no game version or phase, so both are
 * cleared in the same update. Attendance rows and report links are never touched.
 *
 * Tags are deleted through Eloquent so GuildTagObserver flushes its caches;
 * the foreign key's cascade only backs this up.
 *
 * The bulk updates skip GameVersionObserver and ReportObserver on purpose: the
 * only reports a detach affects are this guild's, which are cleared here, and
 * the report caches are flushed once instead.
 */
class DeleteGuild
{
    use AsAction;

    public function handle(Guild $guild): void
    {
        DB::transaction(function () use ($guild): void {
            $guild->gameVersions()->update(['warcraft_logs_guild_id' => null]);
            $guild->guildTags->each->delete();
            $guild->reports()->update([
                'warcraft_logs_guild_id' => null,
                'game_version_id' => null,
                'phase_id' => null,
            ]);

            $guild->delete();
        });

        ReportObserver::flushCaches();
    }
}
