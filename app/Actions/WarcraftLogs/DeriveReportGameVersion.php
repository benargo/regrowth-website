<?php

namespace App\Actions\WarcraftLogs;

use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Report;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Sets a report's game version and phase from its guild and start time. The
 * game version is the guild's latest one released by the start time, and the
 * phase is that version's latest phase started by then. Both are null when the
 * report has no guild or start time, or nothing matches.
 *
 * The report is not saved, so callers can run this before their own save.
 */
class DeriveReportGameVersion
{
    use AsAction;

    public function handle(Report $report): Report
    {
        $report->game_version_id = $this->gameVersionId($report);
        $report->phase_id = $this->phaseId($report);

        return $report;
    }

    private function gameVersionId(Report $report): ?int
    {
        if ($report->warcraft_logs_guild_id === null || $report->start_time === null) {
            return null;
        }

        return GameVersion::where('warcraft_logs_guild_id', $report->warcraft_logs_guild_id)
            ->released($report->start_time)
            ->orderByDesc('release_date')
            ->orderBy('id')
            ->value('id');
    }

    private function phaseId(Report $report): ?int
    {
        if ($report->game_version_id === null) {
            return null;
        }

        return Phase::where('game_version_id', $report->game_version_id)
            ->whereNotNull('start_date')
            ->where('start_date', '<=', $report->start_time)
            ->orderByDesc('start_date')
            ->orderBy('id')
            ->value('id');
    }
}
